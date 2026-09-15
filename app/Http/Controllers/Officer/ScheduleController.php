<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\PuskesmasService;
use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $relations = PuskesmasService::with('puskesmas', 'service')->where('status', 'AKTIF')
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->where('puskesmas_id', $request->user()->puskesmas_id))
            ->get();
        $schedules = Schedule::with('puskesmasService.puskesmas', 'puskesmasService.service')
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->whereHas('puskesmasService', fn ($relation) => $relation->where('puskesmas_id', $request->user()->puskesmas_id)))
            ->orderBy('hari')->get();

        return view('officer.schedules.index', compact('relations', 'schedules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $this->authorizeRelation($request, (int) $data['puskesmas_layanan_id']);
        Schedule::create($data);

        return back()->with('success', 'Jadwal dan kapasitas ditambahkan.');
    }

    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        $data = $this->data($request, $schedule);
        $this->authorizeRelation($request, (int) $data['puskesmas_layanan_id']);
        $schedule->update($data);

        return back()->with('success', 'Jadwal dan kapasitas diperbarui.');
    }

    public function destroy(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        if ($schedule->queues()->exists()) {
            return back()->with('error', 'Jadwal masih dipakai data antrean. Nonaktifkan saja.');
        }
        $schedule->delete();

        return back()->with('success', 'Jadwal dihapus.');
    }

    private function data(Request $request, ?Schedule $schedule = null): array
    {
        return $request->validate([
            'puskesmas_layanan_id' => ['required', 'exists:puskesmas_layanan,puskesmas_layanan_id'],
            'hari' => ['required', Rule::in(['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU']), Rule::unique('jadwal', 'hari')
                ->where('puskesmas_layanan_id', $request->input('puskesmas_layanan_id'))->ignore($schedule?->jadwal_id, 'jadwal_id')],
            'jam_buka' => ['required', 'date_format:H:i'],
            'jam_tutup' => ['required', 'date_format:H:i', 'after:jam_buka'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }

    private function authorizeRelation(Request $request, int $id): void
    {
        if ($request->user()->role === 'PETUGAS') {
            abort_unless(PuskesmasService::whereKey($id)->where('puskesmas_id', $request->user()->puskesmas_id)->exists(), 403);
        }
    }

    private function authorizeSchedule(Request $request, Schedule $schedule): void
    {
        if ($request->user()->role === 'PETUGAS') {
            abort_unless($schedule->puskesmasService()->where('puskesmas_id', $request->user()->puskesmas_id)->exists(), 403);
        }
    }
}
