@extends('layouts.app')
@section('title', 'Dashboard Analitik')
@push('head')
    <script src="{{ asset('assets/vendor/gpt-vis.min.js') }}" defer></script>
@endpush
@section('content')
@php
    $dwName = config('database.connections.dw.database');
    $coverage = $meta['coverage'] ?? null;
    $fmtDate = fn ($date) => \Carbon\Carbon::parse($date)->translatedFormat('d M Y');
@endphp
<div class="page-title">
    <div>
        <p class="eyebrow">{{ $isOfficer ? 'Petugas / '.($meta['puskesmas'] ?? 'Puskesmas') : 'Admin / Dinas Kesehatan' }}</p>
        <h1>Dashboard analitik</h1>
        <p>
            @if ($isOfficer)
                Analisis antrean khusus puskesmas tempat Anda bertugas.
            @else
                Analisis antrean seluruh kecamatan di Surabaya.
            @endif
            Data dibaca dari data warehouse <code>{{ $dwName }}</code> (star schema) yang diperbarui oleh proses ETL.
        </p>
    </div>
    @if ($coverage && $coverage['total'] > 0)
        <span class="count">{{ number_format($coverage['total'], 0, ',', '.') }} fakta · {{ $fmtDate($coverage['awal']) }} – {{ $fmtDate($coverage['akhir']) }}</span>
    @endif
</div>

@if ($dwError)
    <section class="panel analytics-notice">
        <h2>Data warehouse belum dapat dibaca</h2>
        <p>Pastikan database <code>{{ $dwName }}</code> ada di server MySQL yang sama, lalu jalankan ETL dari terminal proyek:</p>
        <pre><code>php artisan dw:etl --full</code></pre>
        <p class="analytics-muted">Detail teknis: {{ \Illuminate\Support\Str::limit($dwError, 240) }}</p>
    </section>
@elseif (! $coverage || $coverage['total'] === 0)
    <section class="panel analytics-notice">
        <h2>Data warehouse masih kosong</h2>
        <p>{{ $isOfficer ? 'Belum ada fakta antrean untuk puskesmas ini.' : 'Tabel sudah tersedia, tetapi belum ada fakta antrean.' }} Jalankan ETL untuk memuat data dari database aplikasi:</p>
        <pre><code>php artisan dw:etl --full</code></pre>
    </section>
