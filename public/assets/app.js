document.addEventListener('DOMContentLoaded', () => {
    const panels = [...document.querySelectorAll('[data-master-panel]')];
    const tabs = [...document.querySelectorAll('[data-master-tab]')];
    if (!panels.length) return;

    const show = (id) => {
        const selected = panels.some((panel) => panel.id === id) ? id : panels[0].id;
        panels.forEach((panel) => panel.classList.toggle('active', panel.id === selected));
        tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.masterTab === selected));
    };

    show(location.hash.slice(1));
    tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.masterTab)));
    window.addEventListener('hashchange', () => show(location.hash.slice(1)));
});


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

const locationButtons = [...document.querySelectorAll('[data-use-location]')];
const locationStatus = document.querySelector('[data-location-status]');
const hospitalList = document.querySelector('[data-hospital-list]');

locationButtons.forEach((button) => {
    const label = button.querySelector('strong') ?? button;
    button.dataset.locationOriginalLabel = label.textContent.trim();
});

function setLocationButtonState(label, disabled = false) {
    locationButtons.forEach((button) => {
        button.disabled = disabled;
        button.setAttribute('aria-busy', disabled ? 'true' : 'false');

        const labelTarget = button.querySelector('strong') ?? button;
        labelTarget.textContent = label ?? button.dataset.locationOriginalLabel;
    });
}

function useCurrentLocation() {
    if (!locationStatus) return;
    if (!navigator.geolocation) {
        locationStatus.textContent = 'Peramban ini tidak mendukung pembacaan lokasi.';
        return;
    }

    if (!hospitalList) {
        locationStatus.textContent = 'Belum ada rumah sakit yang dapat diurutkan.';
        return;
    }

    setLocationButtonState('Membaca lokasi…', true);
    locationStatus.textContent = 'Sedang membaca lokasi perangkat Anda…';
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            const rows = [...document.querySelectorAll('[data-hospital]')];
            rows.forEach((row) => {
                const lat = row.dataset.lat?.trim();
                const lng = row.dataset.lng?.trim();
                const valid = lat && lng && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
                    && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lng)) <= 180;
                const distance = valid ? distanceInKm(coords.latitude, coords.longitude, Number(lat), Number(lng)) : Infinity;
                row.dataset.distanceKm = String(distance);
                const display = row.querySelector('[data-distance]');
                display.hidden = false;
                display.querySelector('strong').textContent = Number.isFinite(distance) ? `${distance.toFixed(1)} km` : 'Belum tersedia';
            });
            rows.sort((a, b) => {
                const da = Number(a.dataset.distanceKm), db = Number(b.dataset.distanceKm);
                return da === db ? 0 : da - db;
            });
            rows.forEach((row, index) => {
                row.querySelector('.result-number').textContent = String(index + 1).padStart(2, '0');
                hospitalList.appendChild(row);
            });
            locationStatus.textContent = 'Hasil diurutkan berdasarkan jarak garis lurus terdekat.';
            setLocationButtonState('Perbarui lokasi saya');
            document.querySelector('#hasil-puskesmas')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        },
        (error) => {
            const messages = { 1: 'Izin lokasi tidak diberikan. Anda tetap dapat memakai pencarian biasa.', 2: 'Lokasi belum tersedia. Coba lagi atau gunakan pencarian biasa.', 3: 'Pembacaan lokasi terlalu lama. Silakan coba lagi.' };
            locationStatus.textContent = messages[error.code] ?? 'Lokasi belum dapat dibaca. Gunakan pencarian biasa.';
            setLocationButtonState(null);
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
    );
}

locationButtons.forEach((button) => button.addEventListener('click', useCurrentLocation));


const clinicMap = document.querySelector('[data-clinic-map]');
if (clinicMap) {
    const selectors = [...clinicMap.querySelectorAll('[data-map-select]')];
    const details = [...clinicMap.querySelectorAll('[data-map-detail]')];

    const selectClinic = (id) => {
        selectors.forEach((selector) => {
            const selected = selector.dataset.mapSelect === id;
            selector.classList.toggle('is-active', selected);
            if (selector.classList.contains('map-marker')) selector.setAttribute('aria-pressed', String(selected));
        });
        details.forEach((detail) => { detail.hidden = detail.dataset.mapDetail !== id; });
    };

    selectors.forEach((selector) => selector.addEventListener('click', () => selectClinic(selector.dataset.mapSelect)));
}

const liveQueueTargets = [...document.querySelectorAll('[data-puskesmas-id]')];
if (liveQueueTargets.length) {
    const endpoint = document.body.dataset.liveQueueEndpoint;
    const liveStatus = document.querySelector('[data-live-status]');
    const toastStack = document.querySelector('[data-live-toast-stack]');
    let previousValues = new Map();

    const setLiveValue = (container, selector, value) => {
        container.querySelectorAll(selector).forEach((element) => { element.textContent = String(value); });
    };

    const showLiveToast = (item, difference) => {
        if (!toastStack) return;
        const toast = document.createElement('div');
        toast.className = 'live-toast';
        toast.setAttribute('role', 'status');
        const icon = document.createElement('span');
        icon.className = 'live-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '+';
        const message = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `${difference} antrean baru masuk`;
        const detail = document.createElement('small');
        detail.textContent = item.name;
        message.append(title, detail);
        toast.append(icon, message);
        toastStack.appendChild(toast);
        window.setTimeout(() => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 260);
        }, 4500);
    };

    const refreshLiveQueues = async () => {
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            const values = new Map(payload.puskesmas.map((item) => [String(item.id), item]));

            if (previousValues.size) {
                values.forEach((item, id) => {
                    const previous = previousValues.get(id);
                    if (previous && item.total > previous.total) showLiveToast(item, item.total - previous.total);
                });
            }

            liveQueueTargets.forEach((target) => {
                const item = values.get(target.dataset.puskesmasId);
                if (!item) return;
                setLiveValue(target, '[data-live-total]', item.total);
                setLiveValue(target, '[data-live-active]', item.active);
                setLiveValue(target, '[data-live-completed]', item.completed);
            });

            document.querySelectorAll('[data-live-summary-total]').forEach((element) => { element.textContent = String(payload.totals.queues); });
            document.querySelectorAll('[data-live-summary-active]').forEach((element) => { element.textContent = String(payload.totals.active); });
            if (liveStatus) liveStatus.textContent = `Data live diperbarui ${new Date(payload.generated_at).toLocaleTimeString('id-ID')}. Pembaruan berikutnya dalam 5 detik.`;
            previousValues = values;
        } catch (error) {
            if (liveStatus) liveStatus.textContent = 'Data live belum dapat diperbarui. Sistem akan mencoba lagi otomatis.';
        }
    };

    refreshLiveQueues();
    window.setInterval(refreshLiveQueues, 5000);
}


// Progressive enhancement: navigation remains available when JavaScript is off.
const menuToggle = document.querySelector('[data-menu-toggle]');
const mainNavigation = document.querySelector('#main-navigation');
if (menuToggle && mainNavigation) {
    menuToggle.hidden = false;
    mainNavigation.classList.add('is-collapsible');
    const setMenuOpen = (open) => {
        mainNavigation.classList.toggle('is-open', open);
        menuToggle.setAttribute('aria-expanded', String(open));
    };
    menuToggle.addEventListener('click', () => setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true'));
    mainNavigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
            setMenuOpen(false);
            menuToggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!mainNavigation.contains(event.target) && !menuToggle.contains(event.target)) setMenuOpen(false);
    });
}
