const toRadians = (degrees) => degrees * Math.PI / 180;

function distanceInKm(lat1, lng1, lat2, lng2) {
    const earthRadius = 6371;
    const deltaLat = toRadians(lat2 - lat1);
    const deltaLng = toRadians(lng2 - lng1);
    const a = Math.sin(deltaLat / 2) ** 2
        + Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2))
        * Math.sin(deltaLng / 2) ** 2;
    return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
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
                const distance = distanceInKm(
                    coords.latitude,
                    coords.longitude,
                    Number(row.dataset.lat),
                    Number(row.dataset.lng)
                );
                row.dataset.distanceKm = String(distance);
                const display = row.querySelector('[data-distance]');
                display.hidden = false;
                display.querySelector('strong').textContent = `${distance.toFixed(1)} km`;
            });
            rows.sort((a, b) => Number(a.dataset.distanceKm) - Number(b.dataset.distanceKm));
            rows.forEach((row, index) => {
                row.querySelector('.result-number').textContent = String(index + 1).padStart(2, '0');
                hospitalList.appendChild(row);
            });
            locationStatus.textContent = 'Hasil diurutkan berdasarkan jarak garis lurus terdekat.';
            setLocationButtonState('Sudah diurutkan dari lokasi', true);
            document.querySelector('#hasil-rumah-sakit')?.scrollIntoView({ behavior: 'smooth' });
        },
        () => {
            locationStatus.textContent = 'Izin lokasi tidak diberikan. Anda tetap dapat memakai pencarian biasa.';
            setLocationButtonState(null);
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
    );
}

locationButtons.forEach((button) => button.addEventListener('click', useCurrentLocation));

document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('.flash')?.remove());
});

const deleteDialog = document.querySelector('[data-delete-dialog]');
document.querySelectorAll('[data-delete-trigger]').forEach((button) => {
    button.addEventListener('click', () => {
        deleteDialog.querySelector('[data-delete-name]').textContent = button.dataset.name;
        const deleteForm = deleteDialog.querySelector('[data-delete-form]');
        deleteForm.action = deleteForm.dataset.actionTemplate.replace('__ID__', button.dataset.id);
        deleteDialog.showModal();
    });
});

document.querySelector('[data-delete-cancel]')?.addEventListener('click', () => deleteDialog.close());

const masterPanels = [...document.querySelectorAll('[data-master-panel]')];
const masterTabs = [...document.querySelectorAll('[data-master-tab]')];

function showMasterPanel(panelId) {
    const target = masterPanels.find((panel) => panel.id === panelId) ?? masterPanels[0];
    if (!target) return;

    masterPanels.forEach((panel) => panel.classList.toggle('is-active', panel === target));
    masterTabs.forEach((tab) => {
        const active = tab.hash === `#${target.id}`;
        tab.classList.toggle('is-active', active);
        if (active) tab.setAttribute('aria-current', 'page');
        else tab.removeAttribute('aria-current');
    });
}

if (masterPanels.length) {
    showMasterPanel(window.location.hash.slice(1));
    masterTabs.forEach((tab) => tab.addEventListener('click', () => showMasterPanel(tab.hash.slice(1))));
    window.addEventListener('hashchange', () => showMasterPanel(window.location.hash.slice(1)));
}
