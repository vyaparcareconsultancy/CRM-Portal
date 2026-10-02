<div class="row">
    <div class="col-12">
        <!-- Page Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Leads Management</h4>
                <p class="text-muted small mb-0">Capture inbound inquiries, track pipeline status, follow up, and convert to clients or students.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- View Mode Toggle -->
                <div class="btn-group" role="group" aria-label="View toggle">
                    <button type="button" class="btn btn-outline-secondary active" id="viewKanbanBtn" title="Kanban Board View">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h4v12H4zM10 6h4v8h-4zM16 6h4v10h-4z"/></svg>
                        <span class="d-none d-sm-inline ms-1">Kanban</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="viewTableBtn" title="Table List View">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <span class="d-none d-sm-inline ms-1">Table</span>
                    </button>
                </div>

                <?php if (!empty($canManageSources)): ?>
                <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-1" id="openSourcesModalBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Lead Sources</span>
                </button>
                <?php endif; ?>

                <?php if (!empty($canManage)): ?>
                <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-1" id="openImportModalBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Import CSV</span>
                </button>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-1" id="openCreateLeadModalBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Add Lead</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="filterSearch" placeholder="Search name, phone, code, notes...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filterStatus">
                            <option value="">All Statuses</option>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="interested">Interested</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filterSource">
                            <option value="">All Lead Sources</option>
                        </select>
                    </div>
                    <?php if (!empty($canViewAll)): ?>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filterAssigned">
                            <option value="">All Counselors</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" id="filterDateFrom" title="From Date">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-light border w-100" id="resetFiltersBtn">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanban Board View -->
        <div id="kanbanViewContainer">
            <div class="kanban-board" id="kanbanBoard">
                <!-- Column: New -->
                <div class="kanban-column lead-status-new" data-status="new">
                    <div class="kanban-column-header">
                        <span class="text-primary fw-bold">New</span>
                        <span class="badge bg-primary rounded-pill count-badge" id="count_new">0</span>
                    </div>
                    <div class="kanban-cards" data-status="new" id="column_new"></div>
                </div>

                <!-- Column: Contacted -->
                <div class="kanban-column lead-status-contacted" data-status="contacted">
                    <div class="kanban-column-header">
                        <span class="text-info fw-bold">Contacted</span>
                        <span class="badge bg-info text-white rounded-pill count-badge" id="count_contacted">0</span>
                    </div>
                    <div class="kanban-cards" data-status="contacted" id="column_contacted"></div>
                </div>

                <!-- Column: Interested -->
                <div class="kanban-column lead-status-interested" data-status="interested">
                    <div class="kanban-column-header">
                        <span class="text-warning fw-bold">Interested</span>
                        <span class="badge bg-warning text-dark rounded-pill count-badge" id="count_interested">0</span>
                    </div>
                    <div class="kanban-cards" data-status="interested" id="column_interested"></div>
                </div>

                <!-- Column: Follow-up -->
                <div class="kanban-column lead-status-follow_up" data-status="follow_up">
                    <div class="kanban-column-header">
                        <span class="text-purple fw-bold" style="color:#7c3aed;">Follow-up</span>
                        <span class="badge rounded-pill count-badge" style="background:#7c3aed;color:#fff;" id="count_follow_up">0</span>
                    </div>
                    <div class="kanban-cards" data-status="follow_up" id="column_follow_up"></div>
                </div>

                <!-- Column: Converted -->
                <div class="kanban-column lead-status-converted" data-status="converted">
                    <div class="kanban-column-header">
                        <span class="text-success fw-bold">Converted</span>
                        <span class="badge bg-success rounded-pill count-badge" id="count_converted">0</span>
                    </div>
                    <div class="kanban-cards" data-status="converted" id="column_converted"></div>
                </div>

                <!-- Column: Lost -->
                <div class="kanban-column lead-status-lost" data-status="lost">
                    <div class="kanban-column-header">
                        <span class="text-danger fw-bold">Lost</span>
                        <span class="badge bg-danger rounded-pill count-badge" id="count_lost">0</span>
                    </div>
                    <div class="kanban-cards" data-status="lost" id="column_lost"></div>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div id="tableViewContainer" style="display: none;">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0" id="leadsTable">
                            <thead class="table-light text-uppercase small text-muted">
                                <tr>
                                    <th>Lead Code</th>
                                    <th>Contact</th>
                                    <th>Source</th>
                                    <th>Interested In</th>
                                    <th>Counselor</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="leadsTableBody">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                        <div>Loading leads...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="leadsPaginationContainer">
                    <div class="small text-muted" id="leadsPaginationInfo">Showing 0 of 0 leads</div>
                    <nav aria-label="Leads pagination">
                        <ul class="pagination pagination-sm mb-0" id="leadsPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create / Edit Lead -->
