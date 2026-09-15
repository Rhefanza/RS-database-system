<?php

namespace App\Http\Controllers;

use App\Models\Citizen;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function create(): View
    {
        return view('auth.activate');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'digits:16', Rule::exists('masyarakat', 'nik')->where('status_data', 'AKTIF'), 'unique:akun,nik'],
            'email' => ['required', 'email', 'max:255', 'unique:akun,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], ['nik.exists' => 'NIK tidak ditemukan atau data tidak aktif.', 'nik.unique' => 'NIK ini sudah mempunyai akun.']);

        $citizen = Citizen::findOrFail($validated['nik']);
        User::create([
            'nik' => $citizen->nik,
            'nama_lengkap' => $citizen->nama_lengkap,
            'email' => $validated['email'],
            'password_hash' => $validated['password'],
            'role' => 'MASYARAKAT',
            'status_akun' => 'AKTIF',
        ]);

        return redirect()->route('login')->with('success', 'Aktivasi berhasil. Silakan masuk dengan email dan kata sandi Anda.');
    }
}
