/**
 * Location management for Enteangadi
 */

const EnteangadiLocation = {
    init() {
        this.bindEvents();
    },

    bindEvents() {
        const detectBtn = document.getElementById('detect-location');
        if (detectBtn) {
            detectBtn.addEventListener('click', () => this.detectGPS());
        }

        const searchInput = document.getElementById('modal-location-search');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.searchLocation();
                }
            });
        }
    },

    async getCurrentCoordinates() {
        // Try Capacitor native location first if running inside Capacitor container
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Geolocation) {
            try {
                const permissionStatus = await window.Capacitor.Plugins.Geolocation.checkPermissions();
                if (permissionStatus.location !== 'granted') {
                    const requestStatus = await window.Capacitor.Plugins.Geolocation.requestPermissions();
                    if (requestStatus.location !== 'granted') {
                        throw new Error('Location permission denied natively');
                    }
                }
                const coordinates = await window.Capacitor.Plugins.Geolocation.getCurrentPosition({
                    enableHighAccuracy: true,
                    timeout: 5000
                });
                return {
                    lat: coordinates.coords.latitude,
                    lng: coordinates.coords.longitude
                };
            } catch (error) {
                console.warn('Capacitor native Geolocation failed, falling back to browser geolocation:', error);
            }
        }

        // Web Geolocation fallback
        if (navigator.geolocation) {
            try {
                const coords = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            resolve({
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            });
                        },
                        (error) => {
                            reject(error);
                        },
                        { enableHighAccuracy: true, timeout: 4000, maximumAge: 0 }
                    );
                });
                return coords;
            } catch (error) {
                console.warn('Browser GPS Geolocation failed or blocked. Falling back to IP Geolocation:', error.message);
            }
        }

        // Final resilient fallback: IP-based Geolocation (works perfectly on insecure HTTP and requires zero user permissions!)
        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            if (data && !data.error) {
                console.log('IP Geolocation succeeded:', data.city);
                return {
                    lat: data.latitude,
                    lng: data.longitude,
                    city: data.city
                };
            }
        } catch (ipErr) {
            console.error('IP Geolocation fallback failed:', ipErr);
        }

        throw new Error('Could not retrieve location via GPS or IP');
    },

    async detectGPS() {
        sessionStorage.removeItem('manualLocationClear');
        const btn = document.getElementById('detect-location');
        const originalContent = btn.innerHTML;
        if (btn) btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Detecting...';

        try {
            const coords = await this.getCurrentCoordinates();
            if (coords.city) {
                await this.setLocation(coords.city, coords.lat, coords.lng);
                if (btn) btn.innerHTML = originalContent;
            } else {
                await this.reverseGeocode(coords.lat, coords.lng, btn, originalContent);
            }
        } catch (error) {
            if (btn) btn.innerHTML = originalContent;
            alert('Unable to retrieve your location: ' + error.message);
        }
    },

    async reverseGeocode(lat, lng, btn, originalContent) {
        try {
            // Using Nominatim (OpenStreetMap)
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10&addressdetails=1`);
            const data = await response.json();

            const city = data.address.city || data.address.town || data.address.village || data.address.state_district || 'Unknown Location';

            await this.setLocation(city, lat, lng);
            if (btn) btn.innerHTML = originalContent;
        } catch (error) {
            console.error('Reverse geocoding error:', error);
            await this.setLocation('Current Location', lat, lng);
            if (btn) btn.innerHTML = originalContent;
        }
    },

    async searchLocation() {
        const input = document.getElementById('modal-location-search');
        const resultsDiv = document.getElementById('location-search-results');
        const query = input.value.trim();

        if (query.length < 3) {
            alert('Please enter at least 3 characters to search.');
            return;
        }

        resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center;"><i class="fa fa-spinner fa-spin"></i> Searching...</div>';
        resultsDiv.style.display = 'block';

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`);
            const data = await response.json();

            if (data.length === 0) {
                resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">No locations found.</div>';
                return;
            }

            resultsDiv.innerHTML = '';
            data.forEach(item => {
                const name = item.display_name.split(',')[0];
                const fullName = item.display_name;
                const div = document.createElement('div');
                div.className = 'city-item';
                div.innerHTML = `
                    <i class="fa fa-map-marker-alt"></i>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: 600;">${name}</span>
                        <span style="font-size: 11px; color: var(--text-muted);">${fullName}</span>
                    </div>
                `;
                div.addEventListener('click', () => {
                    sessionStorage.removeItem('manualLocationClear');
                    this.setLocation(name, item.lat, item.lon);
                });
                resultsDiv.appendChild(div);
            });
        } catch (error) {
            console.error('Location search error:', error);
            resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--danger);">Search failed. Please try again.</div>';
        }
    },

    async setLocation(name, lat, lng) {
        try {
            const formData = new FormData();
            formData.append('action', 'set_location');
            formData.append('location_name', name);
            formData.append('latitude', lat);
            formData.append('longitude', lng);

            const response = await fetch(`${EnteangadiConfig.baseUrl}/api/location.php`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                location.reload(); // Reload to apply filters
            }
        } catch (error) {
            console.error('Error setting location:', error);
        }
    },

    async clearLocation() {
        try {
            sessionStorage.setItem('manualLocationClear', 'true');
            const formData = new FormData();
            formData.append('action', 'clear_location');

            const response = await fetch(`${EnteangadiConfig.baseUrl}/api/location.php`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                location.reload();
            }
        } catch (error) {
            console.error('Error clearing location:', error);
        }
    },

    async detectGPSAuto() {
        // If manually cleared in this session, do not auto-detect
        if (sessionStorage.getItem('manualLocationClear') === 'true') {
            return;
        }

        try {
            const coords = await this.getCurrentCoordinates();
            const lat = coords.lat;
            const lng = coords.lng;

            // Compare with currently set location to avoid infinite loops and unnecessary reloads
            if (typeof EnteangadiConfig !== 'undefined' && EnteangadiConfig.location) {
                const storedLat = parseFloat(EnteangadiConfig.location.lat);
                const storedLng = parseFloat(EnteangadiConfig.location.lng);

                // If coordinates are very close (approx. 200m / 0.002 degrees difference), we can skip reverse geocoding & setting
                if (storedLat && storedLng) {
                    const diffLat = Math.abs(lat - storedLat);
                    const diffLng = Math.abs(lng - storedLng);
                    if (diffLat < 0.002 && diffLng < 0.002) {
                        console.log('Location hasn\'t changed significantly. Skipping update.');
                        return;
                    }
                }
            }

            if (coords.city) {
                await this.setLocation(coords.city, lat, lng);
            } else {
                await this.reverseGeocode(lat, lng, null, '');
            }
        } catch (error) {
            console.warn('Auto-location detection failed:', error.message);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    EnteangadiLocation.init();

    // Always fetch location automatically when the application is opened or reloaded
    if (typeof EnteangadiConfig !== 'undefined') {
        EnteangadiLocation.detectGPSAuto();
    }
});
