/**
 * Clients Directory Controller
 */
document.addEventListener('DOMContentLoaded', async () => {
    'use strict';

    let clientsTable;
    let targetDeleteId = null;

    // 1. Populate lookups for filter bar
    async function loadLookups() {
        try {
            const lookupsRes = await api.get('/api/lookups');
            if (lookupsRes && lookupsRes.data) {
                const statesSelect = document.getElementById('filterState');
                if (statesSelect && lookupsRes.data.states) {
                    statesSelect.innerHTML = '<option value="">All States</option>';
                    lookupsRes.data.states.forEach(st => {
                        const opt = document.createElement('option');
                        opt.value = st;
                        opt.textContent = st;
                        statesSelect.appendChild(opt);
                    });
                }

                const sourceSelect = document.getElementById('filterLeadSource');
                if (sourceSelect && lookupsRes.data.lead_sources) {
                    sourceSelect.innerHTML = '<option value="">All Sources</option>';
                    lookupsRes.data.lead_sources.forEach(src => {
                        const opt = document.createElement('option');
                        opt.value = src;
                        opt.textContent = src;
                        sourceSelect.appendChild(opt);
                    });
                }

                const staffSelect = document.getElementById('filterAssignedTo');
                if (staffSelect && lookupsRes.data.staff) {
                    staffSelect.innerHTML = '<option value="">All Staff</option>';
                    lookupsRes.data.staff.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.name;
                        staffSelect.appendChild(opt);
                    });
                }
            }
        } catch (e) {
            console.warn('Could not load lookup values for filter dropdowns', e);
        }
    }

    await loadLookups();

    // 2. Initialize DataTables with server-side processing
    if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        const tbody = document.querySelector('#clientsDataTable tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">DataTables library failed to load. <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="window.location.reload()">Retry</button></td></tr>';
        }
        return;
    }

    clientsTable = $('#clientsDataTable').DataTable({
        serverSide: true,
        processing: true,
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[0, 'desc']],
        ajax: {
            url: '/api/clients',
            type: 'GET',
            data: function (d) {
                d.status = document.getElementById('filterStatus')?.value || '';
                d.state = document.getElementById('filterState')?.value || '';
                d.city = document.getElementById('filterCity')?.value || '';
                d.lead_source = document.getElementById('filterLeadSource')?.value || '';
                d.assigned_to = document.getElementById('filterAssignedTo')?.value || '';
                d.date_from = document.getElementById('filterDateFrom')?.value || '';
                d.date_to = document.getElementById('filterDateTo')?.value || '';
            },
            error: function (xhr) {
                if (xhr.status === 401) {
                    window.location.href = '/login';
                    return;
                }
                const tbody = document.querySelector('#clientsDataTable tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load clients from server. <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="dtRetryBtn">Retry</button></td></tr>';
                    document.getElementById('dtRetryBtn')?.addEventListener('click', () => {
                        clientsTable.ajax.reload();
                    });
                }
            }
        },
        columns: [
            {
                data: 'client_code',
                render: function (data, type, row) {
                    return `<a href="/clients/${row.id}" class="fw-bold font-monospace text-primary text-decoration-none">${UI.escape(data)}</a>`;
                }
            },
            {
                data: 'name',
                render: function (data, type, row) {
                    const isCompany = (row.client_type === 'company');
                    const badge = isCompany
                        ? '<span class="badge bg-light text-primary border me-1">Company</span>'
                        : '<span class="badge bg-light text-secondary border me-1">Individual</span>';
                    const contact = isCompany && row.contact_person
                        ? `<div class="text-muted small">Attn: ${UI.escape(row.contact_person)}</div>`
                        : '';
                    return `<div>
                        <div class="fw-semibold">${badge} <a href="/clients/${row.id}" class="text-dark text-decoration-none">${UI.escape(data)}</a></div>
                        ${contact}
                    </div>`;
                }
            },
            {
                data: 'email',
                orderable: false,
                render: function (data, type, row) {
                    const mobile = row.mobile ? `<div><small class="text-muted font-monospace">${UI.escape(row.mobile)}</small></div>` : '';
                    return `<div>
                        <a href="mailto:${UI.escape(data)}" class="small text-decoration-none text-truncate d-block" style="max-width: 180px;">${UI.escape(data)}</a>
                        ${mobile}
                    </div>`;
                }
            },
            {
                data: 'city',
                render: function (data, type, row) {
                    const city = row.city ? UI.escape(row.city) : '-';
                    const state = row.state ? `<div class="text-muted small">${UI.escape(row.state)}</div>` : '';
                    return `<div><span>${city}</span>${state}</div>`;
                }
            },
            {
                data: 'assigned_to_name',
                orderable: false,
                render: function (data) {
                    return data ? `<span class="badge bg-light text-dark border">${UI.escape(data)}</span>` : '<span class="text-muted small">Unassigned</span>';
                }
            },
            {
                data: 'status',
                render: function (data) {
                    const status = (data || 'new').toLowerCase();
                    let badgeClass = 'bg-secondary';
                    if (status === 'active') badgeClass = 'bg-success';
                    else if (status === 'new') badgeClass = 'bg-info text-dark';
                    else if (status === 'inactive') badgeClass = 'bg-warning text-dark';
                    return `<span class="badge ${badgeClass} text-uppercase px-2 py-1">${UI.escape(status)}</span>`;
                }
            },
            {
                data: 'created_at',
                render: function (data) {
                    if (!data) return '-';
                    return `<small class="text-muted">${data.substring(0, 10)}</small>`;
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-end',
                render: function (data, type, row) {
                    return `<div class="btn-group btn-group-sm">
                        <a href="/clients/${row.id}" class="btn btn-outline-secondary" title="View Profile">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                        <button type="button" class="btn btn-outline-danger delete-btn" data-id="${row.id}" data-name="${UI.escape(row.name)}" data-code="${UI.escape(row.client_code)}" title="Delete Client">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>`;
                }
            }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search clients...",
            emptyTable: "No clients registered yet.",
            zeroRecords: "No matching clients found.",
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div> Loading clients...'
        }
    });

    // 3. Filter form submit & reset
    const filterForm = document.getElementById('clientFilterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (clientsTable) clientsTable.ajax.reload();
        });
    }

    const resetFiltersBtn = document.getElementById('resetFiltersBtn');
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', () => {
            if (filterForm) filterForm.reset();
            if (clientsTable) clientsTable.ajax.reload();
        });
    }

    // 4. Delete modal trigger
    $('#clientsDataTable').on('click', '.delete-btn', function () {
        targetDeleteId = this.dataset.id;
        const codeEl = document.getElementById('deleteClientCode');
        const nameEl = document.getElementById('deleteClientName');
        if (codeEl) codeEl.textContent = this.dataset.code;
        if (nameEl) nameEl.textContent = this.dataset.name;
        const modalEl = document.getElementById('deleteClientModal');
        if (modalEl && window.bootstrap) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    });

    // Confirm delete
    const confirmDeleteBtn = document.getElementById('confirmDeleteClientBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            if (!targetDeleteId) return;
            UI.buttonLoading(confirmDeleteBtn, true, 'Deleting...');

            try {
                await api.delete(`/api/clients/${targetDeleteId}`);
                UI.toast('Client deleted successfully.', 'success');
                const modalEl = document.getElementById('deleteClientModal');
                if (modalEl && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                if (clientsTable) clientsTable.ajax.reload(null, false);
            } catch (err) {
                UI.toast(err.message || 'Failed to delete client.', 'danger');
            } finally {
                UI.buttonLoading(confirmDeleteBtn, false);
                targetDeleteId = null;
            }
        });
    }

    // 5. Export handlers
    function getFilterQueryString(format) {
        const params = new URLSearchParams();
        params.set('format', format);

        const status = document.getElementById('filterStatus')?.value;
        if (status) params.set('status', status);

        const state = document.getElementById('filterState')?.value;
        if (state) params.set('state', state);

        const city = document.getElementById('filterCity')?.value;
        if (city) params.set('city', city);

        const leadSource = document.getElementById('filterLeadSource')?.value;
        if (leadSource) params.set('lead_source', leadSource);

        const assignedTo = document.getElementById('filterAssignedTo')?.value;
        if (assignedTo) params.set('assigned_to', assignedTo);

        const dateFrom = document.getElementById('filterDateFrom')?.value;
        if (dateFrom) params.set('date_from', dateFrom);

        const dateTo = document.getElementById('filterDateTo')?.value;
        if (dateTo) params.set('date_to', dateTo);

        const search = clientsTable ? clientsTable.search() : '';
        if (search) params.set('search', search);

        return params.toString();
    }

    const exportXlsxBtn = document.getElementById('exportXlsxBtn');
    if (exportXlsxBtn) {
        exportXlsxBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = `/api/clients/export?${getFilterQueryString('xlsx')}`;
        });
    }

    const exportCsvBtn = document.getElementById('exportCsvBtn');
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = `/api/clients/export?${getFilterQueryString('csv')}`;
        });
    }
});
