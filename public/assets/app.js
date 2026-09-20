const toRadians = (degrees) => degrees * Math.PI / 180;

function distanceInKm(lat1, lng1, lat2, lng2) {
    const earthRadius = 6371;
    const deltaLat = toRadians(lat2 - lat1);
    const deltaLng = toRadians(lng2 - lng1);
    const a = Math.sin(deltaLat / 2) ** 2
        + Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2))
        * Math.sin(deltaLng / 2) ** 2;
    const bounded = Math.min(1, Math.max(0, a));
    return earthRadius * 2 * Math.atan2(Math.sqrt(bounded), Math.sqrt(1 - bounded));
}

function decodeBase64Json(value) {
    try {
        const bytes = Uint8Array.from(atob(value), (character) => character.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    } catch {
        return [];
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);
}

document.addEventListener('DOMContentLoaded', () => {
    const panels = [...document.querySelectorAll('[data-master-panel]')];
    const tabs = [...document.querySelectorAll('[data-master-tab]')];
    if (panels.length) {
        const show = (id) => {
            const selected = panels.some((panel) => panel.id === id) ? id : panels[0].id;
            panels.forEach((panel) => panel.classList.toggle('active', panel.id === selected));
            tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.masterTab === selected));
        };
        show(location.hash.slice(1));
        tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.masterTab)));
        window.addEventListener('hashchange', () => show(location.hash.slice(1)));
    }

    const menuButton = document.querySelector('[data-menu-toggle]');
    const navigation = document.querySelector('#main-navigation');
    if (menuButton && navigation) {
        menuButton.hidden = false;
        navigation.classList.add('is-collapsible');
        menuButton.addEventListener('click', () => {
            const open = navigation.classList.toggle('is-open');
            menuButton.setAttribute('aria-expanded', String(open));
        });
    }

    initClinicSearch();
    initSurabayaMap();
    initRecommendations();
    initLiveQueues();
});

function initClinicSearch() {
    const form = document.querySelector('[data-clinic-search]');
    if (!form) return;
    const input = form.querySelector('input[type="search"]');
    const results = form.querySelector('[role="listbox"]');
    const clinics = decodeBase64Json(form.dataset.searchSource ?? '');
    let matches = [];

    const normalizedClinicName = (name) => name.replace(/^puskesmas\s+(demo\s+)?/i, '').toLocaleLowerCase('id');
    const findMatches = (term) => {
        const query = term.trim().toLocaleLowerCase('id');
        if (!query) return [];
        const nameStarts = clinics.filter((clinic) => normalizedClinicName(clinic.name).startsWith(query));
        const extras = clinics.filter((clinic) => !nameStarts.includes(clinic) && (
            clinic.district.toLocaleLowerCase('id').startsWith(query)
            || clinic.services.some((service) => service.toLocaleLowerCase('id').startsWith(query))
            || (query.length >= 2 && clinic.address.toLocaleLowerCase('id').includes(query))
        ));
        return [...nameStarts, ...extras];
    };

    const close = () => {
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };
    const choose = (clinic) => {
        input.value = clinic.name.replace(/^Puskesmas Demo\s+/i, '');
        close();
        window.dispatchEvent(new CustomEvent('clinic:focus', { detail: clinic }));
        document.querySelector('#peta-surabaya')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    };
    const render = () => {
        matches = findMatches(input.value);
        results.replaceChildren();
        if (!input.value.trim()) return close();
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'search-empty';
            empty.textContent = 'Tidak ada puskesmas, kecamatan, atau poli yang cocok.';
            results.appendChild(empty);
        } else {
            matches.slice(0, 10).forEach((clinic) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'option');
                const title = document.createElement('strong');
                title.textContent = clinic.name;
                const detail = document.createElement('span');
                detail.textContent = `Kecamatan ${clinic.district} · ${clinic.active} antrean aktif`;
                const services = document.createElement('small');
                services.textContent = clinic.services.join(' · ');
                button.append(title, detail, services);
                button.addEventListener('click', () => choose(clinic));
                results.appendChild(button);
            });
        }
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    document.addEventListener('click', (event) => { if (!form.contains(event.target)) close(); });
    form.addEventListener('submit', (event) => {
        const first = findMatches(input.value)[0];
        if (first) {
            event.preventDefault();
            choose(first);
        }
    });
    if (input.value.trim()) window.setTimeout(render, 0);
}

