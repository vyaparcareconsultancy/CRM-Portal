<div class="row g-4">
    <!-- Top Header -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="fw-bold mb-1">Dashboard Overview</h4>
                    <p class="text-muted mb-0">
                        Welcome back, <strong><?= e(\App\Core\Session::get('user_name') ?? 'User') ?></strong>. Here is your real-time CRM performance snapshot.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border px-3 py-2">
                        <?= date('l, d M Y') ?>
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold">
                        Role: <?= e(\App\Services\PermissionService::getRole() ?? 'Staff') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards Row -->
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Total Clients</span>
                    <div class="badge bg-primary-subtle text-primary p-2 rounded-circle">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <h2 class="fw-bold mb-1" id="statTotalClients">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </h2>
                <small class="text-muted">Scoped portfolio records</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-info border-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">New This Month</span>
                    <div class="badge bg-info-subtle text-info-emphasis p-2 rounded-circle">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                </div>
                <h2 class="fw-bold mb-1 text-info" id="statNewThisMonth">
                    <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                </h2>
                <small class="text-muted">Registered in <?= date('M Y') ?></small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-warning border-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Today's Follow-ups</span>
                    <div class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-circle">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                </div>
                <h2 class="fw-bold mb-1 text-warning" id="statTodayFollowups">
                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                </h2>
                <small class="text-muted">Scheduled for today</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 border-start border-danger border-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Overdue Follow-ups</span>
                    <div class="badge bg-danger-subtle text-danger p-2 rounded-circle">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <h2 class="fw-bold mb-1 text-danger" id="statOverdueFollowups">
                    <div class="spinner-border spinner-border-sm text-danger" role="status"></div>
                </h2>
                <small class="text-muted">Requires urgent action</small>
            </div>
        </div>
    </div>

    <!-- Charts Row: Monthly Trend (Line) + Lead Sources (Doughnut) -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-primary">New Client Acquisition Trend</h6>
                    <small class="text-muted">Monthly registrations over the past 12 months</small>
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div style="height: 280px; position: relative;" id="monthlyTrendChartContainer">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-primary">Lead Sources</h6>
                    <small class="text-muted">Channel acquisition breakdown</small>
                </div>
            </div>
            <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-center">
                <div style="height: 260px; width: 100%; position: relative;" id="leadSourceChartContainer">
                    <canvas id="leadSourceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown (Bar) & Optional Staff Leaderboard -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-primary">Clients by Status</h6>
                    <small class="text-muted">Distribution across life-cycle stages</small>
                </div>
            </div>
            <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-center">
                <div style="height: 250px; width: 100%; position: relative;" id="statusChartContainer">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100" id="topStaffCardWrapper">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-primary">Staff Leaderboard (<?= date('M Y') ?>)</h6>
                    <small class="text-muted">Top 5 team members by clients onboarded this month</small>
                </div>
                <span class="badge bg-light text-secondary border">Admin / Manager</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" id="topStaffTable">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th>Rank</th>
                                <th>Representative</th>
                                <th>Email</th>
                                <th class="text-end">Clients Added</th>
                            </tr>
                        </thead>
                        <tbody id="topStaffTableBody">
                            <tr><td colspan="4" class="text-center py-4 text-muted">Loading leaderboard...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Operational Data Row: Today's Follow-ups + Recent Clients -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold mb-0 text-primary">Today's Scheduled Follow-ups</h6>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" id="todayFollowupListBadge">0</span>
                </div>
                <a href="/follow-ups" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Time</th>
                                <th>Notes</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="todayFollowupsTableBody">
                            <tr><td colspan="5" class="text-center py-4 text-muted">Loading follow-ups...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold mb-0 text-primary">Recently Registered Clients</h6>
                </div>
                <a href="/clients" class="btn btn-sm btn-outline-primary">Directory</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th>Client Name</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th class="text-end">Profile</th>
                            </tr>
                        </thead>
                        <tbody id="recentClientsTableBody">
                            <tr><td colspan="5" class="text-center py-4 text-muted">Loading recent clients...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Mark Done Modal -->
<div class="modal fade" id="dashboardMarkDoneModal" tabindex="-1" aria-labelledby="dashMarkDoneLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="dashMarkDoneLabel">Complete Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="dashMarkDoneForm">
                <input type="hidden" id="dashMarkDoneId" value="">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Record discussion summary or key takeaways for this interaction.</p>
                    <div class="mb-3">
                        <label for="dash_outcome_note" class="form-label small fw-semibold">Outcome Note *</label>
                        <textarea class="form-control" id="dash_outcome_note" name="outcome" rows="3" placeholder="e.g. Client agreed to move forward; sent agreement..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="dashSubmitMarkDoneBtn">Mark as Done</button>
                </div>
            </form>
        </div>
    </div>
</div>

