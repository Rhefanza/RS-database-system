<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalService;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueSnapshot;
use App\Models\ServiceDesk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QueueController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $hospitalIds = $this->manageableHospitalIds($user);
        $hospitalServices = HospitalService::query()
            ->with(['hospital:id,name', 'service:id,name', 'desks'])
            ->where('availability_status', 'ACTIVE')
            ->when($user->role === 'OFFICER', fn ($query) => $query->whereIn('hospital_id', $hospitalIds))
            ->get()
            ->sortBy(fn ($item) => $item->hospital->name.' '.$item->service->name)
            ->values();

        $sessions = QueueSession::query()
            ->with([
                'hospitalService.hospital:id,name',
                'hospitalService.service:id,name',
                'hospitalService.desks',
                'queues' => fn ($query) => $query->with('serviceDesk')->orderBy('queue_number'),
            ])
            ->whereDate('session_date', today())
            ->when($user->role === 'OFFICER', fn ($query) => $query->whereHas('hospitalService', fn ($serviceQuery) => $serviceQuery->whereIn('hospital_id', $hospitalIds)))
            ->orderByDesc('started_at')
            ->get();

        return view('admin.queues.index', compact('hospitalServices', 'sessions'));
    }

    public function storeDesk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hospital_service_id' => ['required', 'exists:hospital_services,id'],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('service_desks')->where('hospital_service_id', $request->input('hospital_service_id')),
            ],
        ]);
        $this->ensureCanManage(HospitalService::findOrFail($validated['hospital_service_id']));
        ServiceDesk::create($validated);

        return back()->with('success', 'Loket layanan berhasil ditambahkan.');
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $validated = $request->validate(['hospital_service_id' => ['required', 'exists:hospital_services,id']]);
        $this->ensureCanManage(HospitalService::findOrFail($validated['hospital_service_id']));
        $exists = QueueSession::query()
            ->where('hospital_service_id', $validated['hospital_service_id'])
            ->whereDate('session_date', today())
            ->where('session_status', 'OPEN')
            ->exists();

        if ($exists) {
            return back()->withErrors(['hospital_service_id' => 'Sesi aktif untuk layanan ini sudah tersedia.']);
        }

        QueueSession::create([
            'hospital_service_id' => $validated['hospital_service_id'],
            'opened_by_user_id' => $request->user()->id,
            'session_date' => today(),
            'started_at' => now(),
            'session_status' => 'OPEN',
        ]);

        return back()->with('success', 'Sesi antrean berhasil dibuka.');
    }

    public function callNext(Request $request, QueueSession $queueSession): RedirectResponse
    {
        $this->ensureCanManage($queueSession->hospitalService);
        $validated = $request->validate([
            'service_desk_id' => [
                'required',
                Rule::exists('service_desks', 'id')
                    ->where('hospital_service_id', $queueSession->hospital_service_id)
                    ->where('desk_status', 'ACTIVE'),
            ],
        ]);

        $deskIsBusy = $queueSession->queues()
            ->where('service_desk_id', $validated['service_desk_id'])
            ->whereIn('queue_status', ['CALLED', 'SERVING'])
            ->exists();
        if ($deskIsBusy) {
            return back()->withErrors(['service_desk_id' => 'Loket masih menangani antrean aktif.']);
        }

        $queue = DB::transaction(function () use ($queueSession, $validated) {
            abort_unless($queueSession->session_status === 'OPEN', 409);
            $queue = $queueSession->queues()->where('queue_status', 'WAITING')->orderBy('queue_number')->lockForUpdate()->first();
            if ($queue) {
                $queue->update(['queue_status' => 'CALLED', 'service_desk_id' => $validated['service_desk_id'], 'called_at' => now()]);
            }

            return $queue;
        });

        if (! $queue) {
            return back()->withErrors(['queue' => 'Tidak ada antrean yang sedang menunggu.']);
        }

        $this->captureSnapshot($queueSession);

        return back()->with('success', 'Antrean Q'.str_pad((string) $queue->queue_number, 3, '0', STR_PAD_LEFT).' telah dipanggil.');
    }

    public function start(Queue $queue): RedirectResponse
    {
        $this->ensureCanManage($queue->queueSession->hospitalService);
        abort_unless($queue->queue_status === 'CALLED', 409);
        $queue->update(['queue_status' => 'SERVING', 'service_started_at' => now()]);
        $this->captureSnapshot($queue->queueSession);

        return back()->with('success', 'Pelayanan antrean dimulai.');
    }

    public function complete(Queue $queue): RedirectResponse
    {
        $this->ensureCanManage($queue->queueSession->hospitalService);
        abort_unless($queue->queue_status === 'SERVING', 409);
        $queue->update(['queue_status' => 'COMPLETED', 'service_ended_at' => now()]);
        $this->captureSnapshot($queue->queueSession);

        return back()->with('success', 'Antrean selesai dilayani.');
    }

    public function cancel(Queue $queue): RedirectResponse
    {
        $this->ensureCanManage($queue->queueSession->hospitalService);
        abort_unless(in_array($queue->queue_status, ['WAITING', 'CALLED'], true), 409);
        $queue->update(['queue_status' => 'CANCELLED', 'service_ended_at' => now()]);
        $this->captureSnapshot($queue->queueSession);

        return back()->with('success', 'Antrean dibatalkan.');
    }

    public function close(Request $request, QueueSession $queueSession): RedirectResponse
    {
        $this->ensureCanManage($queueSession->hospitalService);
        if ($queueSession->queues()->whereIn('queue_status', ['WAITING', 'CALLED', 'SERVING'])->exists()) {
            return back()->withErrors(['session' => 'Selesaikan atau batalkan seluruh antrean aktif sebelum menutup sesi.']);
        }

        $queueSession->update([
            'session_status' => 'CLOSED',
            'ended_at' => now(),
            'closed_by_user_id' => $request->user()->id,
        ]);
        $this->captureSnapshot($queueSession);

        return back()->with('success', 'Sesi antrean berhasil ditutup.');
    }

    private function captureSnapshot(QueueSession $queueSession): void
    {
        $counts = $queueSession->queues()
            ->selectRaw('queue_status, count(*) as total')
            ->groupBy('queue_status')
            ->pluck('total', 'queue_status');
        $service = $queueSession->hospitalService;
        $activeDesks = $service->desks()->where('desk_status', 'ACTIVE')->count();
        $duration = $service->initial_service_duration ?? 15;

        QueueSnapshot::create([
            'hospital_service_id' => $queueSession->hospital_service_id,
            'captured_at' => now(),
            'waiting_count' => $counts->get('WAITING', 0),
            'serving_count' => $counts->get('SERVING', 0) + $counts->get('CALLED', 0),
            'completed_count' => $counts->get('COMPLETED', 0),
            'active_desk_count' => $activeDesks,
            'estimated_wait_minutes' => (int) ceil(($counts->get('WAITING', 0) * $duration) / max($activeDesks, 1)),
        ]);
    }

    private function manageableHospitalIds($user)
    {
        if ($user->role === 'ADMIN') {
            return collect();
        }

        return $user->staffAssignments()
            ->where('assignment_status', 'ACTIVE')
            ->whereDate('starts_on', '<=', today())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->pluck('hospital_id');
    }

    private function ensureCanManage(HospitalService $hospitalService): void
    {
        $user = request()->user();
        if ($user->role === 'ADMIN') {
            return;
        }

        abort_unless($this->manageableHospitalIds($user)->contains($hospitalService->hospital_id), 403);
    }
}
