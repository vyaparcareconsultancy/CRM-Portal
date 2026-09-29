<div class="row">
    <div class="col-12">
        <!-- Page Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Follow-ups & Next Steps</h4>
                <p class="text-muted small mb-0">Track and manage scheduled calls, meetings, and client communications.</p>
            </div>
            <?php if (can('followup.manage')): ?>
            <div>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="openMainScheduleModalBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Schedule Follow-up</span>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filter Card & Tabs -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom pt-3 pb-0 px-3 px-md-4">
                <ul class="nav nav-tabs card-header-tabs" id="followUpTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active fw-semibold d-flex align-items-center gap-2" data-tab="today" type="button">
                            <span>Today</span>
                            <span class="badge bg-primary rounded-pill" id="badgeCountToday">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="upcoming" type="button">
                            <span>Upcoming</span>
                            <span class="badge bg-secondary rounded-pill" id="badgeCountUpcoming">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="overdue" type="button">
                            <span>Overdue</span>
                            <span class="badge bg-danger rounded-pill" id="badgeCountOverdue">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="done" type="button">
                            <span>Done</span>
                            <span class="badge bg-success rounded-pill" id="badgeCountDone">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="all" type="button">
                            <span>All</span>
                            <span class="badge bg-light text-dark border rounded-pill" id="badgeCountAll">0</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="filterSearch" placeholder="Search client name, code, notes...">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <select class="form-select" id="filterType">
                            <option value="">All Interaction Types</option>
                            <option value="call">Call</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <button type="button" class="btn btn-light border w-100" id="resetFiltersBtn">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Follow-ups Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" id="mainFollowUpsTable">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Due Date & Time</th>
                                <th>Assigned To</th>
                                <th>Notes & Outcome</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mainFollowUpsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                    <div>Loading follow-ups...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Pagination Footer -->
            <div class="card-footer bg-white border-top py-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="tablePaginationFooter">
                <div class="small text-muted" id="paginationInfo">Showing 0 of 0 entries</div>
                <ul class="pagination pagination-sm mb-0" id="paginationControls"></ul>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Follow-up Modal -->
<div class="modal fade" id="mainScheduleModal" tabindex="-1" aria-labelledby="mainScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="mainScheduleModalLabel">Schedule Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="mainScheduleForm">
                <div class="modal-body p-4">
                    <!-- Client Selector -->
                    <div class="mb-3">
                        <label for="sched_client_id" class="form-label small fw-semibold">Client *</label>
                        <select class="form-select" id="sched_client_id" name="client_id" required>
                            <option value="">Select a client...</option>
                        </select>
                        <div class="invalid-feedback" id="sched_client_idError"></div>
                    </div>

                    <!-- Type -->
                    <div class="mb-3">
                        <label for="sched_type" class="form-label small fw-semibold">Interaction Type *</label>
                        <select class="form-select" id="sched_type" name="type" required>
                            <option value="call">Phone Call</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                        </select>
                    </div>

                    <!-- Due At -->
                    <div class="mb-3">
                        <label for="sched_due_at" class="form-label small fw-semibold">Scheduled Date & Time *</label>
                        <input type="datetime-local" class="form-control" id="sched_due_at" name="due_at" required>
                        <div class="invalid-feedback" id="sched_due_atError"></div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="sched_notes" class="form-label small fw-semibold">Discussion Agenda / Notes</label>
                        <textarea class="form-control" id="sched_notes" name="notes" rows="3" placeholder="Enter agenda, purpose, or discussion points..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mainSubmitScheduleBtn">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Done Modal -->
