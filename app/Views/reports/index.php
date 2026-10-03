<?php
/**
 * Reports & Performance Analytics View
 * Supports all 7 required business reports with filters and Excel/PDF export
 */
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">Reports & Performance Analytics</h1>
            <p class="text-muted small mb-0">Financial statements, lead conversion rates, course enrollments, aging analysis, and staff metrics.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm d-flex align-items-center gap-1" id="exportExcelBtn">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export Excel (.xlsx)</span>
            </button>
            <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="exportCsvBtn">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export CSV</span>
            </button>
            <button class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1" id="exportPdfBtn">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print / PDF</span>
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-2 fw-semibold small text-primary d-flex align-items-center gap-1">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            <span>Filter Report Parameters</span>
        </div>
        <div class="card-body p-3 bg-light">
            <form id="reportFilterForm" class="row g-2">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">From Date</label>
                    <input type="date" class="form-control form-control-sm" id="filterStartDate">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">To Date</label>
                    <input type="date" class="form-control form-control-sm" id="filterEndDate">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Staff Member</label>
                    <select class="form-select form-select-sm" id="filterStaff">
                        <option value="">-- All Staff --</option>
                        <?php foreach (($lookups['staff'] ?? []) as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['role_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6" id="groupFilterSource">
                    <label class="form-label small fw-semibold mb-1">Lead Source</label>
                    <select class="form-select form-select-sm" id="filterSource">
                        <option value="">-- All Sources --</option>
                        <?php foreach (($lookups['sources'] ?? []) as $src): ?>
                            <option value="<?= e($src['name']) ?>"><?= e($src['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6" id="groupFilterCourse">
                    <label class="form-label small fw-semibold mb-1">Course</label>
                    <select class="form-select form-select-sm" id="filterCourse">
                        <option value="">-- All Courses --</option>
                        <?php foreach (($lookups['courses'] ?? []) as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6" id="groupFilterService">
                    <label class="form-label small fw-semibold mb-1">Service</label>
                    <select class="form-select form-select-sm" id="filterService">
                        <option value="">-- All Services --</option>
                        <?php foreach (($lookups['services'] ?? []) as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6" id="groupFilterAging" style="display: none;">
                    <label class="form-label small fw-semibold mb-1">Aging Bucket</label>
                    <select class="form-select form-select-sm" id="filterAging">
                        <option value="">-- All Aging --</option>
                        <option value="0-30">0 - 30 Days Overdue</option>
                        <option value="31-60">31 - 60 Days Overdue</option>
                        <option value="60+">60+ Days Overdue</option>
                    </select>
                </div>
                <div class="col-12 text-end pt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="resetFiltersBtn">Reset</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="applyFiltersBtn">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reports Tab Navigation -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="reportTabs" role="tablist">
                <?php if (!empty($canViewFinancial)): ?>
                <li class="nav-item">
                    <button class="nav-link active fw-semibold" data-type="sales_collections" type="button">
                        Sales & Collections
                    </button>
                </li>
                <?php endif; ?>

                <?php if (!empty($canViewLeads)): ?>
                <li class="nav-item">
                    <button class="nav-link fw-semibold <?= empty($canViewFinancial) ? 'active' : '' ?>" data-type="lead_source_conversion" type="button">
                        Lead Source & Conversion %
                    </button>
                </li>
                <?php endif; ?>

                <?php if (!empty($canViewAcademic)): ?>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-type="course_admissions" type="button">
                        Course Admissions & Batches
                    </button>
                </li>
                <?php endif; ?>

                <?php if (!empty($canViewFinancial)): ?>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-type="client_service_revenue" type="button">
                        Client & Service Revenue
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-type="payment_aging" type="button">
                        Payment Aging (0-30, 31-60, 60+)
                    </button>
                </li>
                <?php endif; ?>

                <?php if (!empty($canViewFinancial) || !empty($canViewLeads)): ?>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-type="staff_performance" type="button">
                        Staff Performance
                    </button>
                </li>
                <?php endif; ?>

                <?php if (!empty($canViewAcademic)): ?>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-type="attendance_summary" type="button">
                        Attendance Summary
                    </button>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-body p-0">
            <!-- Report Title & Summary Banner -->
            <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" id="reportDisplayTitle">Report</h5>
                    <small class="text-muted" id="reportCacheNotice">Cached 10 minutes for performance &bull; Prepared via indexed SQL</small>
                </div>
                <div class="d-flex align-items-center gap-2" id="reportSummaryPills"></div>
            </div>

            <!-- Report Table Container -->
            <div class="table-responsive" style="min-height: 280px;">
                <table class="table table-hover align-middle mb-0" id="reportDataTable">
                    <thead class="table-light small" id="reportTableHeader"></thead>
                    <tbody id="reportTableBody">
                        <tr>
                            <td class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                Loading report data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentType = document.querySelector('#reportTabs button.active')?.getAttribute('data-type') || 'sales_collections';

    // Set initial date range to current month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const today = now.toISOString().split('T')[0];
    document.getElementById('filterStartDate').value = firstDay;
    document.getElementById('filterEndDate').value = today;

    // Load initial report
    loadReport(currentType);

    // Tab switcher
    document.querySelectorAll('#reportTabs button[data-type]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#reportTabs button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentType = this.getAttribute('data-type');
            toggleFilterVisibility(currentType);
            loadReport(currentType);
        });
    });

    function toggleFilterVisibility(type) {
        document.getElementById('groupFilterAging').style.display = (type === 'payment_aging') ? 'block' : 'none';
        document.getElementById('groupFilterSource').style.display = (type === 'lead_source_conversion') ? 'block' : 'none';
        document.getElementById('groupFilterCourse').style.display = (type === 'course_admissions' || type === 'attendance_summary') ? 'block' : 'none';
        document.getElementById('groupFilterService').style.display = (type === 'client_service_revenue') ? 'block' : 'none';
    }

    // Filter Form Submit
    document.getElementById('reportFilterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport(currentType);
    });

    // Reset Filters
    document.getElementById('resetFiltersBtn').addEventListener('click', function() {
        document.getElementById('reportFilterForm').reset();
        document.getElementById('filterStartDate').value = firstDay;
        document.getElementById('filterEndDate').value = today;
        loadReport(currentType);
    });

    function getFilterParams() {
        const p = new URLSearchParams();
        p.set('type', currentType);
        const s = document.getElementById('filterStartDate').value;
        const e = document.getElementById('filterEndDate').value;
        const staff = document.getElementById('filterStaff').value;
        const src = document.getElementById('filterSource').value;
        const crs = document.getElementById('filterCourse').value;
        const srv = document.getElementById('filterService').value;
        const aging = document.getElementById('filterAging').value;

        if (s) p.set('start_date', s);
        if (e) p.set('end_date', e);
        if (staff) p.set('staff_id', staff);
        if (src && currentType === 'lead_source_conversion') p.set('lead_source', src);
        if (crs && (currentType === 'course_admissions' || currentType === 'attendance_summary')) p.set('course_id', crs);
        if (srv && currentType === 'client_service_revenue') p.set('service_id', srv);
        if (aging && currentType === 'payment_aging') p.set('aging_bucket', aging);

        return p;
    }

    // Export Buttons
    document.getElementById('exportExcelBtn').addEventListener('click', function() {
        const p = getFilterParams();
        p.set('format', 'excel');
        window.location.href = `/api/reports/export?${p.toString()}`;
    });

    document.getElementById('exportCsvBtn').addEventListener('click', function() {
        const p = getFilterParams();
        p.set('format', 'csv');
        window.location.href = `/api/reports/export?${p.toString()}`;
    });

    document.getElementById('exportPdfBtn').addEventListener('click', function() {
        const p = getFilterParams();
        p.set('format', 'pdf');
        window.open(`/api/reports/export?${p.toString()}`, '_blank');
    });

    // Fetch and render report data
    async function loadReport(type) {
        const thead = document.getElementById('reportTableHeader');
        const tbody = document.getElementById('reportTableBody');
        const summaryDiv = document.getElementById('reportSummaryPills');

        thead.innerHTML = '';
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading report data...</td></tr>`;
        summaryDiv.innerHTML = '';

        try {
            const params = getFilterParams();
            const res = await fetch(`/api/reports/data?${params.toString()}`);
            const data = await res.json();

            if (data.status === 'success' && data.data) {
                renderReportTable(data.data);
            } else {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-danger">${escapeHtml(data.message || 'Error loading report')}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-danger">Failed to connect to report API.</td></tr>`;
        }
    }

    function renderReportTable(d) {
        document.getElementById('reportDisplayTitle').textContent = d.title || 'Report';
        renderSummaryPills(d.summary || {});

        const thead = document.getElementById('reportTableHeader');
        const tbody = document.getElementById('reportTableBody');
        const rows = d.rows || [];

        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">No records found for the selected filter criteria.</td></tr>`;
            return;
        }

        // Headers
        const cols = Object.keys(rows[0]);
        thead.innerHTML = `<tr>${cols.map(c => `<th>${escapeHtml(formatHeader(c))}</th>`).join('')}</tr>`;

        // Body
        tbody.innerHTML = rows.map(r => `
            <tr>
                ${cols.map(c => `<td>${formatCellValue(c, r[c])}</td>`).join('')}
            </tr>
        `).join('');
    }

    function renderSummaryPills(sum) {
        const div = document.getElementById('reportSummaryPills');
        const badges = [];

        for (const [k, v] of Object.entries(sum)) {
            let label = formatHeader(k);
            let val = v;
            if (typeof v === 'number') {
                if (k.includes('amount') || k.includes('collected') || k.includes('revenue') || k.includes('billed') || k.includes('due') || k.includes('pending') || k.includes('current') || k.includes('30') || k.includes('60')) {
                    val = '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                } else if (k.includes('pct')) {
                    val = Number(v) + '%';
                } else {
                    val = Number(v).toLocaleString();
                }
            }
            badges.push(`<span class="badge bg-white text-dark border px-2 py-1">${label}: <strong class="text-primary">${val}</strong></span>`);
        }

        div.innerHTML = badges.join(' ');
    }

    function formatHeader(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function formatCellValue(col, val) {
        if (val === null || val === undefined) return '<span class="text-muted">—</span>';
        if (typeof val === 'number' || (!isNaN(val) && val !== '' && (col.includes('amount') || col.includes('fee') || col.includes('revenue') || col.includes('collected') || col.includes('billed') || col.includes('due')))) {
            return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        }
        if (col.includes('pct') || col.includes('rate') || col.includes('attendance')) {
            return `<span class="fw-semibold ${val >= 75 ? 'text-success' : (val >= 50 ? 'text-warning' : 'text-danger')}">${val}%</span>`;
        }
        if (col === 'aging_bucket') {
            const badgeClass = val === 'Current' ? 'bg-success-subtle text-success' : (val === '0-30 Days' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger');
            return `<span class="badge ${badgeClass} border">${escapeHtml(val)}</span>`;
        }
        return escapeHtml(String(val));
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});
</script>
