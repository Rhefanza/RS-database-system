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
        if (Auth::check()) {
            return redirect()->to($this->destinationFor(Auth::user()->role));
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'account_status' => 'ACTIVE'], $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $destination = $this->destinationFor($request->user()->role);

        return redirect()->intended($destination)
            ->with('success', 'Selamat datang, '.$request->user()->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah keluar dari panel pengelola.');
    }

    private function destinationFor(string $role): string
    {
        return match ($role) {
            'ADMIN' => route('admin.index'),
            'OFFICER' => route('admin.queues.index'),
            default => route('home'),
        };
    }
}
