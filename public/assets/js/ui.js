/**
 * CRM UI Utility Helpers
 */
(function (window) {
    'use strict';

    const UI = {
        /**
         * Show Bootstrap Toast notification
         * @param {string} message 
         * @param {'success'|'danger'|'warning'|'info'} type 
         * @param {string} [title] 
         */
        toast(message, type = 'info', title = '') {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '1090';
                document.body.appendChild(container);
            }

            const bgClasses = {
                success: 'text-bg-success',
                danger: 'text-bg-danger',
                warning: 'text-bg-warning',
                info: 'text-bg-primary'
            };

            const bgClass = bgClasses[type] || 'text-bg-secondary';
            const toastId = 'toast_' + Date.now();

            const toastEl = document.createElement('div');
            toastEl.id = toastId;
            toastEl.className = `toast align-items-center ${bgClass} border-0 shadow`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');

            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${title ? `<strong>${UI.escape(title)}</strong><br>` : ''}
                        ${UI.escape(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;

            container.appendChild(toastEl);
            if (window.bootstrap && typeof window.bootstrap.Toast === 'function') {
                const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
                bsToast.show();

                toastEl.addEventListener('hidden.bs.toast', () => {
                    toastEl.remove();
                });
            } else {
                setTimeout(() => toastEl.remove(), 4000);
            }
        },

        /**
         * Control global loading progress bar
         * @param {boolean} show 
         */
        showLoader(show = true) {
            let bar = document.getElementById('topProgressBar');
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'topProgressBar';
                document.body.appendChild(bar);
            }

            if (show) {
                bar.style.opacity = '1';
                bar.style.width = '70%';
            } else {
                bar.style.width = '100%';
                setTimeout(() => {
                    bar.style.opacity = '0';
                    setTimeout(() => {
                        bar.style.width = '0';
                    }, 300);
                }, 150);
            }
        },

        /**
         * Manage button loading spinner state
         * @param {HTMLButtonElement|string} btn 
         * @param {boolean} isLoading 
         * @param {string} [loadingText] 
         */
        buttonLoading(btn, isLoading, loadingText = '') {
            const button = typeof btn === 'string' ? document.querySelector(btn) : btn;
            if (!button) return;

            if (isLoading) {
                if (!button.dataset.origHtml) {
                    button.dataset.origHtml = button.innerHTML;
                }
                button.disabled = true;
                const text = loadingText || button.textContent.trim();
                button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${UI.escape(text)}`;
            } else {
                button.disabled = false;
                if (button.dataset.origHtml) {
                    button.innerHTML = button.dataset.origHtml;
                    delete button.dataset.origHtml;
                }
            }
        },

        /**
         * Render field-level validation errors on a form
         * @param {HTMLFormElement|string} form 
         * @param {Object.<string, string>} errors 
         */
        showFieldErrors(form, errors) {
            const formEl = typeof form === 'string' ? document.querySelector(form) : form;
            if (!formEl || !errors) return;

            UI.clearErrors(formEl);

            for (const [field, message] of Object.entries(errors)) {
                const input = formEl.querySelector(`[name="${field}"]`) || formEl.querySelector(`#${field}`);
                if (!input) continue;

                input.classList.add('is-invalid');

                let feedback = input.nextElementSibling;
                if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                    feedback = formEl.querySelector(`#${field}Error`) || input.parentElement.querySelector('.invalid-feedback');
                }

                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    input.parentNode.appendChild(feedback);
                }

                feedback.textContent = message;
            }
        },

        /**
         * Clear validation errors from form
         * @param {HTMLFormElement|string} form 
         */
        clearErrors(form) {
            const formEl = typeof form === 'string' ? document.querySelector(form) : form;
            if (!formEl) return;

            formEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            formEl.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        },

        escape(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    window.UI = UI;

    // Layout Global Interactivity (Sidebar toggle & logout)
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggleBtn = document.getElementById('sidebarToggleBtn');

        function toggleSidebar() {
            if (sidebar) sidebar.classList.toggle('show');
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebar);
        }
        if (backdrop && sidebar) {
            backdrop.addEventListener('click', () => sidebar.classList.remove('show'));
        }

        const logoutBtn = document.getElementById('sidebarLogoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                UI.buttonLoading(logoutBtn, true, 'Signing out...');
                try {
                    if (window.api && typeof window.api.post === 'function') {
                        await window.api.post('/api/auth/logout');
                    }
                    window.location.href = '/login';
                } catch (e) {
                    window.location.href = '/login';
                }
            });
        }
    });
})(window);
