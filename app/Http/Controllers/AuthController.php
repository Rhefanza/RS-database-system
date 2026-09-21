<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->to($this->destination(Auth::user()->role)) : view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt([...$credentials, 'status_akun' => 'AKTIF'])) {
            return back()->withErrors(['email' => 'Email, kata sandi, atau status akun tidak sesuai.'])->onlyInput('email');
        }

        $user = Auth::user();
        if ($user->role === 'PETUGAS' && (! $user->puskesmas_id || ! $user->puskesmas()->where('status', 'AKTIF')->exists())) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Akun petugas belum terhubung dengan puskesmas aktif. Hubungi admin.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->destination($request->user()->role))
            ->with('success', 'Selamat datang, '.$request->user()->nama_lengkap.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah keluar.');
    }

    private function destination(string $role): string
    {
        return match ($role) {
            'ADMIN' => route('admin.master.index'),
            'PETUGAS' => route('officer.schedules.index'),
            default => route('home'),
        };
    }
}
