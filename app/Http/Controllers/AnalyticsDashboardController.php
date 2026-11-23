<?php

namespace App\Http\Controllers;

use App\Support\WarehouseAnalytics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Dashboard analitik data warehouse.
 *
 * - ADMIN (Dinas): melihat semua kecamatan dan dapat drill-down ke puskesmas.
 * - PETUGAS: otomatis dikunci ke puskesmas tempatnya bertugas.
 *
 * Halaman memuat kerangka Blade; data chart diambil dari endpoint JSON sehingga filter
 * dapat diganti tanpa memuat ulang halaman.
 */
class AnalyticsDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $isOfficer = $request->user()->role === 'PETUGAS';

        try {
            $meta = $this->analytics($request, [])->meta();
            $error = null;
        } catch (Throwable $exception) {
            report($exception);
            $meta = null;
            $error = $exception->getMessage();
        }

        return view('analytics.index', [
            'meta' => $meta,
            'dwError' => $error,
            'isOfficer' => $isOfficer,
            'dataUrl' => route($isOfficer ? 'officer.analytics.data' : 'admin.analytics.data'),
            'initialFilters' => $request->only(['tahun', 'bulan', 'kecamatan', 'layanan']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'layanan' => ['nullable', 'integer'],
        ]);

        try {
            return response()->json($this->analytics($request, $filters)->payload());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Data warehouse belum dapat dibaca. Jalankan php artisan dw:etl --full.'], 503);
        }
    }

    private function analytics(Request $request, array $filters): WarehouseAnalytics
    {
        $user = $request->user();

        return new WarehouseAnalytics($filters, $user->role === 'PETUGAS' ? (int) $user->puskesmas_id : null);
    }
}