<div class="modal fade" id="mainMarkDoneModal" tabindex="-1" aria-labelledby="mainMarkDoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="mainMarkDoneModalLabel">Complete Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="mainMarkDoneForm">
                <input type="hidden" id="mainMarkDoneId" value="">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Record discussion summary or key takeaways for this interaction.</p>
                    <div class="mb-3">
                        <label for="main_outcome_note" class="form-label small fw-semibold">Outcome Note *</label>
                        <textarea class="form-control" id="main_outcome_note" name="outcome" rows="3" placeholder="e.g. Client agreed to review quote; follow up next week..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="mainSubmitMarkDoneBtn">Mark as Done</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentTab = 'today';
    let currentPage = 1;
    const perPage = 15;
    let searchTimeout = null;

    const tbody = document.getElementById('mainFollowUpsTableBody');
    const searchInput = document.getElementById('filterSearch');
    const typeSelect = document.getElementById('filterType');

    // Tab Switching
    document.querySelectorAll('#followUpTabs [data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#followUpTabs [data-tab]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentTab = btn.dataset.tab;
            currentPage = 1;
            loadFollowUps();
        });
    });

    // Filters
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadFollowUps();
        }, 300);
    });

    typeSelect.addEventListener('change', () => {
        currentPage = 1;
        loadFollowUps();
    });

    document.getElementById('resetFiltersBtn')?.addEventListener('click', () => {
        searchInput.value = '';
        typeSelect.value = '';
        currentPage = 1;
        loadFollowUps();
    });

    // Load Follow-ups from API
    async function loadFollowUps() {
        tbody.innerHTML = `<tr>
            <td colspan="7" class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <div>Loading follow-ups...</div>
            </td>
        </tr>`;

        const queryParams = new URLSearchParams({
            tab: currentTab,
            search: searchInput.value.trim(),
            type: typeSelect.value,
            page: currentPage,
            per_page: perPage,
            sort_by: 'due_at',
            sort_dir: currentTab === 'done' ? 'DESC' : 'ASC',
        });

        try {
            const res = await api.get(`/api/followups?${queryParams.toString()}`);
            if (!res || !res.data) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No follow-ups found.</td></tr>';
                return;
            }

            const items = res.data.items || [];
            const total = res.data.total || 0;
            const counts = res.data.counts || {};

            updateBadges(counts);
            renderTable(items);
            renderPagination(total);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger small">${UI.escape(err.message || 'Error loading follow-ups.')}</td></tr>`;
        }
    }

    function updateBadges(counts) {
        document.getElementById('badgeCountToday').textContent = counts.today || 0;
        document.getElementById('badgeCountUpcoming').textContent = counts.upcoming || 0;
        document.getElementById('badgeCountOverdue').textContent = counts.overdue || 0;
        document.getElementById('badgeCountDone').textContent = counts.done || 0;
        document.getElementById('badgeCountAll').textContent = counts.all || 0;
    }

    function renderTable(items) {
        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
                <div class="mb-2">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="fw-semibold">No follow-ups for this tab</div>
                <div class="small text-muted">Scheduled interactions matching this criteria will appear here.</div>
            </td></tr>`;
            return;
        }

        const now = new Date();
        let html = '';

        items.forEach(f => {
            const dueDate = new Date(f.due_at.replace(' ', 'T'));
            const isOverdue = f.status === 'pending' && dueDate < now;
            const formattedDate = f.due_at.substring(0, 16);

            let typeBadge = '<span class="badge bg-secondary">Other</span>';
            if (f.type === 'call') typeBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Call</span>';
            else if (f.type === 'meeting') typeBadge = '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Meeting</span>';
            else if (f.type === 'email') typeBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Email</span>';

            let statusBadge = '<span class="badge bg-secondary">Unknown</span>';
            if (f.status === 'pending') {
                statusBadge = isOverdue
                    ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Overdue</span>'
                    : '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pending</span>';
            } else if (f.status === 'done') {
                statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Done</span>';
            } else if (f.status === 'missed') {
                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Missed</span>';
            }

            let notesHtml = UI.escape(f.notes || '-');
            if (notesHtml.includes('[Outcome]:')) {
                notesHtml = notesHtml.replace('[Outcome]:', '<strong class="text-success d-block mt-1">[Outcome]:</strong>');
            }

            html += `<tr>
                <td>
                    <div class="fw-semibold">
                        <a href="/clients/${f.client_id}" class="text-decoration-none text-primary">
                            ${UI.escape(f.client_name || 'Client')}
                        </a>
                    </div>
                    <small class="text-muted font-monospace">${UI.escape(f.client_code || '')}</small>
                </td>
                <td>${typeBadge}</td>
                <td>
                    <div class="fw-medium small">${formattedDate}</div>
                    ${isOverdue ? '<span class="badge bg-danger text-white" style="font-size: 0.65rem;">Past Due</span>' : ''}
                </td>
                <td><small class="text-muted">${UI.escape(f.user_name || 'Unassigned')}</small></td>
                <td><small class="text-muted d-block text-break" style="max-width: 260px;">${notesHtml}</small></td>
                <td>${statusBadge}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        ${f.status === 'pending' && <?= can('followup.manage') ? 'true' : 'false' ?> ? `
                        <button type="button" class="btn btn-outline-success main-mark-done-btn" data-id="${f.id}" title="Complete Follow-up">
                            &#10003; Done
                        </button>` : ''}
                        <a href="/clients/${f.client_id}" class="btn btn-outline-secondary" title="View Client">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                        <?php if (can('followup.manage')): ?>
                        <button type="button" class="btn btn-outline-danger main-delete-btn" data-id="${f.id}" title="Delete Follow-up">
                            &times;
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function renderPagination(total) {
        const totalPages = Math.ceil(total / perPage);
        const info = document.getElementById('paginationInfo');
        const start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
        const end = Math.min(total, currentPage * perPage);
        info.textContent = `Showing ${start} to ${end} of ${total} entries`;

        const controls = document.getElementById('paginationControls');
        if (totalPages <= 1) {
            controls.innerHTML = '';
            return;
        }

        let phtml = '';
        phtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <button class="page-link" data-page="${currentPage - 1}">&laquo;</button>
        </li>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                phtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <button class="page-link" data-page="${i}">${i}</button>
                </li>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                phtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        phtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <button class="page-link" data-page="${currentPage + 1}">&raquo;</button>
        </li>`;

        controls.innerHTML = phtml;

        controls.querySelectorAll('[data-page]').forEach(b => {
            b.addEventListener('click', () => {
                const target = parseInt(b.dataset.page, 10);
                if (target >= 1 && target <= totalPages && target !== currentPage) {
                    currentPage = target;
                    loadFollowUps();
                }
            });
        });
    }

    // Schedule Follow-up Modal
    let clientsLoaded = false;
    async function loadClientOptions() {
        if (clientsLoaded) return;
        const select = document.getElementById('sched_client_id');
        try {
            const res = await api.get('/api/clients?per_page=100');
            const clients = res?.data || [];
            select.innerHTML = '<option value="">Select a client...</option>';
            clients.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.name} (${c.client_code})`;
                select.appendChild(opt);
            });
            clientsLoaded = true;
        } catch (e) {
            console.warn('Failed to load clients list', e);
        }
    }

    const openSchedBtn = document.getElementById('openMainScheduleModalBtn');
    if (openSchedBtn) {
        openSchedBtn.addEventListener('click', async () => {
            const form = document.getElementById('mainScheduleForm');
            form.reset();
            UI.clearErrors(form);

            // Default due_at to tomorrow at 10:00 AM
            const tmr = new Date();
            tmr.setDate(tmr.getDate() + 1);
            tmr.setHours(10, 0, 0, 0);
            const isoStr = new Date(tmr.getTime() - tmr.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('sched_due_at').value = isoStr;

            await loadClientOptions();

            const modal = new bootstrap.Modal(document.getElementById('mainScheduleModal'));
            modal.show();
        });
    }

    document.getElementById('mainScheduleForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = document.getElementById('mainScheduleForm');
        const submitBtn = document.getElementById('mainSubmitScheduleBtn');
        UI.clearErrors(form);
        UI.buttonLoading(submitBtn, true, 'Scheduling...');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            await api.post('/api/followups', payload);
            UI.toast('Follow-up scheduled successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('mainScheduleModal')).hide();
            loadFollowUps();
        } catch (err) {
            if (err.errors) {
                UI.showFieldErrors(form, err.errors);
            } else {
                UI.toast(err.message || 'Failed to schedule follow-up.', 'danger');
            }
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Mark Done Action
    $('#mainFollowUpsTableBody').on('click', '.main-mark-done-btn', function () {
        const id = this.dataset.id;
        document.getElementById('mainMarkDoneId').value = id;
        document.getElementById('mainMarkDoneForm').reset();
        const modal = new bootstrap.Modal(document.getElementById('mainMarkDoneModal'));
        modal.show();
    });

    document.getElementById('mainMarkDoneForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('mainMarkDoneId').value;
        const outcome = document.getElementById('main_outcome_note').value;
        const submitBtn = document.getElementById('mainSubmitMarkDoneBtn');

        UI.buttonLoading(submitBtn, true, 'Saving...');
        try {
            await api.put(`/api/followups/${id}`, {
                status: 'done',
                outcome: outcome
            });
            UI.toast('Follow-up marked as completed!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('mainMarkDoneModal')).hide();
            loadFollowUps();
        } catch (err) {
            UI.toast(err.message || 'Failed to complete follow-up.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Delete Follow-up Action
    $('#mainFollowUpsTableBody').on('click', '.main-delete-btn', async function () {
        const id = this.dataset.id;
        if (!confirm('Are you sure you want to delete this follow-up?')) return;

        try {
            await api.delete(`/api/followups/${id}`);
            UI.toast('Follow-up deleted successfully.', 'success');
            loadFollowUps();
        } catch (err) {
            UI.toast(err.message || 'Failed to delete follow-up.', 'danger');
        }
    });

    // Initial Load
    loadFollowUps();
});
</script>
