const HOST_IP = 'enteangadi.com'; // Set to 'enteangadi.com' for production

/**
 * Resolves the backend base URL dynamically depending on whether the app
 * is running in a desktop browser, an Android emulator, or a physical mobile device.
 */
export const getBaseUrl = () => {
  const isCapacitor = window.Capacitor !== undefined || window.hasOwnProperty('Capacitor');

  if (isCapacitor) {
    const isLocal = (HOST_IP.includes('192.168.') || HOST_IP.includes('10.0.2.2') || HOST_IP === 'localhost');
    const protocol = isLocal ? 'http' : 'https';
    const pathSuffix = isLocal ? '/Enteangadi' : '';
    return `${protocol}://${HOST_IP}${pathSuffix}`;
  }

  // Web browser production or development (resolves protocol and hostname dynamically)
  const isLocal = (window.location.hostname.includes('192.168.') || window.location.hostname.includes('10.0.2.2') || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
  const pathSuffix = isLocal ? '/Enteangadi' : '';
  return `${window.location.protocol}//${window.location.hostname}${pathSuffix}`;
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
    credentials: 'include',
    ...options,
    headers,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`API Error (${response.status}): ${text || response.statusText}`);
  }

  return response.json();
};
