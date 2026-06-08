/**
 * Location management for Enteangadi
*/

const EnteangadiLocation = {
    init() {
        this.bindEvents();
    },

    extractLocality(address, defaultVal = 'Current Location') {
        if (!address) return defaultVal;
        return address.village || 
               address.hamlet || 
               address.local_authority || 
               address.municipality || 
               address.village_panchayat || 
               address.town || 
               address.suburb || 
               address.neighbourhood || 
               address.city_district || 
               address.city || 
               address.county || 
               address.state_district || 
               defaultVal;
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
                    enableHighAccuracy: false, // Triangulation first for battery/speed
                    timeout: 10000
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
            // Stage 1: Try low-accuracy/fast geolocation first (Wi-Fi/Cellular) - highly reliable and works indoors/PCs
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
                        { enableHighAccuracy: false, timeout: 8000, maximumAge: 0 }
                    );
                });
                return coords;
            } catch (error) {
                console.warn('Browser fast geolocation failed, trying high accuracy:', error.message);
                
                // Stage 2: Try high-accuracy GPS if low accuracy failed
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
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                        );
                    });
                    return coords;
                } catch (gpsError) {
                    console.warn('Browser GPS Geolocation failed. Falling back to IP Geolocation:', gpsError.message);
                }
            }
        }

        // Final resilient fallback: Multi-layered IP-based Geolocation
        // 1. Try ipapi.co
        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            if (data && !data.error) {
                console.log('ipapi.co Geolocation succeeded:', data.city);
                return {
                    lat: data.latitude,
                    lng: data.longitude,
                    city: data.city
                };
            }
        } catch (ipErr) {
            console.warn('ipapi.co Geolocation fallback failed, trying ipwhois.app:', ipErr);
        }

        // 2. Try ipwho.is (extremely reliable alternative)
        try {
            const response = await fetch('https://ipwho.is/');
            const data = await response.json();
            if (data && data.success) {
                console.log('ipwho.is Geolocation succeeded:', data.city);
                return {
                    lat: parseFloat(data.latitude),
                    lng: parseFloat(data.longitude),
                    city: data.city
                };
            }
        } catch (ipErr2) {
            console.warn('ipwho.is Geolocation fallback failed, trying ip-api.com:', ipErr2);
        }

        // 3. Try ip-api.com (works on plaintext HTTP context perfectly)
        try {
            const response = await fetch('http://ip-api.com/json/');
            const data = await response.json();
            if (data && data.status === 'success') {
                console.log('ip-api.com Geolocation succeeded:', data.city);
                return {
                    lat: data.lat,
                    lng: data.lon,
                    city: data.city
                };
            }
        } catch (ipErr3) {
            console.error('ip-api.com Geolocation fallback failed:', ipErr3);
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
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`);
            const data = await response.json();

            const city = this.extractLocality(data.address, 'Unknown Location');

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
        // Save to localStorage
        try {
            localStorage.setItem('enteangadi_user_location', JSON.stringify({ name, lat, lng }));
        } catch (e) {
            console.warn('localStorage is not accessible:', e);
        }

        // Save to cookie (valid for 1 year)
        document.cookie = "user_location=" + encodeURIComponent(JSON.stringify({ name, lat, lng })) + "; path=/; max-age=31536000; SameSite=Lax";

        // Mark as auto-location attempted in this tab session
        try {
            sessionStorage.setItem('auto_location_attempted', 'true');
        } catch (e) { }

        const statusEl = document.getElementById('loader-location-status');
        if (statusEl) {
            statusEl.innerHTML = `<i class="fa fa-check-circle" style="color: #4CAF50;"></i> 📍 ${name.split(',')[0]} detected!`;
        }

        // Dynamically update header location text in DOM immediately
        const headerTextEl = document.getElementById('current-location-text');
        if (headerTextEl) {
            const displayCity = name.split(',')[0];
            headerTextEl.innerHTML = `${displayCity}`;
        }

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
                const currentUrl = window.location.href;
                const separator = currentUrl.includes('?') ? '&' : '?';
                const reloadUrl = currentUrl.includes('loc_set=1') ? currentUrl : (currentUrl + separator + 'loc_set=1');

                const loaderWrapper = document.getElementById('loader-wrapper');
                const isLoaderVisible = loaderWrapper && loaderWrapper.style.display !== 'none' && !loaderWrapper.classList.contains('loader-hide');

                if (isLoaderVisible) {
                    // Brief delay to let the user see the detected city in the splash pill
                    setTimeout(() => {
                        window.location.href = reloadUrl;
                    }, 1200);
                } else {
                    window.location.href = reloadUrl;
                }
            }
        } catch (error) {
            console.error('Error setting location:', error);
        }
    },

    async clearLocation() {
        try {
            try {
                sessionStorage.setItem('manualLocationClear', 'true');
            } catch (e) { }
            try {
                localStorage.removeItem('enteangadi_user_location');
            } catch (e) { }
            document.cookie = "user_location=; path=/; max-age=0; SameSite=Lax";

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
        const statusEl = document.getElementById('loader-location-status');
        const updateStatus = (text, isSuccess) => {
            if (statusEl) {
                const icon = isSuccess ? '<i class="fa fa-check-circle" style="color: #4CAF50;"></i>' : '<i class="fa fa-spinner fa-spin"></i>';
                statusEl.innerHTML = `${icon} ${text}`;
            }
        };

        // Prevent auto-detection loops if already attempted in this tab session
        try {
            if (sessionStorage.getItem('auto_location_attempted') === 'true') {
                return;
            }
        } catch (e) {
            console.warn('sessionStorage is not accessible:', e);
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('loc_set')) {
            return;
        }

        // If manually cleared in this session, do not auto-detect
        let manualCleared = false;
        try {
            manualCleared = sessionStorage.getItem('manualLocationClear') === 'true';
        } catch (e) { }

        if (manualCleared) {
            if (statusEl) statusEl.style.display = 'none';
            return;
        }

        // Check if Geolocation permission is already granted so we can query GPS coordinates in the background.
        let gpsGranted = false;
        try {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Geolocation) {
                const permissionStatus = await window.Capacitor.Plugins.Geolocation.checkPermissions();
                gpsGranted = (permissionStatus.location === 'granted');
            } else if (navigator.permissions && navigator.permissions.query) {
                const status = await navigator.permissions.query({ name: 'geolocation' });
                gpsGranted = (status.state === 'granted');
            }
        } catch (e) { }

        // 1. Prioritize restoring from localStorage (persistent client-side preference)
        let localLoc = null;
        try {
            localLoc = localStorage.getItem('enteangadi_user_location');
        } catch (e) { }

        if (localLoc) {
            try {
                const parsed = JSON.parse(localLoc);
                if (parsed && parsed.name && parsed.lat && parsed.lng) {
                    EnteangadiConfig.hasLocation = true;
                    EnteangadiConfig.location = parsed;

                    // Sync the cookie as well
                    document.cookie = "user_location=" + encodeURIComponent(JSON.stringify(parsed)) + "; path=/; max-age=31536000; SameSite=Lax";

                    // Update header text in DOM
                    const headerTextEl = document.getElementById('current-location-text');
                    if (headerTextEl) {
                        const displayCity = parsed.name.split(',')[0];
                        headerTextEl.innerHTML = `${displayCity}`;
                    }

                    // Sync to server session in background (without page reload)
                    const formData = new FormData();
                    formData.append('action', 'set_location');
                    formData.append('location_name', parsed.name);
                    formData.append('latitude', parsed.lat);
                    formData.append('longitude', parsed.lng);

                    fetch(`${EnteangadiConfig.baseUrl}/api/location.php`, {
                        method: 'POST',
                        body: formData
                    }).catch(err => console.warn('Background location sync failed:', err));

                    return;
                }
            } catch (e) {
                console.error('Error parsing localStorage location:', e);
            }
        }

        // 2. If no localStorage exists, check if session already has a location
        // This prevents constant reload loops, saves battery, and speeds up page loading.
        // BUT if GPS permission is already granted, we bypass this return to verify precise coordinates in the background.
        if (!gpsGranted && typeof EnteangadiConfig !== 'undefined' && EnteangadiConfig.hasLocation && EnteangadiConfig.location) {
            const locObj = EnteangadiConfig.location;
            if (locObj && locObj.name && locObj.lat && locObj.lng) {
                try {
                    localStorage.setItem('enteangadi_user_location', JSON.stringify({ name: locObj.name, lat: locObj.lat, lng: locObj.lng }));
                } catch (e) { }
                document.cookie = "user_location=" + encodeURIComponent(JSON.stringify({ name: locObj.name, lat: locObj.lat, lng: locObj.lng })) + "; path=/; max-age=31536000; SameSite=Lax";
            }
            return;
        }

        // Mark as auto-location attempted in this tab session before starting Geolocation
        try {
            sessionStorage.setItem('auto_location_attempted', 'true');
        } catch (e) { }

        // Detect location in the background
        const headerTextEl = document.getElementById('current-location-text');
        // Capture original content of header text to restore if we fail
        const originalHTML = headerTextEl ? headerTextEl.innerHTML : 'Set Location';

        try {
            if (headerTextEl) {
                headerTextEl.innerHTML = `<span class="location-pulse-dot"></span> Locating...`;
            }
            updateStatus('Detecting location...', false);
            const coords = await this.getCurrentCoordinates();
            const lat = coords.lat;
            const lng = coords.lng;

            // Compare with currently set location to avoid infinite loops and unnecessary reloads
            if (typeof EnteangadiConfig !== 'undefined' && EnteangadiConfig.location) {
                const storedLat = parseFloat(EnteangadiConfig.location.lat);
                const storedLng = parseFloat(EnteangadiConfig.location.lng);

                if (storedLat && storedLng) {
                    const diffLat = Math.abs(lat - storedLat);
                    const diffLng = Math.abs(lng - storedLng);
                    if (diffLat < 0.002 && diffLng < 0.002) {
                        console.log('Location hasn\'t changed significantly. Skipping update.');
                        const locName = EnteangadiConfig.location.name || 'Current location';
                        updateStatus(`📍 ${locName.split(',')[0]} active`, true);

                        // Overwrite blank Set Location headers instantly
                        if (headerTextEl) {
                            const displayCity = locName.split(',')[0];
                            headerTextEl.innerHTML = `${displayCity}`;
                        }
                        return;
                    }
                }
            }

            // Always reverse-geocode coordinates if we have them, to get the precise village/locality
            if (lat && lng) {
                await this.reverseGeocodeAuto(lat, lng, originalHTML);
            } else if (coords.city) {
                updateStatus(`📍 ${coords.city} detected!`, true);
                await this.setLocation(coords.city, lat, lng);
            } else {
                throw new Error('No coordinates or city name detected');
            }
        } catch (error) {
            console.warn('Auto-location detection failed:', error.message);
            try {
                sessionStorage.setItem('auto_location_attempted', 'true');
            } catch (e) { }
            if (statusEl) {
                statusEl.innerHTML = '<i class="fa fa-exclamation-triangle" style="color: #FFB300;"></i> Selection fallback active';
            }
            // Restore original HTML if we fail
            if (headerTextEl) {
                headerTextEl.innerHTML = originalHTML;
            }
        }
    },

    async reverseGeocodeAuto(lat, lng, originalHTML) {
        const statusEl = document.getElementById('loader-location-status');
        const headerTextEl = document.getElementById('current-location-text');
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`);
            const data = await response.json();
            const city = this.extractLocality(data.address, 'Current Location');

            if (statusEl) {
                statusEl.innerHTML = `<i class="fa fa-check-circle" style="color: #4CAF50;"></i> 📍 ${city.split(',')[0]} detected!`;
            }
            await this.setLocation(city, lat, lng);
        } catch (error) {
            console.error('Reverse geocoding auto error:', error);
            if (statusEl) {
                statusEl.innerHTML = '<i class="fa fa-exclamation-triangle" style="color: #FFB300;"></i> Default location active';
            }
            // Restore original HTML if we fail
            if (headerTextEl && originalHTML) {
                headerTextEl.innerHTML = originalHTML;
            }
            await this.setLocation('Current Location', lat, lng);
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
