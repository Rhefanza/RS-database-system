<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::where('status', 'AKTIF')->orderBy('nama_layanan')->get();
        $schedules = Schedule::with('puskesmas', 'service')
            ->when($request->user()->role === 'PETUGAS', fn ($query) => $query->where('puskesmas_id', $request->user()->puskesmas_id))
            ->orderBy('hari')->get();

        return view('officer.schedules.index', compact('services', 'schedules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        Schedule::create($data);

        return back()->with('success', 'Jadwal dan kapasitas ditambahkan.');
    }

    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        $data = $this->data($request, $schedule);
        DB::transaction(function () use ($schedule, $data): void {
            $locked = Schedule::whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
            $activeMaximum = (int) ($locked->queues()
                ->active()
                ->selectRaw('tanggal_daftar, COUNT(*) as total')
                ->groupBy('tanggal_daftar')
                ->pluck('total')
                ->max() ?? 0);
            abort_if((int) $data['kapasitas'] < $activeMaximum, 409, "Kapasitas tidak boleh lebih kecil dari {$activeMaximum} antrean aktif yang sudah terdaftar.");
            if ($locked->queues()->active()->exists()) {
                foreach (['layanan_id', 'hari', 'jam_buka', 'jam_tutup'] as $field) {
                    $current = in_array($field, ['jam_buka', 'jam_tutup']) ? substr($locked->$field, 0, 5) : (string) $locked->$field;
                    abort_if($current !== (string) $data[$field], 409, 'Selesaikan antrean aktif sebelum mengubah layanan atau waktu jadwal.');
                }
            }
            $locked->update($data);
        });

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
        if ($request->filled('puskesmas_id')) {
            abort_unless((int) $request->input('puskesmas_id') === (int) $request->user()->puskesmas_id, 403);
        }
        $validator = Validator::make($request->all(), [
            'layanan_id' => ['required', Rule::exists('layanan', 'layanan_id')->where('status', 'AKTIF')],
            'nama_dokter' => ['nullable', 'string', 'max:1000'],
            'spesialisasi' => ['nullable', 'string', 'max:1000'],
            'hari' => ['required', Rule::in(['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU']), Rule::unique('jadwal', 'hari')
                ->where('puskesmas_id', $request->user()->puskesmas_id)->where('layanan_id', $request->input('layanan_id'))->ignore($schedule?->jadwal_id, 'jadwal_id')],
            'jam_buka' => ['required', 'date_format:H:i'],
            'jam_tutup' => ['required', 'date_format:H:i', 'after:jam_buka'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ], [
            'hari.unique' => 'Layanan ini sudah memiliki jadwal pada hari tersebut sehingga jadwal tidak boleh bertabrakan.',
            'jam_tutup.after' => 'Jam tutup harus lebih akhir daripada jam buka.',
        ]);

        $validator->after(function ($validator) use ($schedule, $request): void {
            if (! $schedule || ! $request->filled('kapasitas')) {
                return;
            }

            $largestActiveQueue = (int) ($schedule->queues()
                ->active()
                ->selectRaw('tanggal_daftar, COUNT(*) as total')
                ->groupBy('tanggal_daftar')
                ->pluck('total')
                ->max() ?? 0);

            if ((int) $request->input('kapasitas') < $largestActiveQueue) {
                $validator->errors()->add('kapasitas', "Kapasitas tidak boleh lebih kecil dari {$largestActiveQueue} antrean aktif yang sudah terdaftar.");
            }
        });

        return $validator->validate() + ['puskesmas_id' => $request->user()->puskesmas_id];
    }

    private function authorizeSchedule(Request $request, Schedule $schedule): void
    {
        if ($request->user()->role === 'PETUGAS') {
            abort_unless((int) $schedule->puskesmas_id === (int) $request->user()->puskesmas_id, 403);
        }
    }
}
