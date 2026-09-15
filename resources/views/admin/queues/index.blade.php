@extends('layouts.app')

@section('title', 'Operasional antrean')

@section('content')
<section class="admin-heading queue-admin-heading">
    <div class="shell heading-row">
        <div>
            <p class="eyebrow">Operasional harian</p>
            <h1>Antrean layanan</h1>
            <p>Buka sesi, panggil nomor, dan pantau pelayanan hari ini.</p>
        </div>
        <span class="today-chip">{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>
</section>

<section class="shell queue-admin-content">
    <div class="queue-setup-grid">
        <form class="setup-card" method="post" action="{{ route('admin.queues.sessions.store') }}">
            @csrf
            <p class="eyebrow">Sesi baru</p>
            <h2>Buka antrean</h2>
            <label><span>Rumah sakit & layanan</span>
                <select name="hospital_service_id" required>
                    <option value="">Pilih layanan</option>
                    @foreach ($hospitalServices as $hospitalService)
                        <option value="{{ $hospitalService->id }}">{{ $hospitalService->hospital->name }} — {{ $hospitalService->service->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-primary" type="submit">Buka sesi hari ini</button>
        </form>

        <form class="setup-card" method="post" action="{{ route('admin.queues.desks.store') }}">
            @csrf
            <p class="eyebrow">Konfigurasi</p>
            <h2>Tambah loket</h2>
            <label><span>Rumah sakit & layanan</span>
                <select name="hospital_service_id" required>
                    <option value="">Pilih layanan</option>
                    @foreach ($hospitalServices as $hospitalService)
                        <option value="{{ $hospitalService->id }}">{{ $hospitalService->hospital->name }} — {{ $hospitalService->service->name }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Nama loket</span><input name="name" placeholder="Contoh: Loket 2" required></label>
            <button class="button button-quiet" type="submit">Tambahkan loket</button>
        </form>
    </div>

    <div class="queue-section-heading">
        <div><p class="eyebrow">Sesi hari ini</p><h2>{{ $sessions->count() }} sesi layanan</h2></div>
        <span>Halaman dapat dimuat ulang untuk melihat nomor baru.</span>
    </div>

    <div class="queue-session-list">
        @forelse ($sessions as $session)
            @php
                $waiting = $session->queues->where('queue_status', 'WAITING');
                $active = $session->queues->whereIn('queue_status', ['CALLED', 'SERVING']);
                $completed = $session->queues->where('queue_status', 'COMPLETED');
                $activeDesks = $session->hospitalService->desks->where('desk_status', 'ACTIVE');
                $busyDeskIds = $active->pluck('service_desk_id')->filter();
                $availableDesks = $activeDesks->whereNotIn('id', $busyDeskIds);
            @endphp
            <article class="queue-session-card">
                <header>
                    <div>
                        <span>{{ $session->hospitalService->hospital->name }}</span>
                        <h3>{{ $session->hospitalService->service->name }}</h3>
                    </div>
                    <span class="queue-status status-{{ strtolower($session->session_status) }}">{{ $session->session_status }}</span>
                </header>
                <div class="session-metrics">
                    <div><small>Menunggu</small><strong>{{ $waiting->count() }}</strong></div>
                    <div><small>Aktif</small><strong>{{ $active->count() }}</strong></div>
                    <div><small>Selesai</small><strong>{{ $completed->count() }}</strong></div>
                    <div><small>Loket</small><strong>{{ $activeDesks->count() }}</strong></div>
                </div>

                @if ($session->session_status === 'OPEN')
                    <div class="session-controls">
                        <form method="post" action="{{ route('admin.queues.call-next', $session) }}">
                            @csrf
                            <select name="service_desk_id" required>
                                <option value="">Pilih loket</option>
                                @foreach ($activeDesks as $desk)
                                    <option value="{{ $desk->id }}" @disabled($busyDeskIds->contains($desk->id))>{{ $desk->name }}{{ $busyDeskIds->contains($desk->id) ? ' — sibuk' : '' }}</option>
                                @endforeach
                            </select>
                            <button class="button button-primary" type="submit" @disabled($waiting->isEmpty() || $availableDesks->isEmpty())>Panggil berikutnya</button>
                        </form>
                        <form method="post" action="{{ route('admin.queues.sessions.close', $session) }}">
                            @csrf
                            <button class="button button-quiet" type="submit">Tutup sesi</button>
                        </form>
                    </div>
                @endif

                <div class="queue-table-wrap">
                    <table class="queue-table">
                        <thead><tr><th>Nomor</th><th>Status</th><th>Loket</th><th>Waktu</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @forelse ($session->queues as $queue)
                            <tr>
                                <td><strong>Q{{ str_pad((string) $queue->queue_number, 3, '0', STR_PAD_LEFT) }}</strong></td>
                                <td><span class="queue-status status-{{ strtolower($queue->queue_status) }}">{{ $queue->queue_status }}</span></td>
                                <td>{{ $queue->serviceDesk?->name ?: '—' }}</td>
                                <td>{{ $queue->created_at->format('H:i') }}</td>
                                <td class="queue-actions">
                                    @if ($queue->queue_status === 'CALLED')
                                        <form method="post" action="{{ route('admin.queues.start', $queue) }}">@csrf @method('PATCH')<button type="submit">Mulai</button></form>
                                    @endif
                                    @if ($queue->queue_status === 'SERVING')
                                        <form method="post" action="{{ route('admin.queues.complete', $queue) }}">@csrf @method('PATCH')<button type="submit">Selesai</button></form>
                                    @endif
                                    @if (in_array($queue->queue_status, ['WAITING', 'CALLED'], true))
                                        <form method="post" action="{{ route('admin.queues.cancel', $queue) }}">@csrf @method('PATCH')<button class="danger-link" type="submit">Batal</button></form>
                                    @endif
                                    @if (in_array($queue->queue_status, ['COMPLETED', 'CANCELLED'], true))<span>—</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">Belum ada nomor antrean pada sesi ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="empty-state"><span class="empty-index">00</span><div><h3>Belum ada sesi hari ini</h3><p>Buka sesi dari formulir di atas untuk mulai menerima antrean.</p></div></div>
        @endforelse
    </div>
</section>
@endsection
