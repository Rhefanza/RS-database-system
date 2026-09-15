@extends('layouts.app')

@section('title', 'Tiket antrean Q'.str_pad((string) $queue->queue_number, 3, '0', STR_PAD_LEFT))

@section('content')
<section class="ticket-stage">
    <div class="shell ticket-shell">
        <a class="back-link" href="{{ route('hospitals.show', $queue->queueSession->hospitalService->hospital) }}">← Kembali ke rumah sakit</a>
        <div class="queue-ticket">
            <div class="ticket-heading">
                <div>
                    <p class="eyebrow">Tiket antrean digital</p>
                    <h1>Q{{ str_pad((string) $queue->queue_number, 3, '0', STR_PAD_LEFT) }}</h1>
                </div>
                <span class="queue-status status-{{ strtolower($queue->queue_status) }}">{{ ['WAITING' => 'Menunggu', 'CALLED' => 'Dipanggil', 'SERVING' => 'Dilayani', 'COMPLETED' => 'Selesai', 'CANCELLED' => 'Dibatalkan'][$queue->queue_status] }}</span>
            </div>
            <div class="ticket-provider">
                <strong>{{ $queue->queueSession->hospitalService->hospital->name }}</strong>
                <span>{{ $queue->queueSession->hospitalService->service->name }}</span>
            </div>
            <div class="ticket-metrics">
                <div><small>Antrean di depan</small><strong>{{ $ahead }}</strong></div>
                <div><small>Estimasi tunggu</small><strong>{{ $estimatedWait }} menit</strong></div>
                <div><small>Nomor dipanggil</small><strong>{{ $currentQueue ? 'Q'.str_pad((string) $currentQueue->queue_number, 3, '0', STR_PAD_LEFT) : '—' }}</strong></div>
                <div><small>Loket Anda</small><strong>{{ $queue->serviceDesk?->name ?: 'Belum ditentukan' }}</strong></div>
            </div>
            <p class="ticket-note">Status diperbarui otomatis setiap 10 detik. Jangan menutup atau kehilangan alamat halaman ini.</p>
        </div>
    </div>
</section>
@if (in_array($queue->queue_status, ['WAITING', 'CALLED', 'SERVING'], true))
    <script>setTimeout(() => window.location.reload(), 10000);</script>
@endif
@endsection
