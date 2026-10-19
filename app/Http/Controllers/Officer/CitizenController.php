<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CitizenController extends Controller
{
    public function index(): View
    {
        $citizens = User::citizens()->orderBy('nama_lengkap')->get();

        return view('officer.citizens.index', compact('citizens'));
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->data($request) + ['role' => 'MASYARAKAT', 'status_akun' => 'NONAKTIF']);

        return back()->with('success', 'Data masyarakat ditambahkan.');
    }

    public function update(Request $request, User $citizen): RedirectResponse
    {
        abort_unless($citizen->role === 'MASYARAKAT', 404);
        $citizen->update($this->data($request, $citizen));

        return back()->with('success', 'Data masyarakat diperbarui.');
    }

    public function destroy(User $citizen): RedirectResponse
    {
        abort_unless($citizen->role === 'MASYARAKAT', 404);
        if ($citizen->queues()->exists()) {
            return back()->with('error', 'Data masih dipakai antrean. Nonaktifkan saja.');
        }
        $citizen->delete();

        return back()->with('success', 'Data masyarakat dihapus.');
    }

    private function data(Request $request, ?User $citizen = null): array
    {
        return $request->validate([
            'nik' => ['required', 'digits:16', Rule::unique('akun', 'nik')->ignore($citizen?->akun_id, 'akun_id')],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'status_data' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }
}
