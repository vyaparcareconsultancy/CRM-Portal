/**
 * CRM Centralized API Fetch Client
 */
(function (window) {
    'use strict';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        const input = document.querySelector('input[name="_csrf_token"]');
        if (input && input.value) {
            return input.value;
        }
        return '';
    }

    async function request(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrfToken(),
            ...(options.headers || {})
        };

        let body = options.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        if (window.UI && typeof window.UI.showLoader === 'function') {
            window.UI.showLoader(true);
        }

        try {
            // Guarantee same-origin credentials for session cookie authentication
            const response = await fetch(url, {
                ...options,
                method,
                headers,
                body,
                credentials: 'same-origin'
            });

            if (window.UI && typeof window.UI.showLoader === 'function') {
                window.UI.showLoader(false);
            }

            let data;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                data = { status: response.ok ? 'success' : 'error', message: text, data: null };
            }

            // Global Status Code Handlers
            switch (response.status) {
                case 200:
                case 201:
                    if (data && data.status === 'error') {
                        return Promise.reject(data);
                    }
                    return data;

                case 401:
                    if (window.UI) {
                        window.UI.toast('Session expired. Redirecting to login...', 'warning', 'Unauthorized');
                    }
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 1000);
                    return Promise.reject(data);

                case 403:
                    if (window.UI) {
                        window.UI.toast(data?.message || 'Access Forbidden: Insufficient permissions.', 'danger', 'Forbidden');
                    }
                    return Promise.reject(data);

                case 404:
                    return Promise.reject(data || { status: 'error', message: 'Requested resource not found' });

                case 422:
                    if (window.UI) {
                        window.UI.toast(data?.message || 'Please correct the highlighted form errors.', 'warning', 'Validation Error');
                    }
                    return Promise.reject(data);

                case 429: {
                    const retryAfter = response.headers.get('retry-after');
                    const msg = data?.message || (retryAfter ? `Too many requests, try after ${retryAfter} seconds` : 'Too many requests, try after a few seconds');
                    if (window.UI) {
                        window.UI.toast(msg, 'danger', 'Rate Limit Exceeded');
                    }
                    return Promise.reject(data);
                }

                case 500:
                default:
                    if (!response.ok) {
                        if (window.UI) {
                            window.UI.toast(data?.message || 'An unexpected server error occurred.', 'danger', 'Server Error');
                        }
                        return Promise.reject(data);
                    }
                    return data;
            }
        } catch (error) {
            if (window.UI && typeof window.UI.showLoader === 'function') {
                window.UI.showLoader(false);
            }

            if (window.UI && error?.message && error.status !== 'error') {
                window.UI.toast(error.message || 'Network connection error.', 'danger', 'Network Error');
            }

            return Promise.reject(error);
        }
    }

    const api = {
        get(url, params = {}) {
            const query = new URLSearchParams(params).toString();
            const fullUrl = query ? `${url}?${query}` : url;
            return request(fullUrl, { method: 'GET' });
        },

        post(url, body = {}) {
            return request(url, { method: 'POST', body });
        },

        put(url, body = {}) {
            return request(url, { method: 'PUT', body });
        },

        delete(url, body = {}) {
            return request(url, { method: 'DELETE', body });
        },

        request
    };

    window.api = api;
})(window);
