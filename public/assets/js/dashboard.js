/**
 * Dashboard Overview & Analytics Controller
 */
(function () {
    'use strict';

    let monthlyChart = null;
    let leadSourceChart = null;
    let statusChart = null;

    function renderSpinner(colorClass) {
        return `<div class="spinner-border spinner-border-sm ${colorClass}" role="status"></div>`;
    }

    function resetSpinners() {
        const c1 = document.getElementById('statTotalClients');
        const c2 = document.getElementById('statNewThisMonth');
        const c3 = document.getElementById('statTodayFollowups');
        const c4 = document.getElementById('statOverdueFollowups');

        if (c1) c1.innerHTML = renderSpinner('text-primary');
        if (c2) c2.innerHTML = renderSpinner('text-info');
        if (c3) c3.innerHTML = renderSpinner('text-warning');
        if (c4) c4.innerHTML = renderSpinner('text-danger');
    }

    function showChartEmptyState(containerId, message = 'No data yet') {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted small">
                <svg width="36" height="36" class="mb-2 text-secondary opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
                <span>${message}</span>
            </div>`;
    }

    function ensureCanvas(containerId, canvasId) {
        const container = document.getElementById(containerId);
        if (!container) return null;
        let canvas = document.getElementById(canvasId);
        if (!canvas) {
            container.innerHTML = `<canvas id="${canvasId}"></canvas>`;
            canvas = document.getElementById(canvasId);
        }
        return canvas ? canvas.getContext('2d') : null;
    }

    async function loadDashboard() {
        resetSpinners();

        try {
            if (typeof api === 'undefined') {
                throw new Error('API client is not loaded');
            }

            const res = await api.get('/api/dashboard/stats');
            if (!res || !res.data) {
                throw new Error(res?.message || 'Failed to load dashboard metrics');
            }

            const data = res.data;

            // 1. Stat Cards (display 0 if 0, never leave spinners)
            const c1 = document.getElementById('statTotalClients');
            const c2 = document.getElementById('statNewThisMonth');
            const c3 = document.getElementById('statTodayFollowups');
            const c4 = document.getElementById('statOverdueFollowups');

            if (c1) c1.textContent = Number(data.total_clients || 0).toLocaleString();
            if (c2) c2.textContent = Number(data.new_this_month || 0).toLocaleString();
            if (c3) c3.textContent = Number(data.follow_ups?.today_count || 0).toLocaleString();
            if (c4) c4.textContent = Number(data.follow_ups?.overdue_count || 0).toLocaleString();

            // 2. Charts
            renderMonthlyChart(data.monthly_new_clients || []);
            renderLeadSourceChart(data.by_lead_source || {});
            renderStatusChart(data.by_status || {});

            // 3. Top Staff Leaderboard (Hide if restricted role)
            const staffCard = document.getElementById('topStaffCardWrapper');
            if (!data.can_view_all) {
                if (staffCard) staffCard.classList.add('d-none');
            } else {
                if (staffCard) staffCard.classList.remove('d-none');
                renderTopStaff(data.top_staff || []);
            }

            // 4. Operational Lists
            renderTodayFollowups(data.follow_ups?.today_list || []);
            renderRecentClients(data.recent_clients || []);
        } catch (err) {
            handleDashboardError(err);
        }
    }

    function handleDashboardError(err) {
        console.error('Dashboard load error:', err);
        const retryHtml = '<span class="text-danger small fs-6">Error <button type="button" class="btn btn-sm btn-link p-0 text-primary text-decoration-underline dash-retry-btn" style="font-size: 0.8rem;">Retry</button></span>';

        ['statTotalClients', 'statNewThisMonth', 'statTodayFollowups', 'statOverdueFollowups'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = retryHtml;
        });

        ['monthlyTrendChartContainer', 'leadSourceChartContainer', 'statusChartContainer'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.innerHTML = `
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted small">
                        <span class="text-danger mb-2">Failed to load chart</span>
                        <button type="button" class="btn btn-sm btn-outline-primary dash-retry-btn">Retry</button>
                    </div>`;
            }
        });

        const staffTbody = document.getElementById('topStaffTableBody');
        if (staffTbody) {
            staffTbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Failed to load leaderboard. <button type="button" class="btn btn-sm btn-link p-0 text-primary dash-retry-btn">Retry</button></td></tr>';
        }

        const fuTbody = document.getElementById('todayFollowupsTableBody');
        if (fuTbody) {
            fuTbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load follow-ups. <button type="button" class="btn btn-sm btn-link p-0 text-primary dash-retry-btn">Retry</button></td></tr>';
        }

        const rcTbody = document.getElementById('recentClientsTableBody');
        if (rcTbody) {
            rcTbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load recent clients. <button type="button" class="btn btn-sm btn-link p-0 text-primary dash-retry-btn">Retry</button></td></tr>';
        }

        attachRetryListeners();
    }

    function attachRetryListeners() {
        document.querySelectorAll('.dash-retry-btn').forEach(btn => {
            btn.removeEventListener('click', loadDashboard);
            btn.addEventListener('click', loadDashboard);
        });
    }

    // 12-Month Line Chart
    function renderMonthlyChart(items) {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            showChartEmptyState('monthlyTrendChartContainer', 'Chart library unavailable');
            return;
        }

        if (monthlyChart) {
            monthlyChart.destroy();
            monthlyChart = null;
        }

        const hasData = items && items.length > 0 && items.some(i => Number(i.count) > 0);
        if (!hasData) {
            showChartEmptyState('monthlyTrendChartContainer', 'No client acquisition data yet');
            return;
        }

        const ctx = ensureCanvas('monthlyTrendChartContainer', 'monthlyTrendChart');
        if (!ctx) return;

        const labels = items.map(i => i.label);
        const values = items.map(i => i.count);

        monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Clients',
                    data: values,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#0d6efd',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => ` ${context.parsed.y} client${context.parsed.y === 1 ? '' : 's'}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 11 }
                        },
                        grid: { color: '#f0f2f5' }
                    }
                }
            }
        });
    }

    // Lead Sources Doughnut Chart
    function renderLeadSourceChart(sourceObj) {
        if (typeof Chart === 'undefined') {
            showChartEmptyState('leadSourceChartContainer', 'Chart library unavailable');
            return;
        }

        if (leadSourceChart) {
            leadSourceChart.destroy();
            leadSourceChart = null;
        }

        const labels = Object.keys(sourceObj || {}).filter(k => Number(sourceObj[k]) > 0);
        const values = labels.map(k => Number(sourceObj[k]));

        if (labels.length === 0 || values.every(v => v === 0)) {
            showChartEmptyState('leadSourceChartContainer', 'No lead sources recorded yet');
            return;
        }

        const ctx = ensureCanvas('leadSourceChartContainer', 'leadSourceChart');
        if (!ctx) return;

        const palette = [
            '#0d6efd', '#20c997', '#ffc107', '#fd7e14', '#6f42c1',
            '#0dcaf0', '#d63384', '#6c757d'
        ];

        leadSourceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 12,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }

    // Status Bar Chart
    function renderStatusChart(statusObj) {
        if (typeof Chart === 'undefined') {
            showChartEmptyState('statusChartContainer', 'Chart library unavailable');
            return;
        }

        if (statusChart) {
            statusChart.destroy();
            statusChart = null;
        }

        const newCount = Number(statusObj?.new || 0);
        const activeCount = Number(statusObj?.active || 0);
        const inactiveCount = Number(statusObj?.inactive || 0);

        if (newCount === 0 && activeCount === 0 && inactiveCount === 0) {
            showChartEmptyState('statusChartContainer', 'No client status distribution yet');
            return;
        }

        const ctx = ensureCanvas('statusChartContainer', 'statusChart');
        if (!ctx) return;

        statusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['New', 'Active', 'Inactive'],
                datasets: [{
                    data: [newCount, activeCount, inactiveCount],
                    backgroundColor: ['#0dcaf0', '#198754', '#ffc107'],
                    borderRadius: 6,
                    maxBarThickness: 38,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: '#f0f2f5' }
                    }
                }
            }
        });
    }

    // Staff Leaderboard
    function renderTopStaff(staffList) {
        const tbody = document.getElementById('topStaffTableBody');
        if (!tbody) return;

        if (!staffList || staffList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No staff client additions recorded this month.</td></tr>';
            return;
        }

        let html = '';
        staffList.forEach((s, idx) => {
            let rankBadge = `<span class="badge bg-secondary rounded-pill">${idx + 1}</span>`;
            if (idx === 0) rankBadge = '<span class="badge bg-warning text-dark rounded-pill">&#127942; 1st</span>';
            else if (idx === 1) rankBadge = '<span class="badge bg-light text-dark border rounded-pill">2nd</span>';
            else if (idx === 2) rankBadge = '<span class="badge bg-light text-dark border rounded-pill">3rd</span>';

            const name = (window.UI && UI.escape) ? UI.escape(s.name) : s.name;
            const email = (window.UI && UI.escape) ? UI.escape(s.email) : s.email;

            html += `<tr>
                <td>${rankBadge}</td>
                <td class="fw-semibold">${name}</td>
                <td><small class="text-muted">${email}</small></td>
                <td class="text-end">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 fw-bold">
                        ${s.client_count} client${s.client_count === 1 ? '' : 's'}
                    </span>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // Today's Follow-ups List
    function renderTodayFollowups(list) {
        const tbody = document.getElementById('todayFollowupsTableBody');
        const badge = document.getElementById('todayFollowupListBadge');
        if (badge) badge.textContent = list ? list.length : 0;

        if (!tbody) return;

        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No pending follow-ups scheduled for today.</td></tr>';
            return;
        }

        let html = '';
        list.forEach(f => {
            const timeStr = f.due_at ? f.due_at.substring(11, 16) : '-';

            let typeBadge = '<span class="badge bg-secondary">Other</span>';
            if (f.type === 'call') typeBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Call</span>';
            else if (f.type === 'meeting') typeBadge = '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Meeting</span>';
            else if (f.type === 'email') typeBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Email</span>';

            const clientName = (window.UI && UI.escape) ? UI.escape(f.client_name || 'Client') : (f.client_name || 'Client');
            const clientCode = (window.UI && UI.escape) ? UI.escape(f.client_code || '') : (f.client_code || '');
            const notes = (window.UI && UI.escape) ? UI.escape(f.notes || 'None') : (f.notes || 'None');

            html += `<tr>
                <td>
                    <div class="fw-semibold">
                        <a href="/clients/${f.client_id}" class="text-decoration-none text-primary">
                            ${clientName}
                        </a>
                    </div>
                    <small class="text-muted font-monospace">${clientCode}</small>
                </td>
                <td>${typeBadge}</td>
                <td><span class="badge bg-light text-dark border">${timeStr}</span></td>
                <td><small class="text-muted text-truncate d-inline-block" style="max-width: 180px;">${notes}</small></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-success dash-mark-done-btn" data-id="${f.id}" title="Complete Follow-up">
                        &#10003; Done
                    </button>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // Recent Clients List
    function renderRecentClients(clients) {
        const tbody = document.getElementById('recentClientsTableBody');
        if (!tbody) return;

        if (!clients || clients.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No clients registered yet.</td></tr>';
            return;
        }

        let html = '';
        clients.forEach(c => {
            const st = (c.status || 'new').toLowerCase();
            const statusBadge = st === 'active'
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                : (st === 'new' ? '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">New</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Inactive</span>');

            const dateStr = (c.created_at || '').substring(0, 10);
            const name = (window.UI && UI.escape) ? UI.escape(c.name) : c.name;
            const code = (window.UI && UI.escape) ? UI.escape(c.client_code) : c.client_code;
            const mobile = (window.UI && UI.escape) ? UI.escape(c.mobile || '-') : (c.mobile || '-');

            html += `<tr>
                <td>
                    <div class="fw-semibold">
                        <a href="/clients/${c.id}" class="text-decoration-none text-primary">
                            ${name}
                        </a>
                    </div>
                    <small class="text-muted font-monospace">${code}</small>
                </td>
                <td><small class="text-muted">${mobile}</small></td>
                <td>${statusBadge}</td>
                <td><small class="text-muted">${dateStr}</small></td>
                <td class="text-end">
                    <a href="/clients/${c.id}" class="btn btn-sm btn-outline-secondary" title="View Profile">
                        &rarr;
                    </a>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // Setup Event Listeners
    function setupEventListeners() {
        const fuTbody = document.getElementById('todayFollowupsTableBody');
        if (fuTbody) {
            fuTbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.dash-mark-done-btn');
                if (!btn) return;

                const id = btn.dataset.id;
                const idInput = document.getElementById('dashMarkDoneId');
                if (idInput) idInput.value = id;

                const form = document.getElementById('dashMarkDoneForm');
                if (form) form.reset();

                const modalEl = document.getElementById('dashboardMarkDoneModal');
                if (modalEl && window.bootstrap) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            });
        }

        const markDoneForm = document.getElementById('dashMarkDoneForm');
        if (markDoneForm) {
            markDoneForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('dashMarkDoneId').value;
                const outcome = document.getElementById('dash_outcome_note').value;
                const submitBtn = document.getElementById('dashSubmitMarkDoneBtn');

                if (window.UI && UI.buttonLoading) {
                    UI.buttonLoading(submitBtn, true, 'Saving...');
                }

                try {
                    await api.put(`/api/followups/${id}`, {
                        status: 'done',
                        outcome: outcome
                    });
                    if (window.UI && UI.toast) {
                        UI.toast('Follow-up marked as completed!', 'success');
                    }
                    const modalEl = document.getElementById('dashboardMarkDoneModal');
                    if (modalEl && window.bootstrap) {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }
                    loadDashboard();
                } catch (err) {
                    if (window.UI && UI.toast) {
                        UI.toast(err.message || 'Failed to complete follow-up.', 'danger');
                    }
                } finally {
                    if (window.UI && UI.buttonLoading) {
                        UI.buttonLoading(submitBtn, false);
                    }
                }
            });
        }
    }

    window.loadDashboard = loadDashboard;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setupEventListeners();
            loadDashboard();
        });
    } else {
        setupEventListeners();
        loadDashboard();
    }
})();
