<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Queue;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $schedules = Schedule::with('puskesmas', 'service')
            ->where('status', 'AKTIF')
            ->whereHas('service', fn ($service) => $service->where('status', 'AKTIF'))
            ->whereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('status', 'AKTIF'))
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->where('puskesmas_id', $request->user()->puskesmas_id))->get();
        $queues = Queue::with('account', 'schedule.puskesmas', 'schedule.service')
            ->active()
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->whereHas('schedule', fn ($relation) => $relation->where('puskesmas_id', $request->user()->puskesmas_id)))
            ->latest('tanggal_daftar')->latest('nomor_antrean')->get();

        return view('officer.queues.index', ['schedules' => $schedules, 'queues' => $queues, 'citizens' => User::citizens()->where('status_data', 'AKTIF')->orderBy('nama_lengkap')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nik' => ['required', Rule::exists('akun', 'nik')->where('role', 'MASYARAKAT')->where('status_data', 'AKTIF')],
            'jadwal_id' => ['required', 'exists:jadwal,jadwal_id'],
            'tanggal_daftar' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $schedule = Schedule::with('puskesmas')->findOrFail($data['jadwal_id']);
        $this->authorizeQueueScope($request, $schedule);
        if ($this->dayName(Carbon::parse($data['tanggal_daftar'])) !== $schedule->hari) {
            throw ValidationException::withMessages(['tanggal_daftar' => 'Tanggal tidak sesuai dengan hari jadwal. Pilih poli yang tersedia pada tanggal tersebut.']);
        }
        DB::transaction(function () use ($data, $schedule) {
            $citizen = User::citizens()->where('nik', $data['nik'])->lockForUpdate()->firstOrFail();
            $data['akun_id'] = $citizen->akun_id;
            unset($data['nik']);
            $locked = Schedule::whereKey($schedule->jadwal_id)->lockForUpdate()->firstOrFail();
            if ($citizen->status_data !== 'AKTIF') {
                throw ValidationException::withMessages(['nik' => 'Data masyarakat tidak aktif.']);
            }
            if ($locked->status !== 'AKTIF' || $locked->service?->status !== 'AKTIF' || $locked->puskesmas?->status !== 'AKTIF') {
                throw ValidationException::withMessages(['jadwal_id' => 'Jadwal atau puskesmas tidak aktif. Pilih jadwal lain.']);
            }
            if ($this->dayName(Carbon::parse($data['tanggal_daftar'])) !== $locked->hari) {
                throw ValidationException::withMessages(['tanggal_daftar' => 'Jadwal telah berubah. Pilih kembali tanggal dan poli.']);
            }
            $activeCount = $locked->queues()->active()->whereDate('tanggal_daftar', $data['tanggal_daftar'])->count();
            if ($activeCount >= $locked->kapasitas) {
                throw ValidationException::withMessages(['jadwal_id' => 'Kapasitas antrean sudah penuh.']);
            }
            if ($locked->queues()->active()->where('akun_id', $data['akun_id'])->whereDate('tanggal_daftar', $data['tanggal_daftar'])->exists()) {
                throw ValidationException::withMessages(['nik' => 'Masyarakat sudah terdaftar pada jadwal ini.']);
            }
            if (Queue::overlappingActiveFor($data['akun_id'], $locked, $data['tanggal_daftar'])) {
                throw ValidationException::withMessages(['jadwal_id' => 'Masyarakat masih memiliki antrean aktif pada jadwal yang bertabrakan.']);
            }
            $data['nomor_antrean'] = ((int) $locked->queues()->whereDate('tanggal_daftar', $data['tanggal_daftar'])->max('nomor_antrean')) + 1;
            $data['status_antrean'] = 'WAITING';
            Queue::create($data);
        });

        return back()->with('success', 'Data antrean ditambahkan.');
    }

    public function update(Request $request, Queue $queue): RedirectResponse
    {
        $this->authorizeQueueScope($request, $queue->schedule);
        $data = $request->validate(['status_antrean' => ['required', Rule::in($this->statuses())]]);
        $allowed = [
            'WAITING' => ['WAITING', 'CALLED', 'CANCELLED'],
            'CALLED' => ['CALLED', 'SERVING', 'CANCELLED'],
            'SERVING' => ['SERVING', 'COMPLETED', 'CANCELLED'],
            'COMPLETED' => ['COMPLETED'],
            'CANCELLED' => ['CANCELLED'],
        ];
        abort_unless(in_array($data['status_antrean'], $allowed[$queue->status_antrean], true), 409, 'Urutan perubahan status antrean tidak valid.');
        $queue->update($data);

        return back()->with('success', 'Status antrean diperbarui.');
    }

    public function destroy(Request $request, Queue $queue): RedirectResponse
    {
        $this->authorizeQueueScope($request, $queue->schedule);
        abort_unless(in_array($queue->status_antrean, ['COMPLETED', 'CANCELLED'], true), 409, 'Antrean aktif tidak dapat dihapus. Selesaikan atau batalkan terlebih dahulu.');
        $queue->delete();

        return back()->with('success', 'Data antrean dihapus.');
    }

    private function authorizeQueueScope(Request $request, Schedule $schedule): void
    {
        if ($request->user()->role === 'PETUGAS') {
            abort_unless((int) $schedule->puskesmas_id === (int) $request->user()->puskesmas_id, 403);
        }
    }

    private function statuses(): array
    {
        return ['WAITING', 'CALLED', 'SERVING', 'COMPLETED', 'CANCELLED'];
    }

    private function dayName(Carbon $date): string
    {
        return ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][$date->format('l')];
    }
}
