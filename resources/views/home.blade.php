@extends('layouts.app')

@section('title', 'Temukan rumah sakit yang sesuai')

@section('content')
<section class="search-stage">
    <div class="shell stage-grid">
        <div class="stage-copy">
            <p class="eyebrow">Direktori layanan kesehatan</p>
            <h1>Rumah sakit yang tepat, tanpa pencarian yang rumit.</h1>
            <p class="lede">Bandingkan kelas, kepemilikan, fasilitas, dan perkiraan jarak dari posisi Anda.</p>
        </div>
        <form class="search-panel" method="get" action="{{ route('home') }}">
            <label class="search-main">
                <span>Nama atau lokasi</span>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Contoh: Surabaya atau Dr. Soetomo">
            </label>
            <div class="search-options">
                <label>
                    <span>Kelas</span>
                    <select name="class">
                        <option value="">Semua kelas</option>
                        @foreach (['A', 'B', 'C', 'D'] as $class)
                            <option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>Kelas {{ $class }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Kepemilikan</span>
                    <select name="ownership">
                        <option value="">Semua pengelola</option>
                        @foreach (['Pemerintah', 'BUMN', 'Swasta'] as $ownership)
                            <option value="{{ $ownership }}" @selected(($filters['ownership'] ?? '') === $ownership)>{{ $ownership }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="search-actions">
                <button class="button button-primary" type="submit">Tampilkan hasil</button>
                <button class="button button-quiet" type="button" data-use-location>Urutkan dari lokasi saya</button>
            </div>
            <p class="location-note" data-location-status>Lokasi hanya digunakan di perangkat Anda dan tidak disimpan.</p>
        </form>
    </div>
</section>

<section class="service-ribbon" aria-label="Layanan utama">
    <div class="shell service-grid">
        <a href="#hasil-rumah-sakit" class="service-card">
            <span class="service-icon" aria-hidden="true">⌕</span>
            <span><small>Direktori terpadu</small><strong>Cari rumah sakit</strong><em>Temukan pilihan berdasarkan kota dan kelas</em></span>
            <b aria-hidden="true">→</b>
        </a>
        <button type="button" class="service-card" data-use-location>
            <span class="service-icon" aria-hidden="true">◎</span>
            <span><small>Berdasarkan posisi</small><strong>Rumah sakit terdekat</strong><em>Urutkan hasil menggunakan lokasi perangkat</em></span>
            <b aria-hidden="true">→</b>
        </button>
    </div>
</section>

<section class="results-section" id="hasil-rumah-sakit">
    <div class="shell">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Hasil pencarian</p>
                <h2><span>{{ $items->count() }}</span> rumah sakit ditemukan</h2>
            </div>
            @if (array_filter($filters))
                <a class="text-link" href="{{ route('home') }}">Hapus semua filter</a>
            @endif
        </div>

        @forelse ($items as $hospital)
            @if ($loop->first)<div class="hospital-list" data-hospital-list>@endif
            <article class="hospital-row" data-hospital data-lat="{{ $hospital->latitude }}" data-lng="{{ $hospital->longitude }}">
                <div class="result-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                <div class="hospital-main">
                    <div class="hospital-title-line">
                        <h3><a href="{{ route('hospitals.show', $hospital) }}">{{ $hospital->name }}</a></h3>
                        @if ($hospital->is_emergency)<span class="status-dot"><i></i> IGD 24 jam</span>@endif
                    </div>
                    <p>{{ $hospital->address }}, {{ $hospital->city }}</p>
                    <div class="facility-line">
                        @foreach ($hospital->facilities->take(4) as $facility)<span>{{ $facility->name }}</span>@endforeach
                        @if ($hospital->facilities->count() > 4)<span>+{{ $hospital->facilities->count() - 4 }} fasilitas</span>@endif
                    </div>
                </div>
                <div class="hospital-meta">
                    <div><small>Kelas</small><strong>{{ $hospital->class }}</strong></div>
                    <div><small>Pengelola</small><strong>{{ $hospital->ownership }}</strong></div>
                    <div class="distance" data-distance hidden><small>Jarak garis lurus</small><strong>—</strong></div>
                </div>
                <a class="row-arrow" href="{{ route('hospitals.show', $hospital) }}" aria-label="Lihat {{ $hospital->name }}">↗</a>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">
                <span class="empty-index">00</span>
                <div><h3>Belum ada hasil yang cocok</h3><p>Coba gunakan nama kota atau hapus filter kelas.</p></div>
            </div>
        @endforelse
    </div>
</section>
@endsection
