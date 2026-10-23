<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Email atau password tidak sesuai.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        AuditLogger::log('login', 'User login ke sistem.', $request->user());

        return redirect()->intended(route($request->user()->defaultRouteName()));
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'signature_upload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:1024',
                'dimensions:max_width=2400,max_height=1200',
                Rule::prohibitedIf(fn (): bool => $request->boolean('remove_signature') || $request->filled('signature_drawn')),
            ],
            'signature_drawn' => [
                'nullable',
                'string',
                'max:1400000',
                Rule::prohibitedIf(fn (): bool => $request->boolean('remove_signature') || $request->hasFile('signature_upload')),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,')) {
                        $fail('Format gambar TTD tidak valid.');

                        return;
                    }

                    $image = base64_decode(substr($value, strlen('data:image/png;base64,')), true);
                    $dimensions = $image === false ? false : @getimagesizefromstring($image);

                    if ($image === false || strlen($image) > 1024 * 1024 || $dimensions === false || ($dimensions['mime'] ?? null) !== 'image/png') {
                        $fail('Gambar TTD harus berupa PNG valid dengan ukuran maksimal 1 MB.');

                        return;
                    }

                    if ($dimensions[0] > 2400 || $dimensions[1] > 1200) {
                        $fail('Dimensi gambar TTD maksimal 2400 x 1200 piksel.');
                    }
                },
            ],
            'remove_signature' => ['nullable', 'boolean'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi untuk menyimpan profil.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.letters' => 'Password baru harus memiliki minimal satu huruf.',
            'password.numbers' => 'Password baru harus memiliki minimal satu angka.',
            'signature_upload.dimensions' => 'Dimensi file TTD maksimal 2400 x 1200 piksel.',
            'signature_upload.prohibited' => 'Pilih salah satu tindakan TTD: upload file, gambar langsung, atau hapus.',
            'signature_drawn.prohibited' => 'Pilih salah satu tindakan TTD: upload file, gambar langsung, atau hapus.',
        ]);

        $credentialsChanged = $user->email !== $data['email'] || ! empty($data['password']);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if ($request->boolean('remove_signature')) {
            $user->signature_image = null;
            $user->signature_method = null;
        }

        if ($request->hasFile('signature_upload')) {
            $file = $request->file('signature_upload');
            $user->signature_image = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));
            $user->signature_method = 'upload';
        } elseif (! empty($data['signature_drawn'])) {
            $user->signature_image = $data['signature_drawn'];
            $user->signature_method = 'draw';
        }

        if ($credentialsChanged) {
            $user->setRememberToken(bin2hex(random_bytes(30)));
        }

        $user->save();

        if ($credentialsChanged && config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        if ($credentialsChanged) {
            $request->session()->regenerate();
        }

        AuditLogger::log('profile.updated', 'User memperbarui profil.', $user);

        return redirect()->route('dashboard')->with('success', 'Profil berhasil diperbarui.');
    }

    public function logout(Request $request)
    {
        AuditLogger::log('logout', 'User logout dari sistem.', $request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
