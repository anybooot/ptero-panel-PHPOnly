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

        if (data && typeof data === 'object' && data.error && (data.error.includes('Unable to reach the node') || data.error.includes('Wings'))) {
            data.error = 'Unable to reach the node.';
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

    // Robust Copy to Clipboard with fallback for HTTP / non-secure contexts
    const copyToClipboard = async (text, targetEl = null) => {
        if (!text || text === 'No allocation' || text === '-') return false;
        let success = false;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                success = true;
            }
        } catch (e) {}

        if (!success) {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                ta.style.top = '-9999px';
                ta.setAttribute('readonly', '');
                document.body.appendChild(ta);
                ta.select();
                ta.setSelectionRange(0, 99999);
                success = document.execCommand('copy');
                document.body.removeChild(ta);
            } catch (err) {
                success = false;
            }
        }

        if (success) {
            toast(`Address ${text} copied to clipboard!`, 'success', 2500);
            if (targetEl) {
                const icon = targetEl.querySelector('.server-address-icon, #console-ip-copy-icon, .server-ip-copy-icon') || (targetEl.tagName === 'SPAN' || targetEl.tagName === 'BUTTON' ? targetEl : null);
                if (icon) {
                    const originalHtml = icon.innerHTML;
                    icon.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
                    setTimeout(() => {
                        icon.innerHTML = originalHtml;
                    }, 1500);
                }
            }
        } else {
            toast('Failed to copy to clipboard', 'danger');
        }
        return success;
    };

    // Suppress unhandled rejections and console errors for node unreachability to keep browser console clean
    window.addEventListener('unhandledrejection', (e) => {
        const reason = e.reason;
        const msg = (reason && (reason.message || reason.error || String(reason))) || '';
        if (msg.includes('Unable to reach the node') || msg.includes('Wings') || msg.includes('node is currently unavailable')) {
            e.preventDefault();
        }
    });

    try {
        const origConsoleError = console.error;
        console.error = function(...args) {
            const str = args.map(a => (typeof a === 'object' && a !== null ? (a.message || JSON.stringify(a)) : String(a))).join(' ');
            if (str.includes('Unable to reach the node') || str.includes('Wings') || str.includes('node is currently unavailable')) {
                return;
            }
            origConsoleError.apply(console, args);
        };
    } catch (e) {}

    // Custom Confirmation Dialog (Replaces native browser window.confirm popup)
    const confirm = (message, options = {}) => {
        return new Promise((resolve) => {
            const modal = document.getElementById('modal-app-confirm');
            const titleEl = document.getElementById('app-confirm-title-text');
            const msgEl = document.getElementById('app-confirm-message');
            const okBtn = document.getElementById('app-confirm-btn-ok');
            const cancelBtn = document.getElementById('app-confirm-btn-cancel');
            const closeBtn = document.getElementById('app-confirm-close-btn');

            if (!modal || !okBtn || !cancelBtn) {
                resolve(true);
                return;
            }

            if (titleEl) titleEl.textContent = options.title || 'Confirm Action';
            if (msgEl) msgEl.textContent = message;
            okBtn.textContent = options.confirmText || 'Confirm';
            okBtn.className = `btn btn-sm ${options.danger === false ? 'btn-primary' : 'btn-danger'}`;

            modal.classList.add('active');

            let resolved = false;
            const finish = (val) => {
                if (resolved) return;
                resolved = true;
                modal.classList.remove('active');
                okBtn.removeEventListener('click', handleOk);
                cancelBtn.removeEventListener('click', handleCancel);
                if (closeBtn) closeBtn.removeEventListener('click', handleCancel);
                document.removeEventListener('keydown', handleKey);
                resolve(val);
            };

            const handleOk = () => finish(true);
            const handleCancel = () => finish(false);
            const handleKey = (e) => {
                if (e.key === 'Escape') finish(false);
            };

            okBtn.addEventListener('click', handleOk);
            cancelBtn.addEventListener('click', handleCancel);
            if (closeBtn) closeBtn.addEventListener('click', handleCancel);
            document.addEventListener('keydown', handleKey);
        });
    };

    // Override native browser alert to use custom toast
    window.alert = (msg) => {
        toast(String(msg), 'info');
    };

    // Global Event Listeners
    document.addEventListener('DOMContentLoaded', () => {
        // Intercept form submissions with confirmation dialogs to prevent native popups
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.dataset.confirmed === 'true') {
                if (form) delete form.dataset.confirmed;
                return;
            }

            let msg = form.getAttribute('data-confirm');
            const onsubmitAttr = form.getAttribute('onsubmit') || '';
            if (!msg && onsubmitAttr.includes('confirm(')) {
                const match = onsubmitAttr.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/);
                if (match) {
                    msg = match[2];
                }
            }

            if (msg) {
                e.preventDefault();
                e.stopImmediatePropagation();
                confirm(msg).then((ok) => {
                    if (ok) {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                });
                return false;
            }
        }, true);

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

        // Account dropdown in top navigation
        const userDropdownBtn = document.getElementById('user-dropdown-btn');
        const userDropdownMenu = document.getElementById('user-dropdown-menu');
        if (userDropdownBtn && userDropdownMenu) {
            userDropdownBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isShown = userDropdownMenu.style.display === 'block';
                userDropdownMenu.style.display = isShown ? 'none' : 'block';
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.user-dropdown-container')) {
                    userDropdownMenu.style.display = 'none';
                }
            });
        }

        // Global Custom Tooltips (Point 9)
        let tooltipEl = document.getElementById('app-custom-tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'app-custom-tooltip';
            tooltipEl.className = 'custom-tooltip';
            document.body.appendChild(tooltipEl);
        }

        let tipTimer = null;
        const showCustomTip = (target, text) => {
            if (!text || !tooltipEl) return;
            tooltipEl.textContent = text;
            tooltipEl.className = 'custom-tooltip';

            const rect = target.getBoundingClientRect();
            const tipRect = tooltipEl.getBoundingClientRect();

            let top = rect.top - tipRect.height - 8;
            let left = rect.left + (rect.width / 2) - (tipRect.width / 2);

            let arrowTop = false;
            if (top < 6) {
                top = rect.bottom + 8;
                arrowTop = true;
            }

            if (left < 6) left = 6;
            if (left + tipRect.width > window.innerWidth - 6) {
                left = window.innerWidth - tipRect.width - 6;
            }

            tooltipEl.style.top = `${top}px`;
            tooltipEl.style.left = `${left}px`;
            if (arrowTop) tooltipEl.classList.add('arrow-top');

            requestAnimationFrame(() => {
                tooltipEl.classList.add('active');
            });
        };

        const hideCustomTip = () => {
            clearTimeout(tipTimer);
            if (tooltipEl) tooltipEl.classList.remove('active');
        };

        // Intercept title and data-tooltip on mouseover / pointerdown / hold
        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest('[title], [data-tooltip]');
            if (!target) return;

            if (target.hasAttribute('title')) {
                const titleVal = target.getAttribute('title');
                target.setAttribute('data-tooltip', titleVal);
                target.removeAttribute('title');
            }

            const tipText = target.getAttribute('data-tooltip');
            if (!tipText) return;

            clearTimeout(tipTimer);
            tipTimer = setTimeout(() => {
                showCustomTip(target, tipText);
            }, 60);
        });

        document.addEventListener('mouseout', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                hideCustomTip();
            }
        });

        document.addEventListener('mousedown', (e) => {
            const target = e.target.closest('[data-tooltip], [title]');
            if (target) {
                if (target.hasAttribute('title')) {
                    const titleVal = target.getAttribute('title');
                    target.setAttribute('data-tooltip', titleVal);
                    target.removeAttribute('title');
                }
                const tipText = target.getAttribute('data-tooltip');
                if (tipText) {
                    clearTimeout(tipTimer);
                    showCustomTip(target, tipText);
                }
            }
        });

        document.addEventListener('click', () => hideCustomTip());
        window.addEventListener('scroll', () => hideCustomTip(), true);
    });

    return {
        getCsrfToken,
        request,
        openModal,
        closeModal,
        toast,
        escapeHtml,
        copyToClipboard,
        copy: copyToClipboard,
        confirm
    };
})();

window.App = App;
window.copyToClipboard = App.copyToClipboard;
