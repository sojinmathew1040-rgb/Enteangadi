// client/src/utils/apiClient.js

// Replace this with your computer's local LAN IP (e.g., '192.168.1.15') 
// when testing on a physical mobile device connected to the same Wi-Fi network.
const HOST_IP = '192.168.1.3';

/**
 * Resolves the backend base URL dynamically depending on whether the app
 * is running in a desktop browser, an Android emulator, or a physical mobile device.
 */
export const getBaseUrl = () => {
  const isCapacitor = window.Capacitor !== undefined || window.hasOwnProperty('Capacitor');

  if (isCapacitor) {
    // Use the provided local IP for both physical devices and emulators
    return `http://${HOST_IP}/Enteangadi`;
  }

  // Web browser development (usually localhost)
  return `http://${window.location.hostname}/Enteangadi`;
};

/**
 * Universal wrapper for API calls to the PHP backend.
 * Handles automatic headers, base URL resolution, and authentication tokens.
 * 
 * @param {string} endpoint - The target endpoint (e.g., 'api/products.php' or '/api/location.php')
 * @param {object} options - Fetch options (method, body, headers, etc.)
 */
export const apiFetch = async (endpoint, options = {}) => {
  const baseUrl = getBaseUrl();
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  const url = `${baseUrl}${cleanEndpoint}`;

  const headers = {
    'Accept': 'application/json',
    ...options.headers,
  };

  // If a request payload is provided, format it as JSON by default
  if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(options.body);
  }

  // Inject user session/auth token if available (for future JWT implementation)
  const token = localStorage.getItem('auth_token');
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(url, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`API Error (${response.status}): ${text || response.statusText}`);
  }

  return response.json();
};
