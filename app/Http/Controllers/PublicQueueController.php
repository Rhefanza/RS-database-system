<?php

namespace App\Http\Controllers;

use App\Models\HospitalService;
use App\Models\Queue;
use App\Models\QueueSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicQueueController extends Controller
{
    public function store(HospitalService $hospitalService): RedirectResponse
    {
        $queue = DB::transaction(function () use ($hospitalService) {
            $session = QueueSession::query()
                ->where('hospital_service_id', $hospitalService->id)
                ->where('session_status', 'OPEN')
                ->whereDate('session_date', today())
                ->lockForUpdate()
                ->first();

            abort_unless($session && $hospitalService->availability_status === 'ACTIVE', 409, 'Antrean layanan belum dibuka.');

            $nextNumber = ((int) $session->queues()->max('queue_number')) + 1;

            return $session->queues()->create([
                'public_token' => (string) Str::uuid(),
                'queue_number' => $nextNumber,
                'queue_status' => 'WAITING',
            ]);
        });

        return redirect()->route('queues.show', $queue->public_token)
            ->with('success', 'Nomor antrean berhasil dibuat. Simpan halaman tiket ini.');
    }

    public function show(string $token): View
    {
        $queue = Queue::query()
            ->where('public_token', $token)
            ->with(['serviceDesk', 'queueSession.hospitalService.hospital', 'queueSession.hospitalService.service'])
            ->firstOrFail();

        $ahead = Queue::query()
            ->where('queue_session_id', $queue->queue_session_id)
            ->where('queue_number', '<', $queue->queue_number)
            ->whereIn('queue_status', ['WAITING', 'CALLED', 'SERVING'])
            ->count();
        $activeDesks = $queue->queueSession->hospitalService->desks()->where('desk_status', 'ACTIVE')->count();
        $duration = $queue->queueSession->hospitalService->initial_service_duration ?? 15;
        $estimatedWait = (int) ceil(($ahead * $duration) / max($activeDesks, 1));
        $currentQueue = $queue->queueSession->queues()
            ->whereIn('queue_status', ['CALLED', 'SERVING'])
            ->orderByDesc('called_at')
            ->first();

        return view('queues.show', compact('queue', 'ahead', 'estimatedWait', 'currentQueue'));
    }
}
