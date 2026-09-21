<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Queue;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $schedules = Schedule::with('puskesmasService.puskesmas', 'puskesmasService.service')
            ->where('status', 'AKTIF')
            ->whereHas('puskesmasService', fn ($relation) => $relation->where('status', 'AKTIF')->whereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('status', 'AKTIF')))
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->whereHas('puskesmasService', fn ($relation) => $relation->where('puskesmas_id', $request->user()->puskesmas_id)))->get();
        $queues = Queue::with('citizen', 'schedule.puskesmasService.puskesmas', 'schedule.puskesmasService.service')
            ->active()
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->whereHas('schedule.puskesmasService', fn ($relation) => $relation->where('puskesmas_id', $request->user()->puskesmas_id)))
            ->latest('tanggal_daftar')->latest('nomor_antrean')->get();

        return view('officer.queues.index', ['schedules' => $schedules, 'queues' => $queues, 'citizens' => Citizen::where('status_data', 'AKTIF')->orderBy('nama_lengkap')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nik' => ['required', Rule::exists('masyarakat', 'nik')->where('status_data', 'AKTIF')],
            'jadwal_id' => ['required', 'exists:jadwal,jadwal_id'],
            'tanggal_daftar' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $schedule = Schedule::with('puskesmasService.puskesmas')->findOrFail($data['jadwal_id']);
        $this->authorizeQueueScope($request, $schedule);
        abort_unless(
            $schedule->status === 'AKTIF'
            && $schedule->puskesmasService?->status === 'AKTIF'
            && $schedule->puskesmasService?->puskesmas?->status === 'AKTIF',
            409,
            'Jadwal atau puskesmas tidak aktif.'
        );
        abort_if($this->dayName(Carbon::parse($data['tanggal_daftar'])) !== $schedule->hari, 409, 'Tanggal tidak sesuai dengan hari jadwal.');
        DB::transaction(function () use ($data, $schedule) {
            Citizen::whereKey($data['nik'])->lockForUpdate()->firstOrFail();
            $locked = Schedule::whereKey($schedule->jadwal_id)->lockForUpdate()->firstOrFail();
            $activeCount = $locked->queues()->active()->whereDate('tanggal_daftar', $data['tanggal_daftar'])->count();
            abort_if($activeCount >= $locked->kapasitas, 409, 'Kapasitas antrean sudah penuh.');
            abort_if($locked->queues()->active()->where('nik', $data['nik'])->whereDate('tanggal_daftar', $data['tanggal_daftar'])->exists(), 409, 'Masyarakat sudah terdaftar pada jadwal ini.');
            abort_if(Queue::overlappingActiveFor($data['nik'], $locked, $data['tanggal_daftar']), 409, 'Masyarakat masih memiliki antrean aktif pada jadwal yang bertabrakan.');
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
            abort_unless($schedule->puskesmasService()->where('puskesmas_id', $request->user()->puskesmas_id)->exists(), 403);
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