<div class="modal fade" id="leadModal" tabindex="-1" aria-labelledby="leadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="leadForm" novalidate>
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="leadModalTitle">New Lead Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="leadId" name="id" value="">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="leadName" class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="leadName" name="name" required placeholder="e.g. Ramesh Sharma">
                            <div class="invalid-feedback">Full name is required.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="leadMobile" class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="leadMobile" name="mobile" required maxlength="10" placeholder="10-digit mobile number">
                            <div class="invalid-feedback">Valid 10-digit mobile number required.</div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="leadWhatsapp" class="form-label small fw-semibold mb-0">WhatsApp Number</label>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input" type="checkbox" id="leadWhatsappSame" checked>
                                    <label class="form-check-label small text-muted" for="leadWhatsappSame">Same as mobile</label>
                                </div>
                            </div>
                            <input type="tel" class="form-control" id="leadWhatsapp" name="whatsapp_number" maxlength="10" placeholder="10-digit WhatsApp number">
                        </div>

                        <div class="col-md-6">
                            <label for="leadEmail" class="form-label small fw-semibold">Email Address</label>
                            <input type="email" class="form-control" id="leadEmail" name="email" placeholder="name@example.com">
                        </div>

                        <div class="col-md-6">
                            <label for="leadSourceSelect" class="form-label small fw-semibold">Lead Source <span class="text-danger">*</span></label>
                            <select class="form-select" id="leadSourceSelect" name="lead_source_id" required>
                                <option value="">Select source...</option>
                            </select>
                            <div class="invalid-feedback">Please select a lead source.</div>
                        </div>

                        <div class="col-md-6" id="referredByGroup" style="display: none;">
                            <label for="leadReferredBy" class="form-label small fw-semibold">Referred By <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="leadReferredBy" name="referred_by" placeholder="Name / phone of referrer">
                            <div class="invalid-feedback">Please specify who referred this lead.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold d-block">Interest Category <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="interest_type" id="typeService" value="service" checked>
                                <label class="btn btn-outline-secondary btn-sm" for="typeService">Tax / Compliance</label>

                                <input type="radio" class="btn-check" name="interest_type" id="typeCourse" value="course">
                                <label class="btn btn-outline-secondary btn-sm" for="typeCourse">Training Academy</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="leadInterestedIn" class="form-label small fw-semibold">Interested In <span class="text-danger">*</span></label>
                            <select class="form-select" id="leadInterestedInSelect">
                                <option value="">Select service or course...</option>
                            </select>
                            <input type="text" class="form-control mt-1" id="leadInterestedInCustom" name="interested_in" placeholder="Or custom interest description..." style="display:none;">
                            <div class="invalid-feedback">Please select or type an interest.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="leadStatusSelect" class="form-label small fw-semibold">Status</label>
                            <select class="form-select" id="leadStatusSelect" name="status">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="interested">Interested</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="converted">Converted</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="lostReasonGroup" style="display: none;">
                            <label for="leadLostReason" class="form-label small fw-semibold">Lost Reason <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="leadLostReason" name="lost_reason" placeholder="e.g. Budget too high, Competitor chosen">
                            <div class="invalid-feedback">Please provide a reason for lost lead.</div>
                        </div>

                        <?php if (!empty($canViewAll)): ?>
                        <div class="col-md-6">
                            <label for="leadAssignedTo" class="form-label small fw-semibold">Assigned Counselor</label>
                            <select class="form-select" id="leadAssignedTo" name="assigned_to">
                                <option value="">Assign to counselor...</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label for="leadNotes" class="form-label small fw-semibold">Notes / Conversation Summary</label>
                            <textarea class="form-control" id="leadNotes" name="notes" rows="3" placeholder="Enter background details, requirements, or next action items..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveLeadBtn">Save Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Convert Lead -->