function initSurabayaMap() {
    const element = document.querySelector('[data-leaflet-map]');
    if (!element) return;
    if (!window.L) {
        element.innerHTML = '<p class="map-load-error">Peta belum dapat dimuat. Periksa koneksi internet lalu muat ulang halaman.</p>';
        return;
    }

    const clinics = decodeBase64Json(element.dataset.mapItems ?? '');
    const clinicById = new Map(clinics.map((clinic) => [String(clinic.id), clinic]));
    const markers = new Map();
    const map = L.map(element, { scrollWheelZoom: false, zoomControl: true }).setView([-7.2756, 112.7508], 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const markerColor = (active) => active <= 3 ? '#168665' : active <= 7 ? '#d79a22' : '#d44f65';
    const markerLabel = (clinic) => `<span class="map-queue-count">${clinic.active}</span><span class="map-marker-name">${escapeHtml(clinic.name)}</span>`;
    const popupContent = (clinic) => `<div class="clinic-popup"><span>Kecamatan ${escapeHtml(clinic.district)}</span><strong>${escapeHtml(clinic.name)}</strong><p>${escapeHtml(clinic.address)}</p><p><b>${clinic.active} antrean aktif</b></p><a href="${escapeHtml(clinic.url)}">Lihat jadwal & antre →</a></div>`;
    const makeMarker = (clinic) => {
        const marker = L.circleMarker([clinic.latitude, clinic.longitude], {
            radius: 15, color: '#ffffff', weight: 3, fillColor: markerColor(clinic.active), fillOpacity: 1,
        }).addTo(map);
        marker.bindTooltip(markerLabel(clinic), { permanent: true, direction: 'center', className: 'queue-map-label' });
        marker.bindPopup(popupContent(clinic), { maxWidth: 280 });
        marker.on('click', () => marker.openPopup());
        markers.set(String(clinic.id), marker);
    };
    clinics.forEach(makeMarker);
    if (clinics.length) map.fitBounds(clinics.map((clinic) => [clinic.latitude, clinic.longitude]), { padding: [24, 24] });
    const updateZoomLabels = () => element.classList.toggle('show-faskes-labels', map.getZoom() >= 13);
    map.on('zoomend', updateZoomLabels);
    updateZoomLabels();

    window.addEventListener('clinic:focus', ({ detail }) => {
        const marker = markers.get(String(detail.id));
        if (!marker) return;
        map.setView(marker.getLatLng(), 14, { animate: true });
        marker.openPopup();
    });
    window.addEventListener('queues:updated', ({ detail }) => {
        detail.forEach((update) => {
            const clinic = clinicById.get(String(update.id));
            const marker = markers.get(String(update.id));
            if (!clinic || !marker) return;
            Object.assign(clinic, update);
            marker.setStyle({ fillColor: markerColor(clinic.active) });
            marker.setTooltipContent(markerLabel(clinic));
            marker.setPopupContent(popupContent(clinic));
        });
    });
}

function initRecommendations() {
    const list = document.querySelector('[data-recommendation-list]');
    if (!list) return;
    const cards = [...list.querySelectorAll('[data-recommendation-card]')];
    const status = document.querySelector('[data-recommendation-status]');
    const originLabel = document.querySelector('[data-recommendation-origin]');
    const locationButton = document.querySelector('[data-use-recommendation-location]');
    let origin = { latitude: -7.2575, longitude: 112.7521 };

    const rank = () => {
        cards.forEach((card) => {
            card.dataset.distance = String(distanceInKm(origin.latitude, origin.longitude, Number(card.dataset.lat), Number(card.dataset.lng)));
        });
        const nearestTen = [...cards].sort((a, b) => Number(a.dataset.distance) - Number(b.dataset.distance)).slice(0, 10);
        const selected = nearestTen.sort((a, b) => Number(a.dataset.active) - Number(b.dataset.active) || Number(a.dataset.distance) - Number(b.dataset.distance)).slice(0, 5);
        cards.forEach((card) => { card.hidden = !selected.includes(card); });
        selected.forEach((card, index) => {
            card.querySelector('[data-recommendation-rank]').textContent = String(index + 1).padStart(2, '0');
            card.querySelector('[data-recommendation-distance]').textContent = `${Number(card.dataset.distance).toFixed(1)} km`;
            list.appendChild(card);
        });
    };
    rank();

    locationButton?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Peramban ini tidak mendukung pembacaan lokasi.';
            return;
        }
        locationButton.disabled = true;
        status.textContent = 'Sedang membaca lokasi perangkat Anda…';
        navigator.geolocation.getCurrentPosition(({ coords }) => {
            origin = { latitude: coords.latitude, longitude: coords.longitude };
            originLabel.textContent = 'Lokasi perangkat Anda';
            status.textContent = 'Urutan sudah diperbarui berdasarkan lokasi dan antrean aktif.';
            locationButton.textContent = 'Perbarui lokasi saya';
            locationButton.disabled = false;
            rank();
        }, () => {
            status.textContent = 'Lokasi belum dapat dibaca. Rekomendasi tetap dihitung dari pusat Kota Surabaya.';
            locationButton.disabled = false;
        }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
    });
    window.addEventListener('queues:updated', ({ detail }) => {
        const updates = new Map(detail.map((item) => [String(item.id), item]));
        cards.forEach((card) => {
            const update = updates.get(card.dataset.puskesmasId);
            if (update) card.dataset.active = String(update.active);
        });
        rank();
    });
}

