<?php

namespace App\Services;

use App\Models\SystemBackup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class SystemBackupService
{
    public function create(User $user, string $source = 'manual'): SystemBackup
    {
        $this->ensureSupported();
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory);
        $workDirectory = storage_path('app/private/backup-work/'.Str::uuid());
        File::ensureDirectoryExists($workDirectory);

        try {
            $databasePath = $workDirectory.'/database.sql';
            $this->dumpDatabase($databasePath);
            $manifest = $this->signedManifest($databasePath);
            File::put($workDirectory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $filename = 'backup-inventory-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(5)).'.zip';
            $absolutePath = $directory.'/'.$filename;
            $this->buildZip($absolutePath, $workDirectory);

            return SystemBackup::create([
                'filename' => $filename,
                'path' => 'system-backups/'.$filename,
                'size' => File::size($absolutePath),
                'checksum' => hash_file('sha256', $absolutePath),
                'source' => $source,
                'status' => 'ready',
                'created_by' => $user->id,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    public function import(UploadedFile $file, User $user): SystemBackup
    {
        $this->ensureSupported();
        $manifest = $this->verifyPackage($file->getRealPath());
        $filename = 'import-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(5)).'.zip';
        $absolutePath = $this->backupDirectory().'/'.$filename;
        File::ensureDirectoryExists($this->backupDirectory());
        File::copy($file->getRealPath(), $absolutePath);

        return SystemBackup::create([
            'filename' => $filename,
            'path' => 'system-backups/'.$filename,
            'size' => File::size($absolutePath),
            'checksum' => hash_file('sha256', $absolutePath),
            'source' => 'import',
            'status' => 'ready',
            'created_by' => $user->id,
            'error_message' => 'Paket '.($manifest['generated_at'] ?? '-').' siap direstore.',
        ]);
    }

    public function restore(SystemBackup $backup, User $user): void
    {
        $path = $this->absolutePath($backup);
        if (! File::exists($path) || ! hash_equals($backup->checksum, hash_file('sha256', $path))) {
            throw new RuntimeException('File backup tidak ditemukan atau checksum file berubah.');
        }

        $this->verifyPackage($path);
        $safetyBackup = $this->create($user, 'pre_restore');
        $workDirectory = storage_path('app/private/restore-work/'.Str::uuid());
        File::ensureDirectoryExists($workDirectory);
        Artisan::call('down');

        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Paket backup tidak dapat dibuka.');
            }
            File::put($workDirectory.'/database.sql', $zip->getFromName('database.sql'));
            $storageDirectory = $workDirectory.'/storage/public';
            File::ensureDirectoryExists($storageDirectory);
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! str_starts_with($name, 'storage/public/') || str_contains($name, '..') || str_ends_with($name, '/')) {
                    continue;
                }
                $target = $workDirectory.'/'.$name;
                File::ensureDirectoryExists(dirname($target));
                File::put($target, $zip->getFromIndex($index));
            }
            $zip->close();

            $this->restoreDatabase($workDirectory.'/database.sql');
            DB::purge(config('database.default'));
            DB::reconnect(config('database.default'));

            File::deleteDirectory(storage_path('app/public'));
            File::ensureDirectoryExists(storage_path('app/public'));
            File::copyDirectory($storageDirectory, storage_path('app/public'));

            DB::table(config('session.table', 'sessions'))->delete();
            DB::table('users')->update(['remember_token' => null]);
            Artisan::call('optimize:clear');
            SystemBackup::updateOrCreate(['path' => $safetyBackup->path], [
                'filename' => $safetyBackup->filename,
                'size' => $safetyBackup->size,
                'checksum' => $safetyBackup->checksum,
                'source' => 'pre_restore',
                'status' => 'ready',
                'created_by' => User::whereKey($user->id)->exists() ? $user->id : null,
            ]);
            SystemBackup::updateOrCreate(['path' => $backup->path], [
                'filename' => $backup->filename,
                'size' => $backup->size,
                'checksum' => $backup->checksum,
                'source' => $backup->source,
                'status' => 'restored',
                'restored_by' => User::whereKey($user->id)->exists() ? $user->id : null,
                'restored_at' => now(),
                'error_message' => null,
            ]);
            File::append(storage_path('logs/restore.log'), now().' restore '.$backup->filename.' oleh '.$user->email.PHP_EOL);
        } catch (\Throwable $exception) {
            File::append(storage_path('logs/restore.log'), now().' GAGAL '.$backup->filename.': '.$exception->getMessage().PHP_EOL);
            throw $exception;
        } finally {
            File::deleteDirectory($workDirectory);
            Artisan::call('up');
        }
    }

    public function verifyPackage(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File bukan paket ZIP yang valid.');
        }
        $manifestJson = $zip->getFromName('manifest.json');
        $database = $zip->getFromName('database.sql');
        $zip->close();
        if ($manifestJson === false || $database === false) {
            throw new RuntimeException('Paket tidak memiliki manifest atau database.');
        }
        $manifest = json_decode($manifestJson, true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 'inventory-al-backup-v1') {
            throw new RuntimeException('Format paket backup tidak dikenali.');
        }
        $signature = (string) ($manifest['signature'] ?? '');
        unset($manifest['signature']);
        $expected = hash_hmac('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES), (string) config('app.key'));
        if (! hash_equals($expected, $signature) || ! hash_equals((string) ($manifest['database_checksum'] ?? ''), hash('sha256', $database))) {
            throw new RuntimeException('Tanda tangan atau checksum paket backup tidak valid.');
        }
        if (($manifest['schema_version'] ?? null) !== $this->schemaVersion()) {
            throw new RuntimeException('Versi struktur database backup tidak sama dengan aplikasi saat ini.');
        }
        $manifest['signature'] = $signature;

        return $manifest;
    }

    public function absolutePath(SystemBackup $backup): string
    {
        return storage_path('app/private/'.$backup->path);
    }

    public function delete(SystemBackup $backup): void
    {
        File::delete($this->absolutePath($backup));
        $backup->delete();
    }

    public function binariesAvailable(): bool
    {
        return File::exists($this->mysqlBinary('mysqldump')) && File::exists($this->mysqlBinary('mysql')) && class_exists(ZipArchive::class);
    }

    private function signedManifest(string $databasePath): array
    {
        $manifest = [
            'format' => 'inventory-al-backup-v1',
            'generated_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'schema_version' => $this->schemaVersion(),
            'database_checksum' => hash_file('sha256', $databasePath),
        ];
        $manifest['signature'] = hash_hmac('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES), (string) config('app.key'));

        return $manifest;
    }

    private function buildZip(string $target, string $workDirectory): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File ZIP backup tidak dapat dibuat.');
        }
        $zip->addFile($workDirectory.'/database.sql', 'database.sql');
        $zip->addFile($workDirectory.'/manifest.json', 'manifest.json');
        $public = storage_path('app/public');
        if (File::isDirectory($public)) {
            foreach (File::allFiles($public) as $file) {
                $zip->addFile($file->getPathname(), 'storage/public/'.$file->getRelativePathname());
            }
        }
        $zip->close();
    }

    private function dumpDatabase(string $target): void
    {
        $handle = fopen($target, 'wb');
        $process = new Process(array_merge([$this->mysqlBinary('mysqldump')], $this->connectionArguments(), [
            '--single-transaction', '--routines', '--triggers', '--add-drop-table', (string) config('database.connections.'.config('database.default').'.database'),
        ]), null, $this->processEnvironment(), null, 300);
        $process->run(function ($type, $buffer) use ($handle) {
            if ($type === Process::OUT) fwrite($handle, $buffer);
        });
        fclose($handle);
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Backup database gagal: '.trim($process->getErrorOutput()));
        }
    }

    private function restoreDatabase(string $source): void
    {
        $process = new Process(array_merge([$this->mysqlBinary('mysql')], $this->connectionArguments(), [
            (string) config('database.connections.'.config('database.default').'.database'),
        ]), null, $this->processEnvironment(), null, 300);
        $handle = fopen($source, 'rb');
        $process->setInput($handle);
        $process->run();
        fclose($handle);
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore database gagal: '.trim($process->getErrorOutput()));
        }
    }

    private function connectionArguments(): array
    {
        $connection = config('database.connections.'.config('database.default'));
        return ['--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'], '--default-character-set=utf8mb4'];
    }

    private function processEnvironment(): array
    {
        $connection = config('database.connections.'.config('database.default'));
        return ['MYSQL_PWD' => (string) ($connection['password'] ?? '')];
    }

    private function schemaVersion(): string
    {
        return (string) DB::table('migrations')->max('migration');
    }

    private function mysqlBinary(string $name): string
    {
        $configured = env(strtoupper($name).'_PATH');
        return $configured ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/'.$name.'.exe' : '/usr/bin/'.$name);
    }

    private function backupDirectory(): string
    {
        return storage_path('app/private/system-backups');
    }

    private function ensureSupported(): void
    {
        if (config('database.default') !== 'mysql' || ! $this->binariesAvailable()) {
            throw new RuntimeException('Backup membutuhkan MySQL, mysqldump, mysql client, dan ekstensi ZIP.');
        }
    }
}