<div class="modal fade" id="convertModal" tabindex="-1" aria-labelledby="convertModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="convertForm">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="convertModalTitle">Convert Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="convertLeadId" value="">
                    
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <strong id="convertLeadName"></strong> (<span id="convertLeadCode"></span>)<br>
                        Interested in: <span id="convertLeadInterest"></span>
                    </div>

                    <p class="small text-muted mb-3">Choose the destination for this lead. You can convert to a Tax/Compliance Client, a Training Academy Student, or both:</p>

                    <!-- Destination: Client -->
                    <div class="card mb-3 p-3 border">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="chkConvertToClient" checked>
                            <label class="form-check-label fw-bold" for="chkConvertToClient">
                                Convert to Client (Tax & Compliance)
                            </label>
                        </div>
                        <div id="convertClientOptions" class="ps-4">
                            <div class="mb-2">
                                <label class="form-label small mb-1">Client Type</label>
                                <select class="form-select form-select-sm" id="convertClientType">
                                    <option value="individual">Individual / Proprietorship</option>
                                    <option value="company">Company / LLP / Partnership</option>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm" id="convertPan" placeholder="PAN (Optional)">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm" id="convertGstin" placeholder="GSTIN (Optional)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Destination: Student -->
                    <div class="card p-3 border">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="chkConvertToStudent">
                            <label class="form-check-label fw-bold" for="chkConvertToStudent">
                                Convert to Student (Training Academy)
                            </label>
                        </div>
                        <div id="convertStudentOptions" class="ps-4" style="display: none;">
                            <div class="mb-2">
                                <label class="form-label small mb-1">Enrolling Course</label>
                                <input type="text" class="form-control form-control-sm" id="convertCourseName" placeholder="Course title">
                            </div>
                            <div>
                                <input type="text" class="form-control form-control-sm" id="convertQualification" placeholder="Academic Qualification (Optional)">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="confirmConvertBtn">Convert Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Prompt Lost Reason -->
<div class="modal fade" id="lostReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form id="lostReasonForm">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-danger">Mark Lead as Lost</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" id="lostModalLeadId" value="">
                    <label for="lostModalReason" class="form-label small fw-semibold">Why was this lead lost? <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" id="lostModalReason" rows="3" required placeholder="e.g. Unresponsive, Price out of budget, Joined another institute"></textarea>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Confirm Lost</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: CSV Import -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="importModalTitle">Import Leads from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-secondary small mb-3">
                        <strong>Duplicate Mobile Check:</strong> Any rows with mobile numbers already present in the database or duplicated within the file will be automatically skipped without halting the import.
                    </div>

                    <div class="mb-3">
                        <label for="csvFileInput" class="form-label small fw-semibold">Choose CSV File <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="csvFileInput" name="file" accept=".csv,text/csv" required>
                        <div class="form-text small">Accepted columns: <code>Name, Mobile, WhatsApp, Email, Source, Interested In, Notes</code></div>
                    </div>

                    <div class="mb-3">
                        <label for="defaultSourceSelect" class="form-label small fw-semibold">Default Lead Source</label>
                        <select class="form-select form-select-sm" id="defaultSourceSelect">
                            <option value="">Auto-detect from file</option>
                        </select>
                    </div>

                    <div id="importResultsAlert" style="display:none;" class="mt-3"></div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="startImportBtn">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Manage Lead Sources (Admin) -->
<div class="modal fade" id="sourcesModal" tabindex="-1" aria-labelledby="sourcesModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="sourcesModalTitle">Manage Lead Sources</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Add new source form -->
                <form id="addSourceForm" class="d-flex gap-2 mb-3">
                    <input type="text" class="form-control form-control-sm" id="newSourceName" placeholder="e.g. LinkedIn, Seminar, Newspaper" required>
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">+ Add Source</button>
                </form>

                <!-- List of sources -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Source Name</th>
                                <th>Status</th>
                                <th class="text-end">Toggle</th>
                            </tr>
                        </thead>
                        <tbody id="sourcesTableBody">
                            <tr><td colspan="3" class="text-center text-muted py-3">Loading sources...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Schedule Follow-up (Quick) -->
<div class="modal fade" id="leadFollowUpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="leadFollowUpForm">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Schedule Follow-up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="fuTargetLeadId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contact</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="fuTargetName" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="fuDueAt" class="form-label small fw-semibold">Due Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="fuDueAt" required>
                    </div>

                    <div class="mb-3">
                        <label for="fuType" class="form-label small fw-semibold">Interaction Mode <span class="text-danger">*</span></label>
                        <select class="form-select" id="fuType" required>
                            <option value="call">Call</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="visit">Visit / Walk-in</option>
                            <option value="email">Email</option>
                            <option value="meeting">Meeting</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="fuNotes" class="form-label small fw-semibold">Remarks / Discussion Points</label>
                        <textarea class="form-control" id="fuNotes" rows="2" placeholder="e.g. Call regarding quotation and next batch seat availability"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveFollowUpBtn">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>
