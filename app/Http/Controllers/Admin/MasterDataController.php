<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Puskesmas;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Support\OfficerEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'citizens' => User::citizens()->orderBy('nama_lengkap')->get(),
            'districts' => District::withCount('puskesmas')->orderBy('nama_kecamatan')->get(),
            'accounts' => User::query()
                ->where('role', 'PETUGAS')
                ->with('puskesmas.district')
                ->orderBy('nama_lengkap')
                ->get(),
            'puskesmasItems' => Puskesmas::with('district')->withCount('schedules', 'officers')->orderBy('nama_puskesmas')->get(),
            'services' => Service::withCount('schedules')->orderBy('nama_layanan')->get(),
        ]);
    }

    public function storeDistrict(Request $request): RedirectResponse
    {
        District::create($this->districtData($request));

        return $this->success('kecamatan', 'Kecamatan ditambahkan.');
    }

    public function updateDistrict(Request $request, District $district): RedirectResponse
    {
        $district->update($this->districtData($request, $district));

        return $this->success('kecamatan', 'Kecamatan diperbarui.');
    }

    public function destroyDistrict(District $district): RedirectResponse
    {
        if ($district->puskesmas()->exists()) {
            return $this->error('kecamatan', 'Kecamatan masih dipakai oleh puskesmas.');
        }
        $district->delete();

        return $this->success('kecamatan', 'Kecamatan dihapus.');
    }

    public function storeCitizen(Request $request): RedirectResponse
    {
        User::create($this->citizenData($request) + ['role' => 'MASYARAKAT', 'status_akun' => 'NONAKTIF']);

        return $this->success('masyarakat', 'Data masyarakat ditambahkan.');
    }

    public function updateCitizen(Request $request, User $citizen): RedirectResponse
    {
        abort_unless($citizen->role === 'MASYARAKAT', 404);
        $citizen->update($this->citizenData($request, $citizen));

        return $this->success('masyarakat', 'Data masyarakat diperbarui.');
    }

    public function destroyCitizen(User $citizen): RedirectResponse
    {
        abort_unless($citizen->role === 'MASYARAKAT', 404);
        if ($citizen->queues()->exists()) {
            return $this->error('masyarakat', 'Data masih dipakai antrean. Nonaktifkan saja.');
        }
        $citizen->delete();

        return $this->success('masyarakat', 'Data masyarakat dihapus.');
    }

    public function storeOfficer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'puskesmas_id' => ['required', Rule::exists('puskesmas', 'puskesmas_id')->where('status', 'AKTIF')],
        ]);
        $puskesmas = Puskesmas::findOrFail($data['puskesmas_id']);
        $data['email'] = OfficerEmail::forPuskesmas($puskesmas);
        $data['password_hash'] = $data['password'];
        unset($data['password']);
        User::create([...$data, 'role' => 'PETUGAS', 'status_akun' => 'AKTIF']);

        return $this->success('akun', 'Akun petugas ditambahkan.');
    }

    public function updateAccount(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->role === 'PETUGAS', 404);

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'puskesmas_id' => ['required', Rule::exists('puskesmas', 'puskesmas_id')->where('status', 'AKTIF')],
            'status_akun' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        if ((int) $data['puskesmas_id'] !== (int) $account->puskesmas_id) {
            $data['email'] = OfficerEmail::forPuskesmas(Puskesmas::findOrFail($data['puskesmas_id']), $account);
        }
        if ($data['password'] ?? null) {
            $data['password_hash'] = $data['password'];
        }
        unset($data['password']);
        $account->update($data);

        return $this->success('akun', 'Akun diperbarui.');
    }

    public function destroyAccount(User $account): RedirectResponse
    {
        abort_unless($account->role === 'PETUGAS', 404);

        $account->delete();

        return $this->success('akun', 'Akun petugas dihapus.');
    }

    public function storePuskesmas(Request $request): RedirectResponse
    {
        Puskesmas::create($this->puskesmasData($request));

        return $this->success('puskesmas', 'Puskesmas ditambahkan.');
    }

    public function updatePuskesmas(Request $request, Puskesmas $puskesmas): RedirectResponse
    {
        $puskesmas->update($this->puskesmasData($request, $puskesmas));

        return $this->success('puskesmas', 'Puskesmas diperbarui.');
    }

    public function destroyPuskesmas(Puskesmas $puskesmas): RedirectResponse
    {
        $hasQueues = Queue::whereHas('schedule', fn ($query) => $query->where('puskesmas_id', $puskesmas->puskesmas_id))->exists();
        if ($puskesmas->officers()->exists() || $puskesmas->schedules()->exists() || $hasQueues) {
            return $this->error('puskesmas', 'Puskesmas masih memiliki petugas, jadwal, atau transaksi antrean.');
        }
        $puskesmas->delete();

        return $this->success('puskesmas', 'Puskesmas dihapus.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        Service::create($this->serviceData($request));

        return $this->success('layanan', 'Layanan ditambahkan.');
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->serviceData($request, $service));

        return $this->success('layanan', 'Layanan diperbarui.');
    }

    public function destroyService(Service $service): RedirectResponse
    {
        if ($service->schedules()->exists()) {
            return $this->error('layanan', 'Layanan masih terhubung ke puskesmas.');
        }
        $service->delete();

        return $this->success('layanan', 'Layanan dihapus.');
    }

    private function citizenData(Request $request, ?User $citizen = null): array
    {
        return $request->validate([
            'nik' => ['required', 'digits:16', Rule::unique('akun', 'nik')->ignore($citizen?->akun_id, 'akun_id')],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'status_data' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }

    private function puskesmasData(Request $request, ?Puskesmas $puskesmas = null): array
    {
        return $request->validate([
            'kecamatan_id' => ['required', 'exists:kecamatan,kecamatan_id'],
            'nama_puskesmas' => ['required', 'string', 'max:255', Rule::unique('puskesmas', 'nama_puskesmas')->ignore($puskesmas?->puskesmas_id, 'puskesmas_id')],
            'alamat' => ['required', 'string', 'max:1000'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-7.5,-7.0'],
            'longitude' => ['nullable', 'numeric', 'between:112.5,113.0'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }

    private function districtData(Request $request, ?District $district = null): array
    {
        return $request->validate([
            'nama_kecamatan' => ['required', 'string', 'max:255', Rule::unique('kecamatan', 'nama_kecamatan')->ignore($district?->kecamatan_id, 'kecamatan_id')],
        ]);
    }

    private function serviceData(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'nama_layanan' => ['required', 'string', 'max:255', Rule::unique('layanan', 'nama_layanan')->ignore($service?->layanan_id, 'layanan_id')],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }

    private function success(string $section, string $message): RedirectResponse
    {
        return redirect()->route('admin.master.index')->withFragment($section)->with('success', $message);
    }

    private function error(string $section, string $message): RedirectResponse
    {
        return redirect()->route('admin.master.index')->withFragment($section)->with('error', $message);
    }
}
