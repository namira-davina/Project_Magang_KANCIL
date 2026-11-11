<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Menampilkan halaman login
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    // Memproses login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $user = User::where(function ($q) use ($credentials) {
            $q->where('username', $credentials['username'])
                ->orWhere('email', $credentials['username']);
        })
            ->whereIn('role', User::ROLES)
            ->where('is_active', 1)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withInput($request->only('username'))
                ->with('message', 'Username atau kata sandi tidak sesuai.');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('message', 'Anda berhasil keluar dari sistem.');
    }
}
