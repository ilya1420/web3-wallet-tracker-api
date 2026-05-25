import { UI_CONFIG } from './config.js';

export function createApiClient() {
  return {
    token: localStorage.getItem(UI_CONFIG.tokenStorageKey) || '',

    setToken(token) {
      this.token = token;
      if (token) {
        localStorage.setItem(UI_CONFIG.tokenStorageKey, token);
      } else {
        localStorage.removeItem(UI_CONFIG.tokenStorageKey);
      }
    },

    async request(path, options = {}) {
      const headers = {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
      };

      if (this.token) {
        headers.Authorization = `Bearer ${this.token}`;
      }

      const response = await fetch(`${UI_CONFIG.apiBaseUrl}${path}`, {
        ...options,
        headers,
      });

      const text = await response.text();
      let payload = null;

      if (text !== '') {
        try {
          payload = JSON.parse(text);
        } catch (error) {
          throw new Error(`API returned non-JSON response for ${path}.`);
        }
      }

      if (!response.ok) {
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }

      return payload;
    },
  };
}
