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

        <div class="row g-4">
            <!-- Left Column: Details -->
            <div class="col-lg-8">
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

                <!-- Documents Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0 text-primary">Client Documents</h6>
                            <span class="badge bg-light text-secondary border" id="docsCountBadge">0</span>
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

                <!-- Follow-ups Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0 text-primary">Follow-ups & Next Steps</h6>
                            <span class="badge bg-light text-secondary border" id="followUpsCountBadge">0</span>
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

