<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\Service;
use App\Models\ServiceDesk;
use App\Models\ServiceSchedule;
use App\Models\SpecialServiceSchedule;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'districts' => District::withCount('hospitals')->orderBy('name')->get(),
            'services' => Service::withCount('hospitalServices')->orderBy('name')->get(),
            'hospitals' => Hospital::orderBy('name')->get(['id', 'name']),
            'hospitalServices' => HospitalService::with(['hospital:id,name', 'service:id,name'])
                ->withCount(['schedules', 'specialSchedules', 'desks'])->get()
                ->sortBy(fn ($item) => $item->hospital->name.' '.$item->service->name)->values(),
            'schedules' => ServiceSchedule::with('hospitalService.hospital:id,name', 'hospitalService.service:id,name')
                ->orderBy('day')->orderBy('opens_at')->get(),
            'specialSchedules' => SpecialServiceSchedule::with('hospitalService.hospital:id,name', 'hospitalService.service:id,name')
                ->orderByDesc('date')->get(),
            'desks' => ServiceDesk::with('hospitalService.hospital:id,name', 'hospitalService.service:id,name')
                ->orderBy('name')->get(),
            'users' => User::withCount('staffAssignments')->orderBy('name')->get(),
            'assignments' => StaffAssignment::with(['user:id,name', 'hospital:id,name'])
                ->orderByDesc('starts_on')->get(),
            'days' => $this->days(),
        ]);
    }

    public function storeDistrict(Request $request): RedirectResponse
    {
        District::create($request->validate(['name' => ['required', 'string', 'max:100', 'unique:districts,name']]));

        return $this->success('kecamatan', 'Kecamatan berhasil ditambahkan.');
    }

    public function updateDistrict(Request $request, District $district): RedirectResponse
    {
        $district->update($request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('districts')->ignore($district)]]));

        return $this->success('kecamatan', 'Kecamatan berhasil diperbarui.');
    }

    public function destroyDistrict(District $district): RedirectResponse
    {
        if ($district->hospitals()->exists()) {
            return $this->error('kecamatan', 'Kecamatan masih digunakan oleh rumah sakit dan tidak dapat dihapus.');
        }
        $district->delete();

        return $this->success('kecamatan', 'Kecamatan berhasil dihapus.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        Service::create($request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:services,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return $this->success('layanan', 'Master layanan berhasil ditambahkan.');
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $service->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('services')->ignore($service)],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return $this->success('layanan', 'Master layanan berhasil diperbarui.');
    }

    public function destroyService(Service $service): RedirectResponse
    {
        if ($service->hospitalServices()->exists()) {
            return $this->error('layanan', 'Layanan masih terhubung ke rumah sakit dan tidak dapat dihapus.');
        }
        $service->delete();

        return $this->success('layanan', 'Master layanan berhasil dihapus.');
    }

    public function storeHospitalService(Request $request): RedirectResponse
    {
        HospitalService::create($this->validateHospitalService($request));

        return $this->success('rumah-sakit-layanan', 'Layanan rumah sakit berhasil ditambahkan.');
    }

    public function updateHospitalService(Request $request, HospitalService $hospitalService): RedirectResponse
    {
        $hospitalService->update($this->validateHospitalService($request, $hospitalService));

        return $this->success('rumah-sakit-layanan', 'Layanan rumah sakit berhasil diperbarui.');
    }

    public function destroyHospitalService(HospitalService $hospitalService): RedirectResponse
    {
        if ($hospitalService->queueSessions()->exists()) {
            return $this->error('rumah-sakit-layanan', 'Layanan sudah memiliki riwayat antrean. Ubah status menjadi tidak aktif agar riwayat tetap aman.');
        }
        $hospitalService->delete();

        return $this->success('rumah-sakit-layanan', 'Layanan rumah sakit berhasil dihapus.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        ServiceSchedule::create($this->validateSchedule($request));

        return $this->success('jadwal', 'Jadwal layanan berhasil ditambahkan.');
    }

    public function updateSchedule(Request $request, ServiceSchedule $serviceSchedule): RedirectResponse
    {
        $serviceSchedule->update($this->validateSchedule($request));

        return $this->success('jadwal', 'Jadwal layanan berhasil diperbarui.');
    }

    public function destroySchedule(ServiceSchedule $serviceSchedule): RedirectResponse
    {
        $serviceSchedule->delete();

        return $this->success('jadwal', 'Jadwal layanan berhasil dihapus.');
    }

    public function storeSpecialSchedule(Request $request): RedirectResponse
    {
        SpecialServiceSchedule::create($this->validateSpecialSchedule($request));

        return $this->success('jadwal-khusus', 'Jadwal khusus berhasil ditambahkan.');
    }

    public function updateSpecialSchedule(Request $request, SpecialServiceSchedule $specialServiceSchedule): RedirectResponse
    {
        $specialServiceSchedule->update($this->validateSpecialSchedule($request, $specialServiceSchedule));

        return $this->success('jadwal-khusus', 'Jadwal khusus berhasil diperbarui.');
    }

    public function destroySpecialSchedule(SpecialServiceSchedule $specialServiceSchedule): RedirectResponse
    {
        $specialServiceSchedule->delete();

        return $this->success('jadwal-khusus', 'Jadwal khusus berhasil dihapus.');
    }

    public function storeDesk(Request $request): RedirectResponse
    {
        ServiceDesk::create($this->validateDesk($request));

        return $this->success('loket', 'Loket berhasil ditambahkan.');
    }

    public function updateDesk(Request $request, ServiceDesk $serviceDesk): RedirectResponse
    {
        $serviceDesk->update($this->validateDesk($request, $serviceDesk));

        return $this->success('loket', 'Loket berhasil diperbarui.');
    }

    public function destroyDesk(ServiceDesk $serviceDesk): RedirectResponse
    {
        if ($serviceDesk->queues()->whereIn('queue_status', ['CALLED', 'SERVING'])->exists()) {
            return $this->error('loket', 'Loket masih menangani antrean aktif dan tidak dapat dihapus.');
        }
        $serviceDesk->delete();

        return $this->success('loket', 'Loket berhasil dihapus.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        User::create($request->validate($this->userRules()));

        return $this->success('akun', 'Akun berhasil ditambahkan.');
    }

    public function storeOfficer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'hospital_id' => ['required', 'exists:hospitals,id'],
            'employee_code' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                ...collect($validated)->only(['name', 'email', 'phone', 'password'])->all(),
                'role' => 'OFFICER',
                'account_status' => 'ACTIVE',
            ]);
            StaffAssignment::create([
                'user_id' => $user->id,
                'hospital_id' => $validated['hospital_id'],
                'employee_code' => $validated['employee_code'] ?? null,
                'starts_on' => today(),
                'assignment_status' => 'ACTIVE',
            ]);
        });

        return $this->success('akun', 'Akun petugas dan penugasannya berhasil dibuat.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->userRules($user));
        if ($request->user()->is($user) && ($validated['role'] !== 'ADMIN' || $validated['account_status'] !== 'ACTIVE')) {
            return $this->error('akun', 'Akun yang sedang digunakan tidak boleh dinonaktifkan atau diubah dari peran admin.');
        }
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $user->update($validated);

        return $this->success('akun', 'Akun berhasil diperbarui.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return $this->error('akun', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }
        if ($user->staffAssignments()->exists() || $user->openedQueueSessions()->exists() || $user->closedQueueSessions()->exists()) {
            return $this->error('akun', 'Akun memiliki riwayat operasional. Nonaktifkan akun agar riwayat tetap tersimpan.');
        }
        $user->delete();

        return $this->success('akun', 'Akun berhasil dihapus.');
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        StaffAssignment::create($this->validateAssignment($request));

        return $this->success('penugasan', 'Penugasan petugas berhasil ditambahkan.');
    }

    public function updateAssignment(Request $request, StaffAssignment $staffAssignment): RedirectResponse
    {
        $staffAssignment->update($this->validateAssignment($request));

        return $this->success('penugasan', 'Penugasan petugas berhasil diperbarui.');
    }

    public function destroyAssignment(StaffAssignment $staffAssignment): RedirectResponse
    {
        $staffAssignment->delete();

        return $this->success('penugasan', 'Penugasan petugas berhasil dihapus.');
    }

    private function validateHospitalService(Request $request, ?HospitalService $hospitalService = null): array
    {
        return $request->validate([
            'hospital_id' => ['required', 'exists:hospitals,id'],
            'service_id' => [
                'required', 'exists:services,id',
                Rule::unique('hospital_services')->where('hospital_id', $request->input('hospital_id'))->ignore($hospitalService),
            ],
            'initial_service_duration' => ['nullable', 'integer', 'between:1,480'],
            'availability_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'hospital_service_id' => ['required', 'exists:hospital_services,id'],
            'day' => ['required', Rule::in(array_keys($this->days()))],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'schedule_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    private function validateSpecialSchedule(Request $request, ?SpecialServiceSchedule $schedule = null): array
    {
        return $request->validate([
            'hospital_service_id' => ['required', 'exists:hospital_services,id'],
            'date' => [
                'required', 'date',
                Rule::unique('special_service_schedules')->where('hospital_service_id', $request->input('hospital_service_id'))->ignore($schedule),
            ],
            'special_opens_at' => ['nullable', 'date_format:H:i', Rule::requiredIf($request->input('status') !== 'CLOSED')],
            'special_closes_at' => ['nullable', 'date_format:H:i', 'after:special_opens_at', Rule::requiredIf($request->input('status') !== 'CLOSED')],
            'status' => ['required', Rule::in(['OPEN', 'CLOSED', 'CHANGED'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validateDesk(Request $request, ?ServiceDesk $desk = null): array
    {
        return $request->validate([
            'hospital_service_id' => ['required', 'exists:hospital_services,id'],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('service_desks')->where('hospital_service_id', $request->input('hospital_service_id'))->ignore($desk),
            ],
            'desk_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    private function userRules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['ADMIN', 'OFFICER', 'PUBLIC'])],
            'account_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    private function validateAssignment(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'OFFICER')],
            'hospital_id' => ['required', 'exists:hospitals,id'],
            'employee_code' => ['nullable', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'assignment_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    private function days(): array
    {
        return [
            'MONDAY' => 'Senin', 'TUESDAY' => 'Selasa', 'WEDNESDAY' => 'Rabu',
            'THURSDAY' => 'Kamis', 'FRIDAY' => 'Jumat', 'SATURDAY' => 'Sabtu', 'SUNDAY' => 'Minggu',
        ];
    }

    private function success(string $section, string $message): RedirectResponse
    {
        return redirect(route('admin.master.index').'#'.$section)->with('success', $message);
    }

    private function error(string $section, string $message): RedirectResponse
    {
        return redirect(route('admin.master.index').'#'.$section)->withErrors(['data' => $message]);
    }
}
