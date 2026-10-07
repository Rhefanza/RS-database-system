@if ($facility)
<figure class="puskesmas-banner">
    <img src="{{ $facility->photo_url }}" alt="Ilustrasi suasana layanan puskesmas" decoding="async">
    <figcaption>{{ $facility->nama_puskesmas }}</figcaption>
</figure>
@endif
