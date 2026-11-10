/**
 * Pterodactyl Panel - Core Application JavaScript (Vanilla ES6+)
 */

const App = (() => {
    // Read CSRF Token from meta tag
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // Generic Fetch API wrapper with CSRF injection
    const request = async (url, options = {}) => {
        const method = (options.method || 'GET').toUpperCase();
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(options.headers || {})
        };

        let body = options.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        const res = await fetch(url, {
            method,
            headers,
            body
        });

        // Handle No Content response
        if (res.status === 204) {
            return null;
        }

        const contentType = res.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            data = await res.text();
        }

        if (!res.ok) {
            const err = (data && typeof data === 'object') ? (data.error || data.message || (data.errors && data.errors[0]?.detail)) : data;
            const errorObj = new Error(err || `HTTP error ${res.status}`);
            errorObj.status = res.status;
            errorObj.response = data;
            throw errorObj;
        }

        return data;
    };

    // Modal Manager
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            const autofocusEl = modal.querySelector('[autofocus], input:not([type=hidden]), select, textarea');
            if (autofocusEl) {
                setTimeout(() => autofocusEl.focus(), 50);
            }
        }
    };

    const closeModal = (idOrEl) => {
        const modal = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl.closest('.modal-backdrop');
        if (modal) {
            modal.classList.remove('active');
        }
    };

    // Floating Toast Notification
    const toast = (message, type = 'info', duration = 3500) => {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        
        let icon = 'ℹ';
        if (type === 'success') icon = '✓';
        if (type === 'danger' || type === 'error') icon = '⚠';

        el.innerHTML = `<span style="font-weight: 700;">${icon}</span> <span>${escapeHtml(message)}</span>`;
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            setTimeout(() => el.remove(), 300);
        }, duration);
    };

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    // Global Event Listeners
    document.addEventListener('DOMContentLoaded', () => {
        // Modal close buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-modal-close]') || e.target.closest('[data-modal-close]')) {
                closeModal(e.target);
            } else if (e.target.classList.contains('modal-backdrop')) {
                // Click outside modal dialog closes it
                e.target.classList.remove('active');
            }
        });

        // Escape key closes modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active'));
            }
        });
    });

    return {
        getCsrfToken,
        request,
        openModal,
        closeModal,
        toast,
        escapeHtml
    };
})();

window.App = App;
