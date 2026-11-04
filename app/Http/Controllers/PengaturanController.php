<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class PengaturanController extends Controller
{
    public function index()
    {
        return view('pengaturan.index');
    }

    public function updateProfil(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
        ], [
            'name.required'     => 'Nama lengkap wajib diisi.',
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'username.required' => 'Username wajib diisi.',
        ]);

        DB::table('users')
            ->where('id', Auth::id())
            ->update([
                'name'       => $validated['name'],
                'email'      => $validated['email'],
                'username'   => $validated['username'],
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_lama'    => ['required'],
            'password_baru'    => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password_lama.required'    => 'Password lama wajib diisi.',
            'password_baru.required'    => 'Password baru wajib diisi.',
            'password_baru.min'         => 'Password baru minimal 8 karakter.',
            'password_baru.confirmed'   => 'Konfirmasi password tidak cocok.',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password_lama, $user->password)) {
            return back()->withErrors(['password_lama' => 'Password lama tidak sesuai.']);
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'password'   => Hash::make($request->password_baru),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Password berhasil diubah.');
    }
}
