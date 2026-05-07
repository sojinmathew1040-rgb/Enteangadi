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

    detectGPS() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser');
            return;
        }

        const btn = document.getElementById('detect-location');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Detecting...';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                this.reverseGeocode(lat, lng, btn, originalContent);
            },
            (error) => {
                btn.innerHTML = originalContent;
                alert('Unable to retrieve your location: ' + error.message);
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
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
    }
};

document.addEventListener('DOMContentLoaded', () => EnteangadiLocation.init());
