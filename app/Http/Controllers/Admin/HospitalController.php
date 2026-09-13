<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HospitalRequest;
use App\Models\District;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HospitalController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $items = Hospital::query()
            ->with('district:id,name')
            ->when($query, function ($builder, string $search) {
                $builder->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => Hospital::count(),
            'emergency' => Hospital::where('is_emergency', true)->count(),
            'cities' => Hospital::distinct()->count('city'),
            'classes' => Hospital::distinct()->count('class'),
        ];

        return view('admin.hospitals.index', compact('items', 'stats', 'query'));
    }

    public function create(): View
    {
        return view('admin.hospitals.form', [
            'hospital' => new Hospital,
            'facilities' => Facility::orderBy('name')->get(),
            'selectedFacilities' => [],
            'districts' => District::orderBy('name')->get(),
            'services' => Service::orderBy('name')->get(),
            'selectedServices' => [],
        ]);
    }

    public function store(HospitalRequest $request): RedirectResponse
    {
        $hospital = DB::transaction(function () use ($request) {
            $hospital = Hospital::create($request->safe()->except(['facilities', 'services']));
            $hospital->facilities()->sync($request->validated('facilities', []));
            $hospital->services()->sync($request->validated('services', []));

            return $hospital;
        });

        return redirect()->route('admin.hospitals.edit', $hospital)
            ->with('success', 'Data rumah sakit berhasil ditambahkan.');
    }

    public function edit(Hospital $hospital): View
    {
        return view('admin.hospitals.form', [
            'hospital' => $hospital,
            'facilities' => Facility::orderBy('name')->get(),
            'selectedFacilities' => $hospital->facilities()->pluck('facilities.id')->all(),
            'districts' => District::orderBy('name')->get(),
            'services' => Service::orderBy('name')->get(),
            'selectedServices' => $hospital->services()->pluck('services.id')->all(),
        ]);
    }

    public function update(HospitalRequest $request, Hospital $hospital): RedirectResponse
    {
        DB::transaction(function () use ($request, $hospital) {
            $hospital->update($request->safe()->except(['facilities', 'services']));
            $hospital->facilities()->sync($request->validated('facilities', []));
            $hospital->services()->sync($request->validated('services', []));
        });

        return back()->with('success', 'Perubahan berhasil disimpan.');
    }

    public function destroy(Hospital $hospital): RedirectResponse
    {
        $hospital->delete();

        return redirect()->route('admin.index')
            ->with('success', 'Data rumah sakit berhasil dihapus.');
    }
}
