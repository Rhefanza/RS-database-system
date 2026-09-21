<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\District;
use App\Models\Doctor;
use App\Models\Puskesmas;
use App\Models\PuskesmasService;
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
            'citizens' => Citizen::with('account')->orderBy('nama_lengkap')->get(),
            'districts' => District::withCount('puskesmas')->orderBy('nama_kecamatan')->get(),
            'accounts' => User::query()
                ->where('role', 'PETUGAS')
                ->with('puskesmas.district')
                ->orderBy('nama_lengkap')
                ->get(),
            'puskesmasItems' => Puskesmas::with('district')->withCount('puskesmasServices', 'officers')->orderBy('nama_puskesmas')->get(),
            'services' => Service::withCount('puskesmasServices')->orderBy('nama_layanan')->get(),
            'relations' => PuskesmasService::with('puskesmas', 'service')->withCount('schedules')->get()
                ->sortBy(fn ($item) => $item->puskesmas->nama_puskesmas.' '.$item->service->nama_layanan),
            'doctors' => Doctor::with('puskesmasService.puskesmas', 'puskesmasService.service')->orderBy('nama_dokter')->get(),
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
        Citizen::create($this->citizenData($request));

        return $this->success('masyarakat', 'Data masyarakat ditambahkan.');
    }

    public function updateCitizen(Request $request, Citizen $citizen): RedirectResponse
    {
        $citizen->update($this->citizenData($request, $citizen));

        return $this->success('masyarakat', 'Data masyarakat diperbarui.');
    }

    public function destroyCitizen(Citizen $citizen): RedirectResponse
    {
        if ($citizen->account()->exists() || $citizen->queues()->exists()) {
            return $this->error('masyarakat', 'Data masih dipakai akun atau antrean. Nonaktifkan saja.');
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
        $hasQueues = Queue::whereHas('schedule.puskesmasService', fn ($query) => $query->where('puskesmas_id', $puskesmas->puskesmas_id))->exists();
        if ($puskesmas->officers()->exists() || $hasQueues) {
            return $this->error('puskesmas', 'Puskesmas masih memiliki petugas atau transaksi antrean.');
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
        if ($service->puskesmasServices()->exists()) {
            return $this->error('layanan', 'Layanan masih terhubung ke puskesmas.');
        }
        $service->delete();

        return $this->success('layanan', 'Layanan dihapus.');
    }

    public function storeRelation(Request $request): RedirectResponse
    {
        PuskesmasService::create($this->relationData($request));

        return $this->success('relasi', 'Layanan dihubungkan ke puskesmas.');
    }

    public function updateRelation(Request $request, PuskesmasService $relation): RedirectResponse
    {
        $relation->update($this->relationData($request, $relation));

        return $this->success('relasi', 'Relasi diperbarui.');
    }

    public function destroyRelation(PuskesmasService $relation): RedirectResponse
    {
        if ($relation->schedules()->exists()) {
            return $this->error('relasi', 'Relasi masih memiliki jadwal.');
        }
        $relation->delete();

        return $this->success('relasi', 'Relasi dihapus.');
    }

    public function storeDoctor(Request $request): RedirectResponse
    {
        Doctor::create($this->doctorData($request));

        return $this->success('dokter', 'Dokter ditambahkan.');
    }

    public function updateDoctor(Request $request, Doctor $doctor): RedirectResponse
    {
        $doctor->update($this->doctorData($request, $doctor));

        return $this->success('dokter', 'Dokter diperbarui.');
    }

    public function destroyDoctor(Doctor $doctor): RedirectResponse
    {
        $doctor->delete();

        return $this->success('dokter', 'Dokter dihapus.');
    }

    private function citizenData(Request $request, ?Citizen $citizen = null): array
    {
        return $request->validate([
            'nik' => ['required', 'digits:16', Rule::unique('masyarakat', 'nik')->ignore($citizen?->nik, 'nik')],
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

    private function doctorData(Request $request, ?Doctor $doctor = null): array
    {
        return $request->validate([
            'puskesmas_layanan_id' => ['required', 'exists:puskesmas_layanan,puskesmas_layanan_id'],
            'nama_dokter' => ['required', 'string', 'max:255', Rule::unique('dokter', 'nama_dokter')
                ->where('puskesmas_layanan_id', $request->input('puskesmas_layanan_id'))->ignore($doctor?->dokter_id, 'dokter_id')],
            'spesialisasi' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
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

    private function relationData(Request $request, ?PuskesmasService $relation = null): array
    {
        return $request->validate([
            'puskesmas_id' => ['required', 'exists:puskesmas,puskesmas_id'],
            'layanan_id' => ['required', 'exists:layanan,layanan_id', Rule::unique('puskesmas_layanan', 'layanan_id')
                ->where('puskesmas_id', $request->input('puskesmas_id'))->ignore($relation?->puskesmas_layanan_id, 'puskesmas_layanan_id')],
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