@else
    <form class="panel analytics-filter" data-analytics-filter action="{{ url()->current() }}" method="get" aria-label="Filter analitik">
        <label>Tahun<select name="tahun"><option value="">Semua tahun</option>@foreach ($meta['options']['tahun'] as $year)<option value="{{ $year }}" @selected((string) ($initialFilters['tahun'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></label>
        <label>Bulan<select name="bulan"><option value="">Semua bulan</option>@foreach ($meta['options']['bulan'] as $month)<option value="{{ $month['value'] }}" @selected((string) ($initialFilters['bulan'] ?? '') === (string) $month['value'])>{{ $month['label'] }}</option>@endforeach</select></label>
        @unless ($isOfficer)
            <label>Kecamatan<select name="kecamatan"><option value="">Semua kecamatan</option>@foreach ($meta['options']['kecamatan'] as $name)<option value="{{ $name }}" @selected(($initialFilters['kecamatan'] ?? '') === $name)>{{ $name }}</option>@endforeach</select></label>
        @endunless
        <label>Layanan<select name="layanan"><option value="">Semua layanan</option>@foreach ($meta['options']['layanan'] as $service)<option value="{{ $service['value'] }}" @selected((string) ($initialFilters['layanan'] ?? '') === (string) $service['value'])>{{ $service['label'] }}</option>@endforeach</select></label>
        <div class="analytics-filter-actions">
            <button class="button primary" type="submit">Terapkan</button>
            <button class="button ghost" type="button" data-analytics-reset>Reset</button>
        </div>
        <p class="analytics-muted analytics-filter-note" data-analytics-status aria-live="polite">Satu filter = <strong>slice</strong>; beberapa filter sekaligus = <strong>dice</strong>.</p>
    </form>

    <div data-analytics-body>
        <section class="analytics-kpis" aria-label="Ringkasan">
            <article class="analytics-kpi"><span>Total antrean</span><strong data-kpi="total">–</strong></article>
            <article class="analytics-kpi"><span>Pasien unik</span><strong data-kpi="pasien">–</strong></article>
            <article class="analytics-kpi analytics-kpi-liquid"><span>Selesai dilayani</span><div class="analytics-liquid" data-chart="liquid"></div></article>
            <article class="analytics-kpi"><span>Dibatalkan</span><strong data-kpi="persen_batal">–</strong></article>
            <article class="analytics-kpi"><span>Utilisasi kapasitas</span><strong data-kpi="persen_utilisasi">–</strong></article>
        </section>

        <section class="panel analytics-notice" data-analytics-empty hidden><h2>Tidak ada data untuk filter ini</h2><p>Ubah tahun, bulan, atau dimensi yang dipilih.</p></section>

        <div data-analytics-charts>
            <section class="panel analytics-card analytics-wide">
                <header><div><p class="eyebrow">Roll-up · <span data-trend-granularity>per hari</span></p><h2>Tren antrean per status</h2></div></header>
                <div class="analytics-chart analytics-chart-tall" data-chart="trend"></div>
            </section>

            @unless ($isOfficer)
                <section class="panel analytics-card analytics-wide">
                    <header>
                        <div>
                            <p class="eyebrow">Drill-down · <span data-region-level>kecamatan</span></p>
                            <h2 data-region-title>Antrean per kecamatan</h2>
                            <p class="analytics-muted" data-region-hint>Pilih kecamatan di bawah grafik untuk melihat puskesmas di dalamnya.</p>
                        </div>
                        <button class="button ghost" type="button" data-region-up hidden>← Semua kecamatan</button>
                    </header>
                    <div class="analytics-chart" data-chart="region"></div>
                    <div class="analytics-drill" data-region-drill aria-label="Drill-down kecamatan"></div>
                </section>
            @endunless

            <div class="analytics-grid">
                <section class="panel analytics-card">
                    <header><div><p class="eyebrow">Slice · layanan</p><h2>Antrean per layanan</h2><p class="analytics-muted">Bertumpuk menurut status akhir.</p></div></header>
                    <div class="analytics-chart" data-chart="services"></div>
                </section>
                <section class="panel analytics-card">
                    <header><div><p class="eyebrow">Kapasitas</p><h2>Kapasitas vs utilisasi</h2><p class="analytics-muted">Batang = kapasitas slot jadwal, garis = % terisi (tanpa yang dibatalkan).</p></div></header>
                    <div class="analytics-chart" data-chart="utilization"></div>
                </section>
                <section class="panel analytics-card">
                    <header><div><p class="eyebrow">Pivot · hari × sesi</p><h2>Hari dan sesi terpadat</h2></div></header>
                    <div class="analytics-chart" data-chart="daySession"></div>
                </section>
                <section class="panel analytics-card">
                    <header><div><p class="eyebrow">Komposisi</p><h2>Status antrean</h2></div></header>
                    <div class="analytics-chart" data-chart="statuses"></div>
                </section>
            </div>

            <section class="panel analytics-card analytics-wide">
                <header><div><p class="eyebrow">Peringkat</p><h2>{{ $isOfficer ? 'Jadwal' : 'Puskesmas' }} utilisasi tertinggi</h2></div></header>
                <div class="analytics-table-chart" data-chart="rankingHigh"></div>
            </section>
            <section class="panel analytics-card analytics-wide">
                <header><div><p class="eyebrow">Peringkat</p><h2>{{ $isOfficer ? 'Jadwal' : 'Puskesmas' }} utilisasi terendah</h2></div></header>
                <div class="analytics-table-chart" data-chart="rankingLow"></div>
            </section>

            <section class="panel analytics-card analytics-notes">
                <h2>Catatan membaca data</h2>
                <ul>
                    <li>Utilisasi = antrean yang tidak dibatalkan ÷ kapasitas slot jadwal (jadwal × tanggal) yang memiliki antrean. Kapasitas diambil sekali per slot, bukan dijumlah per antrean.</li>
                    <li>Data warehouse hanya menyimpan status akhir antrean, sehingga dashboard tidak menampilkan alur pemanggilan maupun rata-rata waktu tunggu.</li>
                    <li>Status aktif (WAITING/CALLED/SERVING) pada tanggal yang sudah lewat berarti antrean belum dituntaskan sebelum dibersihkan aplikasi.</li>
                </ul>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dataUrl = @json($dataUrl);
            const form = document.querySelector('[data-analytics-filter]');
            const statusLine = document.querySelector('[data-analytics-status]');
            const body = document.querySelector('[data-analytics-body]');
            const chartsArea = document.querySelector('[data-analytics-charts]');
            const emptyNotice = document.querySelector('[data-analytics-empty]');
            const defaultStatus = statusLine.innerHTML;
            const instances = {};

            const theme = {
                id: 'puskesmasku',
                label: 'PuskesmasKu',
                tokens: { colorDefault: '#238689', colorText: '#021225', colorTextSecondary: '#526667', colorBackground: '#ffffff', fontFamily: "'DM Sans', Arial, sans-serif" },
            };
            const palettes = {
                status: ['#238689', '#b42318', '#e0a526'],
                session: ['#238689', '#021225'],
                donut: ['#e0a526', '#25c5e9', '#165f62', '#238689', '#b42318'],
            };
            const fmt = (value) => Number(value).toLocaleString('id-ID');
            const pct = (value) => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';

            function draw(name, config) {
                const el = document.querySelector(`[data-chart="${name}"]`);
                if (!el || !window.GPTVis) return;
                instances[name]?.destroy();
                el.innerHTML = '';
                instances[name] = new window.GPTVis.GPTVis({ container: el, theme });
                instances[name].render(config);
            }

            function currentFilters() {
                return Object.fromEntries([...new FormData(form)].filter(([, value]) => value !== ''));
            }

            function setFilter(name, value) {
                const field = form.elements[name];
                if (field) field.value = value ?? '';
                load();
            }

            async function load() {
                const params = new URLSearchParams(currentFilters());
                history.replaceState(null, '', params.toString() ? `?${params}` : location.pathname);
                body.classList.add('is-loading');
                statusLine.textContent = 'Memuat data dari data warehouse…';
                try {
                    const response = await fetch(`${dataUrl}?${params}`, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'Gagal memuat data.');
                    render(payload);
                    statusLine.innerHTML = defaultStatus;
                } catch (error) {
                    statusLine.textContent = error.message;
                } finally {
                    body.classList.remove('is-loading');
                }
            }

            function render(data) {
                const kpi = data.kpi;
                document.querySelector('[data-kpi="total"]').textContent = fmt(kpi.total);
                document.querySelector('[data-kpi="pasien"]').textContent = fmt(kpi.pasien);
                document.querySelector('[data-kpi="persen_batal"]').textContent = pct(kpi.persen_batal);
                document.querySelector('[data-kpi="persen_utilisasi"]').textContent = pct(kpi.persen_utilisasi);
                draw('liquid', { type: 'liquid', percent: kpi.persen_selesai / 100, shape: 'circle' });

                const empty = kpi.total === 0;
                emptyNotice.hidden = !empty;
                chartsArea.hidden = empty;
                if (empty) return;

                document.querySelector('[data-trend-granularity]').textContent = `per ${data.trend.granularity}`;
                draw('trend', { type: 'line', data: data.trend.data, axisXTitle: { hari: 'Tanggal', minggu: 'Minggu (awal Senin)', bulan: 'Bulan' }[data.trend.granularity], axisYTitle: 'Jumlah antrean', style: { palette: palettes.status } });

                if (data.region) renderRegion(data.region);

                draw('services', { type: 'column', data: data.services, stack: true, label: false, axisXTitle: 'Layanan', axisYTitle: 'Jumlah antrean', style: { palette: palettes.status } });
                draw('utilization', {
                    type: 'dual-axes',
                    categories: data.utilization.categories,
                    series: [
                        { type: 'column', data: data.utilization.kapasitas, axisYTitle: 'Kapasitas' },
                        { type: 'line', data: data.utilization.utilisasi, axisYTitle: 'Utilisasi (%)' },
                    ],
                    axisXTitle: 'Layanan',
                    style: { palette: palettes.session, startAtZero: true },
                });
                draw('daySession', { type: 'column', data: data.daySession, group: true, label: false, axisXTitle: 'Hari', axisYTitle: 'Jumlah antrean', style: { palette: palettes.session } });
                draw('statuses', { type: 'pie', data: data.statuses, innerRadius: 0.6, style: { palette: palettes.donut } });

                const tableRows = (rows) => rows.map((row) => Object.fromEntries(Object.entries(row).map(([key, value]) =>
                    [key, typeof value === 'number' ? (key.includes('%') ? pct(value) : fmt(value)) : value])));
                draw('rankingHigh', { type: 'table', data: tableRows(data.ranking.highest) });
                draw('rankingLow', { type: 'table', data: tableRows(data.ranking.lowest) });
            }

            function renderRegion(region) {
                const isDistrict = region.level === 'kecamatan';
                const chartEl = document.querySelector('[data-chart="region"]');
                chartEl.style.height = `${Math.max(240, region.data.length * 24 + 60)}px`;
                document.querySelector('[data-region-level]').textContent = isDistrict ? 'kecamatan' : 'puskesmas';
                document.querySelector('[data-region-title]').textContent = isDistrict ? 'Antrean per kecamatan' : `Puskesmas di ${region.kecamatan}`;
                document.querySelector('[data-region-hint]').hidden = !isDistrict;
                document.querySelector('[data-region-up]').hidden = isDistrict;
                draw('region', { type: 'bar', data: region.data, label: false, axisXTitle: isDistrict ? 'Kecamatan' : 'Puskesmas', axisYTitle: 'Jumlah antrean' });

                // GPT-Vis tidak menyediakan event klik, sehingga drill-down memakai tombol per kecamatan.
                const drill = document.querySelector('[data-region-drill]');
                drill.innerHTML = '';
                drill.hidden = !isDistrict;
                if (!isDistrict) return;
                region.data.forEach((row) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'analytics-chip';
                    button.innerHTML = `<span></span><small>${fmt(row.value)}</small>`;
                    button.querySelector('span').textContent = row.category;
                    button.addEventListener('click', () => setFilter('kecamatan', row.category));
                    drill.appendChild(button);
                });
            }

            form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
            form.querySelectorAll('select').forEach((select) => select.addEventListener('change', load));
            document.querySelector('[data-analytics-reset]').addEventListener('click', () => { form.reset(); [...form.elements].forEach((el) => { if (el.tagName === 'SELECT') el.value = ''; }); load(); });
            document.querySelector('[data-region-up]')?.addEventListener('click', () => setFilter('kecamatan', ''));

            if (!window.GPTVis) {
                statusLine.textContent = 'Pustaka grafik GPT-Vis gagal dimuat.';
                return;
            }
            load();
        });
    </script>
@endif
@endsection
