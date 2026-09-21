<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()->load('citizen')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
        if ($user->role !== 'PETUGAS') {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('akun', 'email')->ignore($user->akun_id, 'akun_id')];
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($user, $validated) {
            $account = ['nama_lengkap' => $validated['nama_lengkap']];
            if ($user->role !== 'PETUGAS') {
                $account['email'] = $validated['email'];
            }
            if ($validated['password'] ?? null) {
                $account['password_hash'] = $validated['password'];
            }
            $user->update($account);
            $user->citizen?->update([
                'nama_lengkap' => $validated['nama_lengkap'],
                'nomor_telepon' => $validated['nomor_telepon'] ?? null,
                'alamat' => $validated['alamat'] ?? null,
            ]);
        });

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
