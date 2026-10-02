/**
 * CRM Users Administration Controller
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const usersApp = document.getElementById('usersApp');
    if (!usersApp) return;

    const currentUserId = parseInt(usersApp.dataset.currentUserId || '0', 10);
    let allUsers = [];

    // Modals
    const createModalEl = document.getElementById('createUserModal');
    const editModalEl = document.getElementById('editUserModal');
    const resetModalEl = document.getElementById('resetPasswordModal');
    const deleteModalEl = document.getElementById('deleteUserModal');

    const createModal = createModalEl && window.bootstrap ? new bootstrap.Modal(createModalEl) : null;
    const editModal = editModalEl && window.bootstrap ? new bootstrap.Modal(editModalEl) : null;
    const resetModal = resetModalEl && window.bootstrap ? new bootstrap.Modal(resetModalEl) : null;
    const deleteModal = deleteModalEl && window.bootstrap ? new bootstrap.Modal(deleteModalEl) : null;

    // Elements
    const tableBody = document.getElementById('usersTableBody');
    const searchInput = document.getElementById('userSearchInput');
    const filterRole = document.getElementById('filterRole');
    const filterStatus = document.getElementById('filterStatus');
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');

    // Forms
    const createForm = document.getElementById('createUserForm');
    const editForm = document.getElementById('editUserForm');
    const resetForm = document.getElementById('resetPasswordForm');
    const confirmDeleteBtn = document.getElementById('confirmDeleteUserBtn');

    let targetDeleteUserId = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(dtStr) {
        if (!dtStr) return '—';
        try {
            const d = new Date(dtStr.replace(/-/g, '/'));
            if (isNaN(d.getTime())) return dtStr;
            return d.toLocaleDateString('en-IN', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        } catch {
            return dtStr;
        }
    }

    // Load users from API
    async function loadUsers() {
        try {
            const res = await api.get('/api/users', { per_page: 100 });
            if (res && res.data) {
                allUsers = res.data.items || [];
                renderTable();
            }
        } catch (e) {
            console.error('Failed to load users:', e);
            if (tableBody) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load users. <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="retryLoadBtn">Retry</button></td></tr>`;
                document.getElementById('retryLoadBtn')?.addEventListener('click', loadUsers);
            }
        }
    }

    function renderTable() {
        if (!tableBody) return;

        const search = (searchInput?.value || '').trim().toLowerCase();
        const role = (filterRole?.value || '').trim().toLowerCase();
        const status = (filterStatus?.value || '').trim();

        const filtered = allUsers.filter(u => {
            if (search) {
                const nameMatch = (u.name || '').toLowerCase().includes(search);
                const emailMatch = (u.email || '').toLowerCase().includes(search);
                if (!nameMatch && !emailMatch) return false;
            }
            if (role) {
                if ((u.role_name || '').toLowerCase() !== role) return false;
            }
            if (status !== '') {
                if (String(u.is_active) !== status) return false;
            }
            return true;
        });

        if (filtered.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No users found matching current filters.</td></tr>`;
            return;
        }

        tableBody.innerHTML = filtered.map(u => {
            const isSelf = currentUserId > 0 && parseInt(u.id, 10) === currentUserId;
            const isActive = parseInt(u.is_active, 10) === 1;

            let roleBadge = '<span class="badge bg-secondary">User</span>';
            const rName = (u.role_name || '').toLowerCase();
            if (rName === 'admin') {
                roleBadge = '<span class="badge bg-primary">Administrator</span>';
            } else if (rName === 'manager') {
                roleBadge = '<span class="badge bg-info text-dark">Manager</span>';
            } else if (rName === 'sales') {
                roleBadge = '<span class="badge bg-secondary">Sales</span>';
            }

            const statusBadge = isActive
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border">Inactive</span>';

            const initial = (u.name || 'U').charAt(0).toUpperCase();

            // Self-action guards
            const toggleStatusBtn = isSelf
                ? `<button type="button" class="btn btn-sm btn-outline-secondary opacity-50" disabled title="You cannot deactivate your own account">Deactivate</button>`
                : (isActive
                    ? `<button type="button" class="btn btn-sm btn-outline-warning btn-toggle-status" data-id="${u.id}" data-action="0" title="Deactivate Account">Deactivate</button>`
                    : `<button type="button" class="btn btn-sm btn-outline-success btn-toggle-status" data-id="${u.id}" data-action="1" title="Activate Account">Activate</button>`
                );

            const deleteBtn = isSelf
                ? `<button type="button" class="btn btn-sm btn-outline-danger opacity-50" disabled title="You cannot delete your own account">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                   </button>`
                : `<button type="button" class="btn btn-sm btn-outline-danger btn-delete-user" data-id="${u.id}" data-name="${escapeHtml(u.name)}" data-email="${escapeHtml(u.email)}" title="Delete User">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                   </button>`;

            return `
                <tr data-user-id="${u.id}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 13px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                ${escapeHtml(initial)}
                            </div>
                            <div>
                                <div class="fw-semibold text-dark">
                                    ${escapeHtml(u.name)}
                                    ${isSelf ? '<span class="badge bg-light text-primary border ms-1">You</span>' : ''}
                                </div>
                                ${u.mobile ? `<small class="text-muted">${escapeHtml(u.mobile)}</small>` : ''}
                            </div>
                        </div>
                    </td>
                    <td><span class="text-secondary">${escapeHtml(u.email)}</span></td>
                    <td>${roleBadge}</td>
                    <td>${statusBadge}</td>
                    <td><small class="text-muted">${formatDate(u.created_at)}</small></td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-user" data-id="${u.id}" title="Edit User">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-reset-password" data-id="${u.id}" data-name="${escapeHtml(u.name)}" data-email="${escapeHtml(u.email)}" title="Reset Password">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            </button>
                            ${toggleStatusBtn}
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        attachRowEvents();
    }

    function attachRowEvents() {
        // Edit User button
        document.querySelectorAll('.btn-edit-user').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                const user = allUsers.find(u => parseInt(u.id, 10) === id);
                if (!user) return;

                const form = document.getElementById('editUserForm');
                if (!form) return;

                UI.clearErrors(form);
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editName').value = user.name || '';
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editRole').value = user.role_id;
                document.getElementById('editMobile').value = user.mobile || '';

                const statusSelect = document.getElementById('editStatus');
                const selfNote = document.getElementById('selfDeactivateNote');
                statusSelect.value = user.is_active;

                const isSelf = currentUserId > 0 && id === currentUserId;
                if (isSelf) {
                    statusSelect.disabled = true;
                    selfNote?.classList.remove('d-none');
                } else {
                    statusSelect.disabled = false;
                    selfNote?.classList.add('d-none');
                }

                editModal?.show();
            });
        });

        // Reset Password button
        document.querySelectorAll('.btn-reset-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const name = btn.dataset.name;
                const email = btn.dataset.email;

                const form = document.getElementById('resetPasswordForm');
                if (form) {
                    UI.clearErrors(form);
                    form.reset();
                }
                document.getElementById('resetPasswordUserId').value = id;
                document.getElementById('resetPasswordUserName').textContent = name;
                document.getElementById('resetPasswordUserEmail').textContent = email;

                resetModal?.show();
            });
        });

        // Activate / Deactivate button
        document.querySelectorAll('.btn-toggle-status').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const newStatus = parseInt(btn.dataset.action, 10);
                const user = allUsers.find(u => parseInt(u.id, 10) === id);

                if (currentUserId > 0 && id === currentUserId && newStatus === 0) {
                    UI.toast('You cannot deactivate your own account.', 'warning', 'Action Prohibited');
                    return;
                }

                btn.disabled = true;
                try {
                    await api.put(`/api/users/${id}`, { is_active: newStatus });
                    UI.toast(newStatus === 1 ? 'User activated successfully.' : 'User deactivated successfully.', 'success', 'Status Updated');
                    await loadUsers();
                } catch (e) {
                    UI.toast(e.message || 'Failed to update user status.', 'danger', 'Error');
                    btn.disabled = false;
                }
            });
        });

        // Delete User button
        document.querySelectorAll('.btn-delete-user').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                if (currentUserId > 0 && id === currentUserId) {
                    UI.toast('You cannot delete your own account.', 'warning', 'Action Prohibited');
                    return;
                }

                targetDeleteUserId = id;
                document.getElementById('deleteUserName').textContent = btn.dataset.name || 'User';
                document.getElementById('deleteUserEmail').textContent = btn.dataset.email || '';
                deleteModal?.show();
            });
        });
    }

    // Filter events
    searchInput?.addEventListener('input', renderTable);
    filterRole?.addEventListener('change', renderTable);
    filterStatus?.addEventListener('change', renderTable);
    resetFiltersBtn?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (filterRole) filterRole.value = '';
        if (filterStatus) filterStatus.value = '';
        renderTable();
    });

    // Create User Form Submit
    createForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        UI.clearErrors(createForm);

        const submitBtn = document.getElementById('saveCreateUserBtn');
        const name = (document.getElementById('createName')?.value || '').trim();
        const email = (document.getElementById('createEmail')?.value || '').trim();
        const roleId = document.getElementById('createRole')?.value;
        const mobile = (document.getElementById('createMobile')?.value || '').trim();
        const password = document.getElementById('createPassword')?.value || '';
        const isActive = document.getElementById('createIsActive')?.checked ? 1 : 0;

        // Validation
        const clientErrors = {};
        if (!name || name.length < 2) clientErrors.name = 'Name must be at least 2 characters.';
        if (!email) clientErrors.email = 'Email address is required.';
        if (!roleId) clientErrors.role_id = 'Please select a role.';
        if (!password || password.length < 8) {
            clientErrors.password = 'Password must be at least 8 characters.';
        } else if (!/[A-Za-z]/.test(password) || !/\d/.test(password)) {
            clientErrors.password = 'Password must contain both letters and numbers.';
        }

        if (Object.keys(clientErrors).length > 0) {
            UI.showFieldErrors(createForm, clientErrors);
            return;
        }

        UI.buttonLoading(submitBtn, true, 'Creating...');

        try {
            await api.post('/api/users', {
                name,
                email,
                role_id: parseInt(roleId, 10),
                mobile: mobile || null,
                password,
                is_active: isActive
            });

            UI.toast('User created successfully.', 'success', 'Success');
            createModal?.hide();
            createForm.reset();
            await loadUsers();
        } catch (err) {
            if (err.errors) {
                UI.showFieldErrors(createForm, err.errors);
            } else {
                UI.toast(err.message || 'Failed to create user.', 'danger', 'Error');
            }
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Edit User Form Submit
    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        UI.clearErrors(editForm);

        const submitBtn = document.getElementById('saveEditUserBtn');
        const id = parseInt(document.getElementById('editUserId')?.value || '0', 10);
        const name = (document.getElementById('editName')?.value || '').trim();
        const email = (document.getElementById('editEmail')?.value || '').trim();
        const roleId = document.getElementById('editRole')?.value;
        const mobile = (document.getElementById('editMobile')?.value || '').trim();
        const statusSelect = document.getElementById('editStatus');
        const isActive = parseInt(statusSelect?.value || '1', 10);

        const clientErrors = {};
        if (!name || name.length < 2) clientErrors.name = 'Name must be at least 2 characters.';
        if (!email) clientErrors.email = 'Email address is required.';
        if (!roleId) clientErrors.role_id = 'Please select a role.';

        if (Object.keys(clientErrors).length > 0) {
            UI.showFieldErrors(editForm, clientErrors);
            return;
        }

        UI.buttonLoading(submitBtn, true, 'Saving...');

        const payload = {
            name,
            email,
            role_id: parseInt(roleId, 10),
            mobile: mobile || null
        };

        // Only send is_active if not disabled (self)
        if (!statusSelect?.disabled) {
            payload.is_active = isActive;
        }

        try {
            await api.put(`/api/users/${id}`, payload);
            UI.toast('User updated successfully.', 'success', 'Success');
            editModal?.hide();
            await loadUsers();
        } catch (err) {
            if (err.errors) {
                UI.showFieldErrors(editForm, err.errors);
            } else {
                UI.toast(err.message || 'Failed to update user.', 'danger', 'Error');
            }
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Reset Password Form Submit
    resetForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        UI.clearErrors(resetForm);

        const submitBtn = document.getElementById('submitResetPasswordBtn');
        const id = parseInt(document.getElementById('resetPasswordUserId')?.value || '0', 10);
        const password = document.getElementById('newPasswordInput')?.value || '';

        if (!password || password.length < 8) {
            UI.showFieldErrors(resetForm, { password: 'Password must be at least 8 characters.' });
            return;
        }
        if (!/[A-Za-z]/.test(password) || !/\d/.test(password)) {
            UI.showFieldErrors(resetForm, { password: 'Password must contain both letters and numbers.' });
            return;
        }

        UI.buttonLoading(submitBtn, true, 'Resetting...');

        try {
            await api.put(`/api/users/${id}`, { password });
            UI.toast('Password reset successfully.', 'success', 'Password Updated');
            resetModal?.hide();
            resetForm.reset();
        } catch (err) {
            if (err.errors) {
                UI.showFieldErrors(resetForm, err.errors);
            } else {
                UI.toast(err.message || 'Failed to reset password.', 'danger', 'Error');
            }
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Delete User Confirmation
    confirmDeleteBtn?.addEventListener('click', async () => {
        if (!targetDeleteUserId) return;

        UI.buttonLoading(confirmDeleteBtn, true, 'Deleting...');

        try {
            await api.delete(`/api/users/${targetDeleteUserId}`);
            UI.toast('User deleted successfully.', 'success', 'Deleted');
            deleteModal?.hide();
            targetDeleteUserId = null;
            await loadUsers();
        } catch (err) {
            UI.toast(err.message || 'Failed to delete user.', 'danger', 'Error');
        } finally {
            UI.buttonLoading(confirmDeleteBtn, false);
        }
    });

    // Initial load
    loadUsers();
});
