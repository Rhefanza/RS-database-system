@extends('layouts.app')

@section('title', $hospital->name)

@section('content')
<section class="detail-stage">
    <div class="shell">
        <a class="back-link" href="{{ route('home') }}">← Kembali ke pencarian</a>
        <div class="detail-grid">
            <div>
                <p class="eyebrow">{{ $hospital->code }} · Rumah sakit kelas {{ $hospital->class }}</p>
                <h1>{{ $hospital->name }}</h1>
                <p class="detail-address">{{ $hospital->address }}, {{ $hospital->city }}</p>
            </div>
            <div class="detail-callout">
                <span class="callout-label">Kontak layanan</span>
                <p>{{ $hospital->is_emergency ? 'Layanan IGD tersedia 24 jam' : 'Hubungi rumah sakit untuk jadwal layanan' }}</p>
                <a href="tel:{{ $hospital->emergency_phone ?: $hospital->phone }}">{{ $hospital->emergency_phone ?: $hospital->phone }}</a>
            </div>
        </div>
    </div>
</section>

<section class="detail-content">
    <div class="shell detail-columns">
        <div class="detail-body">
            <h2>Tentang rumah sakit</h2>
            <p>{{ $hospital->description ?: 'Informasi deskripsi rumah sakit belum tersedia.' }}</p>
            <h2>Fasilitas utama</h2>
            <div class="facility-grid">
                @forelse ($hospital->facilities as $facility)
                    <div><span aria-hidden="true">+</span>{{ $facility->name }}</div>
                @empty
                    <p>Data fasilitas belum ditambahkan.</p>
                @endforelse
            </div>
        </div>
        <aside class="fact-sheet">
            <h2>Informasi ringkas</h2>
            <dl>
                <div><dt>Kelas</dt><dd>{{ $hospital->class }}</dd></div>
                <div><dt>Kepemilikan</dt><dd>{{ $hospital->ownership }}</dd></div>
                <div><dt>Kota</dt><dd>{{ $hospital->city }}</dd></div>
                <div><dt>Telepon</dt><dd>{{ $hospital->phone }}</dd></div>
            </dl>
            <a class="button button-primary button-block" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $hospital->latitude }},{{ $hospital->longitude }}">Buka petunjuk arah</a>
        </aside>
    </div>
</section>
@endsection
