<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\SystemBackup;
use App\Services\AuditLogger;
use App\Services\InventoryPageContextService;
use App\Services\SystemBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SystemBackupController extends Controller
{
    public function __construct(
        private readonly SystemBackupService $systemBackupService,
        private readonly InventoryPageContextService $pages,
    ) {}

    public function index()
    {
        $this->ensureAdmin();

        return view('inventory.maintenance.index', array_merge($this->pages->base('maintenance'), [
            'systemBackups' => SystemBackup::with(['createdBy', 'restoredBy'])->latest()->get(),
            'backupToolsAvailable' => $this->systemBackupService->binariesAvailable(),
        ]));
    }

    public function createSystemBackup()
    {
        $this->ensureAdmin();

        try {
            $backup = $this->systemBackupService->create(auth()->user());
            AuditLogger::log('system_backup.created', 'Backup sistem dibuat.', $backup, [
                'filename' => $backup->filename,
                'size' => $backup->size,
            ]);

            return back()->with('success', 'Backup sistem berhasil dibuat.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Backup gagal: '.$exception->getMessage());
        }
    }

    public function downloadSystemBackup(SystemBackup $systemBackup)
    {
        $this->ensureAdmin();
        $path = $this->systemBackupService->absolutePath($systemBackup);
        abort_unless(is_file($path), 404);

        return response()->download($path, $systemBackup->filename, ['Content-Type' => 'application/zip']);
    }

    public function deleteSystemBackup(SystemBackup $systemBackup)
    {
        $this->ensureAdmin();
        abort_if($systemBackup->status === 'restoring', 409);
        $this->systemBackupService->delete($systemBackup);
        AuditLogger::log('system_backup.deleted', 'File backup sistem dihapus.', null, [
            'filename' => $systemBackup->filename,
        ]);

        return back()->with('success', 'Backup berhasil dihapus.');
    }

    public function importSystemBackup(Request $request)
    {
        $this->ensureAdmin();
        $data = $this->validateRestoreRequest($request, true);

        try {
            $backup = $this->systemBackupService->import($data['backup_file'], auth()->user());

            return $this->performSystemRestore($request, $backup);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Restore gagal: '.$exception->getMessage());
        }
    }

    public function restoreSystemBackup(Request $request, SystemBackup $systemBackup)
    {
        $this->ensureAdmin();
        $this->validateRestoreRequest($request);

        try {
            return $this->performSystemRestore($request, $systemBackup);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Restore gagal: '.$exception->getMessage());
        }
    }

    private function validateRestoreRequest(Request $request, bool $withFile = false): array
    {
        $rules = [
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:RESTORE'],
        ];
        if ($withFile) {
            $rules['backup_file'] = ['required', 'file', 'max:524288'];
        }

        $data = $request->validate($rules);
        if (! Hash::check($data['password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password akun tidak sesuai.']);
        }

        return $data;
    }

    /** Restore invalidates every active session after replacing the database. */
    private function performSystemRestore(Request $request, SystemBackup $backup)
    {
        $user = auth()->user();
        $this->systemBackupService->restore($backup, $user);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Restore selesai. Silakan login kembali.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->isAdmin(), 403);
    }
}
