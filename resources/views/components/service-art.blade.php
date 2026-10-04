@php
    $serviceImage = 'assets/services/'.\Illuminate\Support\Str::slug($name).'.png';
@endphp
@if (is_file(public_path($serviceImage)))
    <img class="{{ $class ?? 'service-art' }}" src="{{ asset($serviceImage) }}?v=service-photos-1" alt="" loading="lazy">
@else
    <div class="{{ $class ?? 'service-art' }} service-art-fallback" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)) }}</div>
@endif
