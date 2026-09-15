<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Queue;
use App\Models\Schedule;
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
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->whereHas('puskesmasService', fn ($relation) => $relation->where('puskesmas_id', $request->user()->puskesmas_id)))->get();
        $queues = Queue::with('citizen', 'schedule.puskesmasService.puskesmas', 'schedule.puskesmasService.service')
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->whereHas('schedule.puskesmasService', fn ($relation) => $relation->where('puskesmas_id', $request->user()->puskesmas_id)))
            ->latest('tanggal_daftar')->latest('nomor_antrean')->get();

        return view('officer.queues.index', ['schedules' => $schedules, 'queues' => $queues, 'citizens' => Citizen::where('status_data', 'AKTIF')->orderBy('nama_lengkap')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['nik' => ['required', 'exists:masyarakat,nik'], 'jadwal_id' => ['required', 'exists:jadwal,jadwal_id'], 'tanggal_daftar' => ['required', 'date'], 'status_antrean' => ['required', Rule::in($this->statuses())]]);
        $schedule = Schedule::findOrFail($data['jadwal_id']);
        $this->authorizeQueueScope($request, $schedule);
        DB::transaction(function () use ($data, $schedule) {
            $locked = Schedule::whereKey($schedule->jadwal_id)->lockForUpdate()->firstOrFail();
            $activeCount = $locked->queues()->whereDate('tanggal_daftar', $data['tanggal_daftar'])->where('status_antrean', '!=', 'CANCELLED')->count();
            abort_if($activeCount >= $locked->kapasitas, 409, 'Kapasitas antrean sudah penuh.');
            abort_if($locked->queues()->where('nik', $data['nik'])->whereDate('tanggal_daftar', $data['tanggal_daftar'])->exists(), 409, 'Masyarakat sudah terdaftar pada jadwal ini.');
            $data['nomor_antrean'] = ((int) $locked->queues()->whereDate('tanggal_daftar', $data['tanggal_daftar'])->max('nomor_antrean')) + 1;
            Queue::create($data);
        });

        return back()->with('success', 'Data antrean ditambahkan.');
    }

    public function update(Request $request, Queue $queue): RedirectResponse
    {
        $this->authorizeQueueScope($request, $queue->schedule);
        $queue->update($request->validate(['status_antrean' => ['required', Rule::in($this->statuses())]]));

        return back()->with('success', 'Status antrean diperbarui.');
    }

    public function destroy(Request $request, Queue $queue): RedirectResponse
    {
        $this->authorizeQueueScope($request, $queue->schedule);
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
}
