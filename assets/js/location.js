/**
 * Fast, non-blocking Location Management for Enteangadi
 */
const EnteangadiLocation = {
    init() {
        const detectBtn = document.getElementById('detect-location');
        if (detectBtn) detectBtn.addEventListener('click', () => this.detectGPS());

        const searchInput = document.getElementById('modal-location-search');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.searchLocation();
            });
        }
    },

    extractLocality(address, defaultVal = 'Current Location') {
        if (!address) return defaultVal;
        return address.village || address.hamlet || address.local_authority || address.municipality ||
            address.village_panchayat || address.town || address.suburb || address.neighbourhood ||
            address.city_district || address.city || address.county || address.state_district || defaultVal;
    },

    flushPageCache() {
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ action: 'clearPageCache' });
        }
    },

    async getCurrentCoordinates() {
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Geolocation) {
            try {
                let perm = await window.Capacitor.Plugins.Geolocation.checkPermissions();
                if (perm.location !== 'granted') {
                    perm = await window.Capacitor.Plugins.Geolocation.requestPermissions();
                }
                if (perm.location === 'granted') {
                    const pos = await window.Capacitor.Plugins.Geolocation.getCurrentPosition({ enableHighAccuracy: false, timeout: 5000 });
                    return { lat: pos.coords.latitude, lng: pos.coords.longitude };
                }
            } catch (e) {}
        }

        if (navigator.geolocation) {
            try {
                return await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(
                        (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                        reject,
                        { enableHighAccuracy: false, timeout: 4500, maximumAge: 300000 }
                    );
                });
            } catch (e) {}
        }

        const ipProviders = [
            async () => {
                const d = await (await fetch('https://ipwho.is/')).json();
                return d && d.success ? { lat: parseFloat(d.latitude), lng: parseFloat(d.longitude), city: d.city } : null;
            },
            async () => {
                const d = await (await fetch('https://ipapi.co/json/')).json();
                return d && !d.error ? { lat: d.latitude, lng: d.longitude, city: d.city } : null;
            }
        ];
        for (const fn of ipProviders) {
            try {
                const res = await fn();
                if (res && res.lat && res.lng) return res;
            } catch (e) {}
        }
        throw new Error('Could not retrieve location via GPS or IP');
    },

    async detectGPS() {
        try { sessionStorage.removeItem('manualLocationClear'); } catch (e) {}
        const btn = document.getElementById('detect-location');
        const orig = btn ? btn.innerHTML : '';
        if (btn) btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Detecting...';
        try {
            const coords = await this.getCurrentCoordinates();
            if (coords.city) {
                await this.setLocation(coords.city, coords.lat, coords.lng);
            } else {
                await this.reverseGeocode(coords.lat, coords.lng);
            }
        } catch (error) {
            alert('Unable to retrieve your location: ' + error.message);
        } finally {
            if (btn) btn.innerHTML = orig;
        }
    },

    async reverseGeocode(lat, lng) {
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`);
            const data = await res.json();
            await this.setLocation(this.extractLocality(data.address, 'Current Location'), lat, lng);
        } catch (e) {
            await this.setLocation('Current Location', lat, lng);
        }
    },

    async searchLocation() {
        const input = document.getElementById('modal-location-search');
        const resultsDiv = document.getElementById('location-search-results');
        const query = input ? input.value.trim() : '';
        if (query.length < 3) {
            alert('Please enter at least 3 characters to search.');
            return;
        }
        resultsDiv.innerHTML = '<div style="padding:20px;text-align:center;"><i class="fa fa-spinner fa-spin"></i> Searching...</div>';
        resultsDiv.style.display = 'block';

        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`);
            const data = await res.json();
            if (!data.length) {
                resultsDiv.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);">No locations found.</div>';
                return;
            }
            resultsDiv.innerHTML = '';
            data.forEach(item => {
                const name = item.display_name.split(',')[0];
                const div = document.createElement('div');
                div.className = 'city-item';
                div.innerHTML = `<i class="fa fa-map-marker-alt"></i><div style="display:flex;flex-direction:column;"><span style="font-weight:600;">${name}</span><span style="font-size:11px;color:var(--text-muted);">${item.display_name}</span></div>`;
                div.addEventListener('click', () => {
                    try { sessionStorage.removeItem('manualLocationClear'); } catch (e) {}
                    this.setLocation(name, item.lat, item.lon);
                });
                resultsDiv.appendChild(div);
            });
        } catch (e) {
            resultsDiv.innerHTML = '<div style="padding:20px;text-align:center;color:var(--danger);">Search failed. Please try again.</div>';
        }
    },

    persistClientSide(locObj) {
        const json = JSON.stringify(locObj);
        try { localStorage.setItem('enteangadi_user_location', json); } catch (e) {}
        document.cookie = "user_location=" + encodeURIComponent(json) + "; path=/; max-age=31536000; SameSite=Lax";
        const headerTextEl = document.getElementById('current-location-text');
        if (headerTextEl && locObj.name) headerTextEl.textContent = locObj.name.split(',')[0];
    },

    async setLocation(name, lat, lng, silentSyncOnly = false) {
        this.persistClientSide({ name, lat, lng });
        try { sessionStorage.setItem('auto_location_attempted', 'true'); } catch (e) {}

        const statusEl = document.getElementById('loader-location-status');
        if (statusEl) statusEl.innerHTML = `📍 ${name.split(',')[0]}`;

        try {
            const fd = new FormData();
            fd.append('action', 'set_location');
            fd.append('location_name', name);
            fd.append('latitude', lat);
            fd.append('longitude', lng);
            const res = await fetch(`${EnteangadiConfig.baseUrl}/api/location.php`, { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success && !silentSyncOnly) {
                this.flushPageCache();
                const modal = document.getElementById('location-modal');
                if (modal) modal.style.display = 'none';
                if (window.EnteangadiLoader) window.EnteangadiLoader.startNavigation(`Updating to ${name.split(',')[0]}...`);
                const url = new URL(window.location.href);
                url.searchParams.set('loc_set', '1');
                window.location.replace(url.toString());
            }
        } catch (e) {}
    },

    async clearLocation() {
        try {
            try { sessionStorage.setItem('manualLocationClear', 'true'); } catch (e) {}
            try { localStorage.removeItem('enteangadi_user_location'); } catch (e) {}
            document.cookie = "user_location=; path=/; max-age=0; SameSite=Lax";
            this.flushPageCache();
            const fd = new FormData();
            fd.append('action', 'clear_location');
            const res = await fetch(`${EnteangadiConfig.baseUrl}/api/location.php`, { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) location.reload();
        } catch (e) {}
    },

    async detectGPSAuto() {
        try {
            if (sessionStorage.getItem('auto_location_attempted') === 'true' || sessionStorage.getItem('manualLocationClear') === 'true') return;
        } catch (e) {}
        if (new URLSearchParams(window.location.search).has('loc_set')) return;

        // 1. Fast restore from localStorage without reloading
        try {
            const raw = localStorage.getItem('enteangadi_user_location');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && parsed.name && parsed.lat && parsed.lng) {
                    const hadServerLoc = Boolean(EnteangadiConfig.hasLocation);
                    EnteangadiConfig.hasLocation = true;
                    EnteangadiConfig.location = parsed;
                    this.persistClientSide(parsed);
                    if (!hadServerLoc) {
                        this.setLocation(parsed.name, parsed.lat, parsed.lng, true);
                    }
                    return;
                }
            }
        } catch (e) {}

        // 2. Fast restore from PHP session if already present
        if (EnteangadiConfig.hasLocation && EnteangadiConfig.location && EnteangadiConfig.location.name) {
            this.persistClientSide(EnteangadiConfig.location);
            return;
        }

        // 3. First-time visitor with no saved location: detect quietly in background
        try { sessionStorage.setItem('auto_location_attempted', 'true'); } catch (e) {}
        const headerTextEl = document.getElementById('current-location-text');
        const origText = headerTextEl ? headerTextEl.innerHTML : 'Set Location';
        try {
            if (headerTextEl) headerTextEl.innerHTML = `<span class="location-pulse-dot"></span> Locating...`;
            const coords = await this.getCurrentCoordinates();
            if (coords.lat && coords.lng) {
                if (coords.city) {
                    await this.setLocation(coords.city, coords.lat, coords.lng, true);
                } else {
                    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${coords.lat}&lon=${coords.lng}&zoom=16&addressdetails=1`);
                    const data = await res.json();
                    const city = this.extractLocality(data.address, 'Current Location');
                    await this.setLocation(city, coords.lat, coords.lng, true);
                }
            }
        } catch (e) {
            if (headerTextEl) headerTextEl.innerHTML = origText;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    EnteangadiLocation.init();
    if (typeof EnteangadiConfig !== 'undefined') {
        setTimeout(() => EnteangadiLocation.detectGPSAuto(), 500);
    }
});
