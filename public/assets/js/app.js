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
    const toast = (message, type = 'info', duration = 3800) => {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className = `toast toast-${type}`;

        let iconSvg = '';
        let title = 'Notification';
        if (type === 'success') {
            title = 'Success';
            iconSvg = `<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>`;
        } else if (type === 'danger' || type === 'error') {
            title = 'Error';
            iconSvg = `<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>`;
        } else {
            title = 'Information';
            iconSvg = `<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>`;
        }

        el.innerHTML = `
            <div class="toast-icon-wrap">${iconSvg}</div>
            <div class="toast-body-wrap">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${escapeHtml(message)}</div>
            </div>
            <button type="button" class="toast-close" onclick="this.closest('.toast').remove()">&times;</button>
        `;
        container.appendChild(el);

        let timeout = setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(12px) scale(0.96)';
            setTimeout(() => el.remove(), 250);
        }, duration);

        el.addEventListener('mouseenter', () => clearTimeout(timeout));
        el.addEventListener('mouseleave', () => {
            timeout = setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(12px) scale(0.96)';
                setTimeout(() => el.remove(), 250);
            }, 1200);
        });
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