function initLiveQueues() {
    const endpoint = document.body.dataset.liveQueueEndpoint;
    if (!endpoint) return;
    const liveStatus = document.querySelector('[data-live-status]');
    const toastStack = document.querySelector('[data-live-toast-stack]');
    let previousValues = new Map();

    const showToast = (item, difference) => {
        if (!toastStack) return;
        const toast = document.createElement('div');
        toast.className = 'live-toast';
        toast.setAttribute('role', 'status');
        const icon = document.createElement('span');
        icon.className = 'live-toast-icon';
        icon.textContent = '+';
        const message = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `${difference} antrean baru masuk`;
        const detail = document.createElement('small');
        detail.textContent = item.name;
        message.append(title, detail);
        toast.append(icon, message);
        toastStack.appendChild(toast);
        setTimeout(() => { toast.classList.add('is-leaving'); setTimeout(() => toast.remove(), 260); }, 4500);
    };

    const refresh = async () => {
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Gagal memuat data');
            const data = await response.json();
            data.puskesmas.forEach((item) => {
                const previous = previousValues.get(String(item.id));
                if (previous !== undefined && item.total > previous) showToast(item, item.total - previous);
                previousValues.set(String(item.id), item.total);
                document.querySelectorAll(`[data-puskesmas-id="${item.id}"]`).forEach((container) => {
                    container.querySelectorAll('[data-live-total]').forEach((target) => { target.textContent = String(item.total); });
                    container.querySelectorAll('[data-live-active]').forEach((target) => { target.textContent = String(item.active); });
                    container.querySelectorAll('[data-live-completed]').forEach((target) => { target.textContent = String(item.completed); });
                });
            });
            document.querySelectorAll('[data-live-summary-total]').forEach((target) => { target.textContent = String(data.totals.queues); });
            document.querySelectorAll('[data-live-summary-active]').forEach((target) => { target.textContent = String(data.totals.active); });
            if (liveStatus) liveStatus.textContent = `Diperbarui ${new Date(data.generated_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
            window.dispatchEvent(new CustomEvent('queues:updated', { detail: data.puskesmas }));
        } catch {
            if (liveStatus) liveStatus.textContent = 'Pembaruan otomatis tertunda. Data terakhir tetap ditampilkan.';
        }
    };

    refresh();
    window.setInterval(refresh, 5000);
}
