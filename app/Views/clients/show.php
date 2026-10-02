<div class="row" id="clientProfileWrapper" data-client-id="<?= (int)($clientId ?? 0) ?>">
    <div class="col-12">
        <!-- Top Breadcrumb & Actions Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/clients" class="text-decoration-none">Clients</a></li>
                        <li class="breadcrumb-item active font-monospace" id="breadcrumbClientCode">Loading...</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold mb-0" id="headerClientName">Client Profile</h4>
                    <span class="badge bg-light text-dark border font-monospace" id="headerClientCode">...</span>
                    <span class="badge" id="headerClientStatus">...</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="/clients" class="btn btn-outline-secondary d-flex align-items-center gap-1">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Directory</span>
                </a>

                <?php if (can('client.edit')): ?>
                <a href="/clients/<?= (int)($clientId ?? 0) ?>/edit" class="btn btn-primary d-flex align-items-center gap-1" id="openEditBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Edit Client</span>
                </a>
                <?php endif; ?>

                <?php if (can('client.delete')): ?>
                <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-1" id="openDeleteModalBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Delete</span>
                </button>
                <?php endif; ?>

                <?php if (can('user.manage')): ?>
                <button type="button" class="btn btn-outline-warning d-flex align-items-center gap-1" id="openAnonymizeModalBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>Anonymize (DPDP)</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4 gap-2" id="clientProfileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold d-flex align-items-center gap-2 py-2 px-3" id="tab-services-btn" data-bs-toggle="pill" data-bs-target="#tab-services-pane" type="button" role="tab">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Services</span>
                    <span class="badge bg-primary text-white ms-1" id="clientServicesCountBadge">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold d-flex align-items-center gap-2 py-2 px-3" id="tab-compliance-btn" data-bs-toggle="pill" data-bs-target="#tab-compliance-pane" type="button" role="tab">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Compliance Details</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold d-flex align-items-center gap-2 py-2 px-3" id="tab-overview-btn" data-bs-toggle="pill" data-bs-target="#tab-overview-pane" type="button" role="tab">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Overview & Details</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold d-flex align-items-center gap-2 py-2 px-3" id="tab-docs-btn" data-bs-toggle="pill" data-bs-target="#tab-docs-pane" type="button" role="tab">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Documents</span>
                    <span class="badge bg-secondary-subtle text-secondary ms-1" id="docsCountBadge">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold d-flex align-items-center gap-2 py-2 px-3" id="tab-followups-btn" data-bs-toggle="pill" data-bs-target="#tab-followups-pane" type="button" role="tab">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Follow-ups</span>
                    <span class="badge bg-secondary-subtle text-secondary ms-1" id="followUpsCountBadge">0</span>
                </button>
            </li>
        </ul>

        <div class="row g-4">
            <!-- Left Column: Tabbed Panes -->
            <div class="col-lg-8">
                <div class="tab-content">
                    
                    <!-- TAB 1: SERVICES & WORK TRACKER -->
                    <div class="tab-pane fade show active" id="tab-services-pane" role="tabpanel">
                        <!-- Subscribed Services Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold mb-0 text-primary">Subscribed Services</h6>
                                    <span class="badge bg-light text-secondary border" id="servicesCountBadge">0</span>
                                </div>
                                <?php if (can('client_service.manage') || can('client.edit')): ?>
                                <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-1" id="openAddServiceModalBtn">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                                    <span>Add Service</span>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="clientServicesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service Name</th>
                                                <th>Frequency</th>
                                                <th class="text-end">Fee (₹)</th>
                                                <th>Assigned Accountant</th>
                                                <th>Start Date</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="clientServicesTableBody">
                                            <tr><td colspan="7" class="text-center py-4 text-muted">Loading subscribed services...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Work Tracker Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold mb-0 text-primary">Work Tracker per Service Period</h6>
                                    <span class="badge bg-light text-secondary border" id="workTrackerCountBadge">0</span>
                                </div>
                                <?php if (can('client_service.manage') || can('client.edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" id="openAddWorkTrackerBtn">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                                    <span>Log Work Period</span>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="workTrackerTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Period</th>
                                                <th>Service</th>
                                                <th class="text-center">Status</th>
                                                <th>Ack No.</th>
                                                <th>Filing Date</th>
                                                <th>Staff</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="workTrackerTableBody">
                                            <tr><td colspan="7" class="text-center py-4 text-muted">Loading work tracker items...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: COMPLIANCE DETAILS -->
                    <div class="tab-pane fade" id="tab-compliance-pane" role="tabpanel">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="fw-bold mb-0 text-primary">Statutory Compliance & Portal Details</h6>
                                <?php if (can('client_service.manage') || can('client.edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" id="openEditComplianceBtn">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Edit Compliance</span>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4 mb-4">
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">GSTIN Number</label>
                                        <span class="fw-bold font-monospace fs-6 text-uppercase text-dark" id="compGstin">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">GST Filing Type</label>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="compGstFilingType">Monthly</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">PAN Number</label>
                                        <span class="fw-bold font-monospace fs-6 text-uppercase text-dark" id="compPan">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">TAN Number</label>
                                        <span class="fw-bold font-monospace text-uppercase" id="compTan">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">CIN / LLPIN</label>
                                        <span class="fw-bold font-monospace text-uppercase" id="compCin">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">PF / ESI Registration Codes</label>
                                        <span class="fw-semibold font-monospace" id="compPfEsi">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Financial Year</label>
                                        <span class="badge bg-secondary-subtle text-secondary border font-monospace" id="compFy">-</span>
                                    </div>
                                </div>

                                <div class="border-top pt-4">
                                    <h6 class="fw-bold text-dark mb-3">Portal Login Notes & Secure Credentials</h6>
                                    
                                    <div class="mb-3">
                                        <label class="text-muted small d-block mb-1">Portal Notes</label>
                                        <div class="bg-light p-3 rounded small text-secondary" id="compPortalNotes">
                                            No portal notes provided.
                                        </div>
                                    </div>

                                    <div class="p-3 rounded border bg-light bg-opacity-50">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="fw-semibold small text-dark">Government & Tax Portal Credentials</div>
                                                <div class="text-muted small">Passwords are encrypted at rest (AES-256-GCM). Visible only to Admin & Accountant.</div>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-dark d-flex align-items-center gap-1" id="revealCredentialsBtn">
                                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    <span id="revealBtnText">Reveal Credentials</span>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 d-none" id="credentialsRevealBox">
                                            <div class="bg-white p-3 rounded border font-monospace small text-dark position-relative">
                                                <pre class="mb-0 text-wrap" id="decryptedCredentialsContent" style="white-space: pre-wrap; font-family: inherit;"></pre>
                                            </div>
                                            <div class="text-muted small mt-1">Access has been recorded in the security audit trail.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: OVERVIEW & DETAILS -->
                    <div class="tab-pane fade" id="tab-overview-pane" role="tabpanel">
                        <!-- Overview Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="fw-bold mb-0 text-primary">Overview & Business Details</h6>
                                <span class="badge bg-light text-primary border" id="viewClientType">...</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Client / Company Name</label>
                                        <span class="fw-semibold" id="viewName">-</span>
                                    </div>
                                    <div class="col-sm-6" id="viewContactPersonWrapper">
                                        <label class="text-muted small d-block">Contact Person</label>
                                        <span class="fw-semibold" id="viewContactPerson">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Email Address</label>
                                        <span class="fw-semibold" id="viewEmail">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Mobile Number</label>
                                        <span class="fw-semibold font-monospace" id="viewMobile">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Alternate Mobile</label>
                                        <span class="fw-semibold font-monospace" id="viewAltMobile">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Website</label>
                                        <span class="fw-semibold" id="viewWebsite">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">GSTIN Number</label>
                                        <span class="fw-semibold font-monospace text-uppercase" id="viewGst">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">PAN Number</label>
                                        <span class="fw-semibold font-monospace text-uppercase" id="viewPan">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Industry</label>
                                        <span class="fw-semibold" id="viewIndustry">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="text-muted small d-block">Company Size</label>
                                        <span class="fw-semibold" id="viewCompanySize">-</span>
                                    </div>
                                    <div class="col-12 border-top pt-3">
                                        <label class="text-muted small d-block">Address</label>
                                        <span class="fw-semibold" id="viewAddress">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: DOCUMENTS -->
                    <div class="tab-pane fade" id="tab-docs-pane" role="tabpanel">
                        <!-- Documents Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold mb-0 text-primary">Client Documents</h6>
                                </div>
                                <?php if (can('client.edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="openUploadDocBtn">
                                    + Upload Document
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="documentsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>File Name</th>
                                                <th>Type</th>
                                                <th>Size</th>
                                                <th>Uploaded</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="documentsTableBody">
                                            <tr><td colspan="5" class="text-center py-4 text-muted">No documents uploaded.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: FOLLOW-UPS -->
                    <div class="tab-pane fade" id="tab-followups-pane" role="tabpanel">
                        <!-- Follow-ups Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold mb-0 text-primary">Follow-ups & Next Steps</h6>
                                </div>
                                <?php if (can('followup.manage')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="openAddFollowUpBtn">
                                    + Schedule Follow-up
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="followUpsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Type</th>
                                                <th>Due Date & Time</th>
                                                <th>Status</th>
                                                <th>Notes & Outcome</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="followUpsTableBody">
                                            <tr><td colspan="5" class="text-center py-4 text-muted">Loading follow-ups...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Column: CRM Meta & Timeline -->
            <div class="col-lg-4">
                <!-- CRM Meta Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold mb-0 text-primary">CRM Details</h6>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-muted">Assigned Representative</span>
                                <span class="fw-semibold" id="viewAssignedTo">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-muted">Lead Source</span>
                                <span class="fw-semibold" id="viewLeadSource">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-muted">Registered On</span>
                                <span class="fw-semibold" id="viewCreatedAt">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-muted">Client Consent</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" id="viewConsent">Consent Verified</span>
                            </li>
                        </ul>

                        <div class="mt-3">
                            <label class="text-muted small fw-semibold d-block mb-1">Tags</label>
                            <div id="viewTagsContainer" class="d-flex flex-wrap gap-1">
                                <span class="text-muted small">None</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="text-muted small fw-semibold d-block mb-1">Notes</label>
                            <p class="small text-muted mb-0 bg-light p-2 rounded" id="viewNotes">No notes added.</p>
                        </div>
                    </div>
                </div>

                <!-- Activity Log Timeline Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold mb-0 text-primary">Activity Timeline</h6>
                    </div>
                    <div class="card-body p-3" style="max-height: 420px; overflow-y: auto;">
                        <div id="activityTimelineList" class="timeline small">
                            <div class="text-muted text-center py-3">Loading activity history...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODALS FOR PHASE 3: SERVICES, COMPLIANCE, WORK TRACKER   -->
<!-- ======================================================== -->

<!-- Modal: Add Client Service -->
<div class="modal fade" id="addClientServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="addClientServiceForm">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Service to Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Service Offering <span class="text-danger">*</span></label>
                    <select name="service_id" id="serviceSelect" class="form-select" required>
                        <option value="">-- Choose Service --</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Billing Frequency <span class="text-danger">*</span></label>
                        <select name="frequency" id="serviceFrequencySelect" class="form-select" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Agreed Fee (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="fee" id="serviceFeeInput" class="form-control font-monospace" placeholder="0.00" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" id="serviceStartDateInput" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="active" selected>Active</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Assigned Accountant</label>
                    <select name="assigned_accountant_id" id="serviceAccountantSelect" class="form-select">
                        <option value="">-- Unassigned / Default --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Engagement Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Specific terms, recurring filing details..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="saveClientServiceBtn">Assign Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Client Service -->
<div class="modal fade" id="editClientServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="editClientServiceForm">
            <input type="hidden" id="editClientServiceId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Update Client Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Service Name</label>
                    <input type="text" id="editClientServiceName" class="form-control" disabled>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Frequency</label>
                        <select name="frequency" id="editClientServiceFrequency" class="form-select">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Agreed Fee (₹)</label>
                        <input type="number" step="0.01" min="0" name="fee" id="editClientServiceFee" class="form-control font-monospace" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Status <span class="text-danger">*</span></label>
                        <select name="status" id="editClientServiceStatus" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Assigned Accountant</label>
                        <select name="assigned_accountant_id" id="editClientServiceAccountant" class="form-select">
                            <option value="">-- Unassigned --</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notes</label>
                    <textarea name="notes" id="editClientServiceNotes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="updateClientServiceBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Log Work Tracker Period -->
<div class="modal fade" id="workTrackerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="workTrackerForm">
            <input type="hidden" id="workTrackerItemId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="workTrackerModalTitle">Log Work Period Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" id="workTrackerServiceWrapper">
                    <label class="form-label fw-semibold small">Subscribed Service <span class="text-danger">*</span></label>
                    <select name="client_service_id" id="trackerServiceSelect" class="form-select" required>
                        <option value="">-- Select Active Service --</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Filing Period <span class="text-danger">*</span></label>
                        <input type="text" name="period" id="trackerPeriodInput" class="form-control" placeholder="e.g. Sep-2026, Q2-2026" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Filing Status <span class="text-danger">*</span></label>
                        <select name="status" id="trackerStatusSelect" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="data_received">Data Received</option>
                            <option value="filed">Filed</option>
                            <option value="acknowledged">Acknowledged</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Acknowledgment No.</label>
                        <input type="text" name="acknowledgment_no" id="trackerAckNoInput" class="form-control font-monospace" placeholder="e.g. AA2709260012345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Filing Date</label>
                        <input type="date" name="filing_date" id="trackerFilingDateInput" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Assigned Staff</label>
                    <select name="assigned_to" id="trackerStaffSelect" class="form-select">
                        <option value="">-- Select Staff --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Remarks / Status Notes</label>
                    <textarea name="notes" id="trackerNotesInput" class="form-control" rows="2" placeholder="e.g. GSTR-3B filed, challan generated..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="saveTrackerBtn">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Compliance Details -->
<div class="modal fade" id="editComplianceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="editComplianceForm">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Statutory Compliance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">GSTIN</label>
                        <input type="text" name="gstin" id="editCompGstin" class="form-control font-monospace text-uppercase" maxlength="15" placeholder="27AAAAA0000A1Z5">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">GST Filing Type</label>
                        <select name="gst_filing_type" id="editCompGstFilingType" class="form-select">
                            <option value="monthly">Monthly</option>
                            <option value="qrmp">QRMP (Quarterly)</option>
                            <option value="composition">Composition Scheme</option>
                            <option value="none">None / Not Registered</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">PAN</label>
                        <input type="text" name="pan" id="editCompPan" class="form-control font-monospace text-uppercase" maxlength="10" placeholder="AAAAA0000A">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">TAN Number</label>
                        <input type="text" name="tan" id="editCompTan" class="form-control font-monospace text-uppercase" maxlength="10" placeholder="MUMA00000A">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">CIN / LLPIN</label>
                        <input type="text" name="cin_llpin" id="editCompCin" class="form-control font-monospace text-uppercase" placeholder="U72900MH2026PTC123456">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">PF / ESI Codes</label>
                        <input type="text" name="pf_esi_codes" id="editCompPfEsi" class="form-control" placeholder="PF: MH/BAN/00000, ESI: 31000000000000000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Financial Year</label>
                        <input type="text" name="financial_year" id="editCompFy" class="form-control font-monospace" placeholder="2026-2027">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Portal Notes (Filing dates, specific instructions)</label>
                    <textarea name="portal_notes" id="editCompPortalNotes" class="form-control" rows="2" placeholder="e.g. File on 18th of month; OTP sent to director mobile..."></textarea>
                </div>

                <div class="border rounded p-3 bg-light">
                    <label class="form-label fw-bold text-dark small mb-1">Government Portal Login Passwords (Encrypted at Rest)</label>
                    <p class="text-muted small mb-2">Passwords are automatically encrypted using AES-256-GCM. Plain text is never stored in the database.</p>
                    <textarea name="portal_credentials" id="editCompCredentials" class="form-control font-monospace small" rows="3" placeholder="GST Portal: User / Pass&#10;Income Tax Portal: User / Pass&#10;TRACES / MCA: User / Pass"></textarea>
                    <div class="text-muted small mt-1">Leave blank to retain previously stored encrypted credentials.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="saveComplianceBtn">Save Compliance Details</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODALS FOR ORIGINAL OVERVIEW, DOCS, FOLLOW-UPS           -->
<!-- ======================================================== -->

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="uploadDocModalLabel">Upload Document Attachment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadDocForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="singleDocInput" class="form-label fw-semibold">Select File (PDF, JPG, PNG)</label>
                        <input class="form-control" type="file" id="singleDocInput" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text small">Maximum file size: 5 MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitUploadDocBtn">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="profileDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title text-danger fw-bold">Delete Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-1">Are you sure you want to delete this client record?</p>
                <p class="text-muted small mb-0">This record will be soft-deleted and removed from the active directory.</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmProfileDeleteBtn">Delete Client</button>
            </div>
        </div>
    </div>
</div>

<!-- DPDP Anonymization Confirmation Modal -->
<div class="modal fade" id="profileAnonymizeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title text-warning fw-bold">DPDP Act Data Anonymization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="alert alert-warning small mb-3">
                    <strong>Notice:</strong> In accordance with Digital Personal Data Protection (DPDP) Act 2023 Section 12 (Right to Erasure), this action will permanently purge all uploaded files and redact/pseudonymize all identifying personal information.
                </div>
                <p class="mb-2">Are you sure you want to permanently anonymize this client?</p>
                <div class="mb-3">
                    <label for="anonymizeConfirmCode" class="form-label small fw-semibold">Type client code <span class="badge bg-light text-danger font-monospace border" id="anonymizeClientCodeTarget"></span> to confirm:</label>
                    <input type="text" class="form-control font-monospace" id="anonymizeConfirmCode" placeholder="Enter client code to confirm..." autocomplete="off">
                </div>
                <p class="text-danger small mb-0 fw-semibold">This operation cannot be reversed.</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmProfileAnonymizeBtn" disabled>Confirm Anonymize</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Follow-up Modal -->
<div class="modal fade" id="scheduleFollowUpModal" tabindex="-1" aria-labelledby="scheduleFollowUpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="scheduleFollowUpModalLabel">Schedule Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="scheduleFollowUpForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="followup_type" class="form-label small fw-semibold">Interaction Type *</label>
                        <select class="form-select" id="followup_type" name="type" required>
                            <option value="call">Phone Call</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="followup_due_at" class="form-label small fw-semibold">Scheduled Date & Time *</label>
                        <input type="datetime-local" class="form-control" id="followup_due_at" name="due_at" required>
                    </div>
                    <div class="mb-3">
                        <label for="followup_notes" class="form-label small fw-semibold">Discussion Agenda / Notes</label>
                        <textarea class="form-control" id="followup_notes" name="notes" rows="3" placeholder="Enter agenda, purpose, or talking points..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitScheduleFollowUpBtn">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Done Modal -->
<div class="modal fade" id="markDoneModal" tabindex="-1" aria-labelledby="markDoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="markDoneModalLabel">Complete Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="markDoneForm">
                <input type="hidden" id="markDoneFollowUpId" value="">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Record discussion summary or key takeaways for this interaction.</p>
                    <div class="mb-3">
                        <label for="mark_done_outcome" class="form-label small fw-semibold">Outcome Note *</label>
                        <textarea class="form-control" id="mark_done_outcome" name="outcome" rows="3" placeholder="e.g. Client agreed to review proposal; promised response by Friday..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="submitMarkDoneBtn">Mark as Done</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include Phase 3 Interactive Client Services Script -->
<script src="/assets/js/client-services.js"></script>
