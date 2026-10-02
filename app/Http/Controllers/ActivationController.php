<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function create(): View
    {
        return view('auth.activate');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nik' => ['required', 'digits:16'],
            'email' => ['required', 'email', 'max:255', 'unique:akun,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        DB::transaction(function () use ($data) {
            $account = User::citizens()->where('nik', $data['nik'])->lockForUpdate()->first();
            if (! $account || $account->status_data !== 'AKTIF') {
                throw ValidationException::withMessages(['nik' => 'NIK tidak ditemukan atau data tidak aktif.']);
            }
            if ($account->password_hash !== null) {
                throw ValidationException::withMessages(['nik' => 'NIK ini sudah diaktivasi.']);
            }
            $account->update(['email' => $data['email'], 'password_hash' => $data['password'], 'status_akun' => 'AKTIF']);
        });

        return redirect()->route('login')->with('success', 'Aktivasi berhasil. Silakan masuk dengan email dan kata sandi Anda.');
    }
}
