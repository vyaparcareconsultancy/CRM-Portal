<div class="row g-4">
    <!-- Top Header -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="fw-bold mb-1">CRM & Business Operations Dashboard</h4>
                    <p class="text-muted mb-0">
                        Welcome back, <strong><?= e(\App\Core\Session::get('user_name') ?? 'User') ?></strong>. Real-time metrics for tax compliance, training academy, and leads.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border px-3 py-2">
                        <?= date('l, d M Y') ?>
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold text-uppercase">
                        Role: <?= e(\App\Services\PermissionService::getRole() ?? 'Staff') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards Row 1: Leads & Operations -->
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Total Leads</span>
                    <div class="badge bg-primary-subtle text-primary p-2 rounded-circle">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                </div>
                <h3 class="fw-bold mb-1" id="statTotalLeads"><div class="spinner-border spinner-border-sm text-primary"></div></h3>
                <div class="small text-muted">
                    <span class="text-success fw-semibold" id="statNewLeadsToday">0</span> today &bull; <span class="text-primary fw-semibold" id="statNewLeadsMonth">0</span> this month
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-warning border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Follow-ups</span>
                    <div class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-circle">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                </div>
                <h3 class="fw-bold mb-1 text-warning" id="statTodayFollowups"><div class="spinner-border spinner-border-sm text-warning"></div></h3>
                <div class="small text-muted">
                    Due today &bull; <span class="text-danger fw-semibold" id="statOverdueFollowups">0</span> overdue
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-success border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Converted & Active</span>
                    <div class="badge bg-success-subtle text-success p-2 rounded-circle">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <h3 class="fw-bold mb-1 text-success" id="statConvertedCustomers"><div class="spinner-border spinner-border-sm text-success"></div></h3>
                <div class="small text-muted">
                    Converted clients &bull; <span class="text-info fw-semibold" id="statActiveStudents">0</span> active students
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-info border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Compliance Alerts</span>
                    <div class="badge bg-info-subtle text-info-emphasis p-2 rounded-circle">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <h3 class="fw-bold mb-1 text-info" id="statUpcomingReminders"><div class="spinner-border spinner-border-sm text-info"></div></h3>
                <div class="small text-muted">Upcoming statutory & fee deadlines</div>
            </div>
        </div>
    </div>

    <!-- Financial Cards Row (Conditional for non-trainers) -->
    <div class="col-12" id="financialCardsRow" style="display: none;">
        <div class="row g-4">
            <div class="col-sm-6">
                <div class="card shadow-sm border-0 bg-primary-subtle border-start border-primary border-4">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Collections This Month</span>
                            <h3 class="fw-bold text-primary mb-0 mt-1" id="statRevenueThisMonth">₹0.00</h3>
                            <small class="text-muted">Total payments received in <?= date('M Y') ?></small>
                        </div>
                        <div class="bg-primary text-white p-3 rounded-circle">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="card shadow-sm border-0 bg-danger-subtle border-start border-danger border-4">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Outstanding Due</span>
                            <h3 class="fw-bold text-danger mb-0 mt-1" id="statTotalDue">₹0.00</h3>
                            <small class="text-muted">Unpaid client & tuition invoices</small>
                        </div>
                        <div class="bg-danger text-white p-3 rounded-circle">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row: Leads by Source (Doughnut) & Conversion Funnel (Bar) -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">Leads by Source</h6>
                <small class="text-muted">Acquisition channels distribution</small>
            </div>
            <div class="card-body p-3 d-flex align-items-center justify-content-center">
                <div style="position: relative; width: 100%; height: 260px;">
                    <canvas id="chartLeadsBySource"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">Lead Conversion Funnel</h6>
                <small class="text-muted">Lead lifecycle from intake to conversion</small>
            </div>
            <div class="card-body p-3">
                <div style="position: relative; width: 100%; height: 260px;">
                    <canvas id="chartConversionFunnel"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Trend Chart Row (Conditional for non-trainers) -->
    <div class="col-lg-8" id="revenueChartCol" style="display: none;">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary">Monthly Revenue Trend</h6>
                <small class="text-muted">Collections over the past 6 months (INR)</small>
            </div>
            <div class="card-body p-3">
                <div style="position: relative; width: 100%; height: 260px;">
                    <canvas id="chartMonthlyRevenue"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Reminders & Action List -->
    <div class="col-lg-4" id="upcomingRemindersCol">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Upcoming Deadlines</h6>
                    <small class="text-muted">Statutory & fee schedules</small>
                </div>
                <a href="/reminders" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 11px;">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" id="upcomingRemindersList">
                    <li class="list-group-item text-center text-muted py-4 small">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading deadlines...
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Today's Follow-ups List -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Today's Scheduled Follow-ups</h6>
                    <small class="text-muted">Calls, messages and visits due today</small>
                </div>
                <a href="/follow-ups" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 11px;">Open Follow-ups</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Contact / Client</th>
                                <th>Scheduled Time</th>
                                <th>Type / Mode</th>
                                <th>Remarks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="todayFollowUpsTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3 small">Loading scheduled tasks...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    let sourceChart = null;
    let funnelChart = null;
    let revenueChart = null;

    try {
        const res = await fetch('/api/dashboard/stats');
        const data = await res.json();

        if (data.status === 'success' && data.data) {
            renderDashboard(data.data);
        } else {
            console.error('Failed to load dashboard metrics:', data.message);
        }
    } catch (err) {
        console.error('Dashboard API error:', err);
    }

    function renderDashboard(d) {
        const m = d.metrics || {};

        // Top Metrics
        document.getElementById('statTotalLeads').textContent = (m.total_leads ?? 0).toLocaleString();
        document.getElementById('statNewLeadsToday').textContent = (m.new_leads_today ?? 0).toLocaleString();
        document.getElementById('statNewLeadsMonth').textContent = (m.new_leads_month ?? 0).toLocaleString();
        document.getElementById('statTodayFollowups').textContent = (m.follow_ups_today ?? 0).toLocaleString();
        document.getElementById('statOverdueFollowups').textContent = (m.follow_ups_overdue ?? 0).toLocaleString();
        document.getElementById('statConvertedCustomers').textContent = (m.converted_customers ?? 0).toLocaleString();
        document.getElementById('statActiveStudents').textContent = (m.active_students ?? 0).toLocaleString();
        document.getElementById('statUpcomingReminders').textContent = (m.upcoming_compliance_reminders ?? 0).toLocaleString();

        // Financial row
        if (d.can_view_financial) {
            document.getElementById('financialCardsRow').style.display = 'block';
            document.getElementById('revenueChartCol').style.display = 'block';
            document.getElementById('statRevenueThisMonth').textContent = '₹' + Number(m.revenue_this_month || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('statTotalDue').textContent = '₹' + Number(m.total_due || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        }

        // Charts
        const charts = d.charts || {};
        renderLeadsBySourceChart(charts.leads_by_source || []);
        renderConversionFunnelChart(charts.conversion_funnel || []);
        if (d.can_view_financial) {
            renderMonthlyRevenueChart(charts.monthly_revenue || []);
        }

        // Upcoming Deadlines Widget
        renderUpcomingReminders(d.upcoming_reminders || []);

        // Today Follow-ups Table
        renderTodayFollowUps(d.today_follow_ups || []);
    }

    function renderLeadsBySourceChart(items) {
        const ctx = document.getElementById('chartLeadsBySource');
        if (!ctx) return;
        if (sourceChart) sourceChart.destroy();

        const labels = items.map(i => i.source_name);
        const data = items.map(i => parseInt(i.count, 10));
        const colors = ['#0d6efd', '#20c997', '#ffc107', '#fd7e14', '#6f42c1', '#d63384', '#6c757d', '#0dcaf0'];

        sourceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['No Data'],
                datasets: [{
                    data: data.length ? data : [1],
                    backgroundColor: colors.slice(0, Math.max(labels.length, 1)),
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    function renderConversionFunnelChart(items) {
        const ctx = document.getElementById('chartConversionFunnel');
        if (!ctx) return;
        if (funnelChart) funnelChart.destroy();

        const labels = items.map(i => i.label);
        const counts = items.map(i => i.count);

        funnelChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Leads in Stage',
                    data: counts,
                    backgroundColor: ['#0d6efd', '#0dcaf0', '#ffc107', '#fd7e14', '#198754', '#dc3545'],
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderMonthlyRevenueChart(items) {
        const ctx = document.getElementById('chartMonthlyRevenue');
        if (!ctx) return;
        if (revenueChart) revenueChart.destroy();

        const labels = items.map(i => i.label);
        const revenues = items.map(i => i.revenue);

        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Collections (INR)',
                    data: revenues,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#0d6efd',
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return '₹' + (v >= 1000 ? (v/1000) + 'k' : v); }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderUpcomingReminders(items) {
        const list = document.getElementById('upcomingRemindersList');
        if (!items || items.length === 0) {
            list.innerHTML = '<li class="list-group-item text-center text-muted py-4 small">No upcoming deadlines scheduled.</li>';
            return;
        }

        list.innerHTML = items.map(r => `
            <li class="list-group-item px-3 py-2 d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold small text-dark">${escapeHtml(r.title)}</div>
                    <div class="text-muted" style="font-size: 11px;">${escapeHtml(r.entity_name || 'Client')} &bull; ${escapeHtml(r.period || '')}</div>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-danger border" style="font-size: 11px;">Due: ${r.due_date}</span>
                </div>
            </li>
        `).join('');
    }

    function renderTodayFollowUps(items) {
        const tbody = document.getElementById('todayFollowUpsTableBody');
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3 small">No follow-ups scheduled for today. Great job!</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(f => `
            <tr>
                <td class="fw-semibold">${escapeHtml(f.client_name || f.lead_name || 'Contact')}</td>
                <td><span class="badge bg-light text-dark border">${f.scheduled_time || f.scheduled_at || 'Today'}</span></td>
                <td><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle text-uppercase">${escapeHtml(f.type || f.mode || 'call')}</span></td>
                <td class="small text-muted">${escapeHtml(f.notes || f.remarks || '—')}</td>
                <td><span class="badge bg-warning-subtle text-warning-emphasis">Pending</span></td>
            </tr>
        `).join('');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});
</script>
