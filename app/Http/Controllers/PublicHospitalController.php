<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicHospitalController extends Controller
{
    public function index(Request $request): View
    {
        $requestedClass = (string) $request->query('class', '');
        $requestedOwnership = (string) $request->query('ownership', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'class' => in_array($requestedClass, ['A', 'B', 'C', 'D'], true) ? $requestedClass : '',
            'ownership' => in_array($requestedOwnership, ['Pemerintah', 'BUMN', 'Swasta'], true)
                ? $requestedOwnership
                : '',
        ];

        $items = Hospital::query()
            ->with(['district:id,name', 'facilities:id,name', 'services:id,name'])
            ->when($filters['q'] ?? null, function ($query, string $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('district', fn ($district) => $district->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('services', fn ($service) => $service->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['class'] ?? null, fn ($query, string $class) => $query->where('class', $class))
            ->when($filters['ownership'] ?? null, fn ($query, string $ownership) => $query->where('ownership', $ownership))
            ->orderByDesc('is_emergency')
            ->orderBy('name')
            ->get();

        return view('home', compact('items', 'filters'));
    }

    public function show(Hospital $hospital): View
    {
        $hospital->load([
            'district:id,name',
            'facilities:id,name',
            'services:id,name',
            'hospitalServices' => fn ($query) => $query
                ->where('availability_status', 'ACTIVE')
                ->with([
                    'service:id,name',
                    'desks' => fn ($desks) => $desks->where('desk_status', 'ACTIVE'),
                    'queueSessions' => fn ($sessions) => $sessions
                        ->whereDate('session_date', today())
                        ->where('session_status', 'OPEN')
                        ->withCount([
                            'queues as waiting_count' => fn ($queues) => $queues->where('queue_status', 'WAITING'),
                            'queues as active_count' => fn ($queues) => $queues->whereIn('queue_status', ['CALLED', 'SERVING']),
                        ]),
                ]),
        ]);

        return view('hospital-detail', compact('hospital'));
    }
}
