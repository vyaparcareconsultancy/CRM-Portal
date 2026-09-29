<div class="row">
    <div class="col-12 col-xl-10 mx-auto">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1">New Client Registration</h3>
                <p class="text-muted mb-0">Complete the 5-step registration to enroll a new client into the CRM.</p>
            </div>
            <a href="/clients" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back to Clients</span>
            </a>
        </div>

        <!-- Wizard Card -->
        <div class="card shadow-sm border-0 mb-5">
            <!-- Stepper Navigation Bar -->
            <div class="card-header bg-white p-3 border-bottom">
                <div class="progress mb-3" style="height: 6px;">
                    <div id="wizardProgressBar" class="progress-bar bg-primary" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="d-flex justify-content-between text-center overflow-auto py-1">
                    <div class="wizard-step-item active px-2" data-step="1">
                        <div class="step-num badge rounded-circle mb-1">1</div>
                        <div class="step-title small fw-semibold">Basic</div>
                    </div>
                    <div class="wizard-step-item px-2" data-step="2">
                        <div class="step-num badge rounded-circle mb-1">2</div>
                        <div class="step-title small fw-semibold">Business</div>
                    </div>
                    <div class="wizard-step-item px-2" data-step="3">
                        <div class="step-num badge rounded-circle mb-1">3</div>
                        <div class="step-title small fw-semibold">Address</div>
                    </div>
                    <div class="wizard-step-item px-2" data-step="4">
                        <div class="step-num badge rounded-circle mb-1">4</div>
                        <div class="step-title small fw-semibold">CRM & Files</div>
                    </div>
                    <div class="wizard-step-item px-2" data-step="5">
                        <div class="step-num badge rounded-circle mb-1">5</div>
                        <div class="step-title small fw-semibold">Review</div>
                    </div>
                </div>
            </div>

            <!-- Wizard Form -->
            <form id="clientCreateForm" novalidate enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" id="csrfToken" value="<?= e(\App\Helpers\Csrf::getToken()) ?>">

                <div class="card-body p-4 p-md-5">

                    <!-- STEP 1: Basic Information -->
                    <div class="wizard-pane" id="stepPane1">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">1. Basic Information</h5>

                        <!-- Client Type Radio -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-block">Client Type <span class="text-danger">*</span></label>
                            <div class="btn-group" role="group" aria-label="Client Type Selection">
                                <input type="radio" class="btn-check" name="client_type" id="type_individual" value="individual" checked autocomplete="off">
                                <label class="btn btn-outline-primary px-4" for="type_individual">Individual</label>

                                <input type="radio" class="btn-check" name="client_type" id="type_company" value="company" autocomplete="off">
                                <label class="btn btn-outline-primary px-4" for="type_company">Company</label>
                            </div>
                        </div>

                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-semibold" id="clientNameLabel">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required placeholder="John Doe or Acme Corp">
                                <div class="invalid-feedback" id="nameError"></div>
                            </div>

                            <!-- Contact Person (Company only) -->
                            <div class="col-md-6 d-none" id="contactPersonWrapper">
                                <label for="contact_person" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" placeholder="Primary contact name">
                                <div class="invalid-feedback" id="contact_personError"></div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="client@company.com">
                                <div class="invalid-feedback" id="emailError"></div>
                            </div>

                            <!-- Mobile -->
                            <div class="col-md-6">
                                <label for="mobile" class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+91</span>
                                    <input type="tel" class="form-control" id="mobile" name="mobile" required maxlength="10" placeholder="9876543210">
                                </div>
                                <div class="invalid-feedback" id="mobileError"></div>
                                <div class="form-text small">10-digit Indian mobile number starting with 6-9.</div>
                            </div>

                            <!-- Alternate Mobile -->
                            <div class="col-md-6">
                                <label for="alt_mobile" class="form-label fw-semibold">Alternate Mobile</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+91</span>
                                    <input type="tel" class="form-control" id="alt_mobile" name="alt_mobile" maxlength="10" placeholder="Optional phone number">
                                </div>
                                <div class="invalid-feedback" id="alt_mobileError"></div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: Business Details -->
                    <div class="wizard-pane d-none" id="stepPane2">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">2. Business Details</h5>

                        <div class="row g-3">
                            <!-- Industry -->
                            <div class="col-md-6">
                                <label for="industry" class="form-label fw-semibold">Industry</label>
                                <select class="form-select" id="industry" name="industry">
                                    <option value="">Select Industry</option>
                                    <option value="IT & Software">IT & Software</option>
                                    <option value="Manufacturing">Manufacturing</option>
                                    <option value="Healthcare & Pharma">Healthcare & Pharma</option>
                                    <option value="Financial Services">Financial Services</option>
                                    <option value="Retail & E-commerce">Retail & E-commerce</option>
                                    <option value="Real Estate & Construction">Real Estate & Construction</option>
                                    <option value="Professional Consulting">Professional Consulting</option>
                                    <option value="Education & Training">Education & Training</option>
                                    <option value="Logistics & Supply Chain">Logistics & Supply Chain</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="invalid-feedback" id="industryError"></div>
                            </div>

                            <!-- Company Size -->
                            <div class="col-md-6">
                                <label for="company_size" class="form-label fw-semibold">Company Size</label>
                                <select class="form-select" id="company_size" name="company_size">
                                    <option value="">Select Company Size</option>
                                    <option value="1-10">1-10 Employees (Micro)</option>
                                    <option value="11-50">11-50 Employees (Small)</option>
                                    <option value="51-200">51-200 Employees (Medium)</option>
                                    <option value="201-500">201-500 Employees (Mid-Market)</option>
                                    <option value="500+">500+ Employees (Enterprise)</option>
                                </select>
                                <div class="invalid-feedback" id="company_sizeError"></div>
                            </div>

                            <!-- Website -->
                            <div class="col-12">
                                <label for="website" class="form-label fw-semibold">Website</label>
                                <input type="url" class="form-control" id="website" name="website" placeholder="https://example.com">
                                <div class="invalid-feedback" id="websiteError"></div>
                            </div>

                            <!-- GST Number -->
                            <div class="col-md-6">
                                <label for="gst_no" class="form-label fw-semibold">GSTIN Number</label>
                                <input type="text" class="form-control font-monospace text-uppercase" id="gst_no" name="gst_no" maxlength="15" placeholder="27AAAAA0000A1Z5">
                                <div class="invalid-feedback" id="gst_noError"></div>
                                <div class="form-text small">15-digit GST identification number.</div>
                            </div>

                            <!-- PAN Number -->
                            <div class="col-md-6">
                                <label for="pan_no" class="form-label fw-semibold">PAN Number</label>
                                <input type="text" class="form-control font-monospace text-uppercase" id="pan_no" name="pan_no" maxlength="10" placeholder="ABCDE1234F">
                                <div class="invalid-feedback" id="pan_noError"></div>
                                <div class="form-text small">10-character Permanent Account Number.</div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Address Details -->
                    <div class="wizard-pane d-none" id="stepPane3">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">3. Address Information</h5>

                        <div class="row g-3">
                            <!-- Address Line 1 -->
                            <div class="col-12">
                                <label for="address_line1" class="form-label fw-semibold">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address_line1" name="address_line1" required placeholder="Building, Street, Landmark">
                                <div class="invalid-feedback" id="address_line1Error"></div>
                            </div>

                            <!-- Address Line 2 -->
                            <div class="col-12">
                                <label for="address_line2" class="form-label fw-semibold">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line2" name="address_line2" placeholder="Suite, Floor, Area (Optional)">
                                <div class="invalid-feedback" id="address_line2Error"></div>
                            </div>

                            <!-- Pincode -->
                            <div class="col-md-4">
                                <label for="pincode" class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace" id="pincode" name="pincode" required maxlength="6" placeholder="400001">
                                <div class="invalid-feedback" id="pincodeError"></div>
                            </div>

                            <!-- City -->
                            <div class="col-md-4">
                                <label for="city" class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" required placeholder="e.g. Mumbai">
                                <div class="invalid-feedback" id="cityError"></div>
                            </div>

                            <!-- State -->
                            <div class="col-md-4">
                                <label for="state" class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                                <select class="form-select" id="state" name="state" required>
                                    <option value="">Select State</option>
                                    <option value="Andhra Pradesh">Andhra Pradesh</option>
                                    <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                    <option value="Assam">Assam</option>
                                    <option value="Bihar">Bihar</option>
                                    <option value="Chhattisgarh">Chhattisgarh</option>
                                    <option value="Goa">Goa</option>
                                    <option value="Gujarat">Gujarat</option>
                                    <option value="Haryana">Haryana</option>
                                    <option value="Himachal Pradesh">Himachal Pradesh</option>
                                    <option value="Jharkhand">Jharkhand</option>
                                    <option value="Karnataka">Karnataka</option>
                                    <option value="Kerala">Kerala</option>
                                    <option value="Madhya Pradesh">Madhya Pradesh</option>
                                    <option value="Maharashtra">Maharashtra</option>
                                    <option value="Manipur">Manipur</option>
                                    <option value="Meghalaya">Meghalaya</option>
                                    <option value="Mizoram">Mizoram</option>
                                    <option value="Nagaland">Nagaland</option>
                                    <option value="Odisha">Odisha</option>
                                    <option value="Punjab">Punjab</option>
                                    <option value="Rajasthan">Rajasthan</option>
                                    <option value="Sikkim">Sikkim</option>
                                    <option value="Tamil Nadu">Tamil Nadu</option>
                                    <option value="Telangana">Telangana</option>
                                    <option value="Tripura">Tripura</option>
                                    <option value="Uttar Pradesh">Uttar Pradesh</option>
                                    <option value="Uttarakhand">Uttarakhand</option>
                                    <option value="West Bengal">West Bengal</option>
                                    <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                                    <option value="Chandigarh">Chandigarh</option>
                                    <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                                    <option value="Delhi">Delhi (NCT)</option>
                                    <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                    <option value="Ladakh">Ladakh</option>
                                    <option value="Lakshadweep">Lakshadweep</option>
                                    <option value="Puducherry">Puducherry</option>
                                </select>
                                <div class="invalid-feedback" id="stateError"></div>
                            </div>

                            <!-- Country -->
                            <div class="col-md-6">
                                <label for="country" class="form-label fw-semibold">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="India" readonly>
                            </div>

                            <!-- Sync Checkbox -->
                            <div class="col-12 mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="billingSameAsAddress" checked>
                                    <label class="form-check-label small" for="billingSameAsAddress">
                                        Billing address is the same as the primary address
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: CRM & Documents -->
                    <div class="wizard-pane d-none" id="stepPane4">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">4. CRM Workflow & Attachments</h5>

                        <div class="row g-3">
                            <!-- Lead Source -->
                            <div class="col-md-6">
                                <label for="lead_source" class="form-label fw-semibold">Lead Source</label>
                                <select class="form-select" id="lead_source" name="lead_source">
                                    <option value="">Select Lead Source</option>
                                    <option value="Website">Website Form</option>
                                    <option value="Referral">Client Referral</option>
                                    <option value="Cold Call">Cold Outreach</option>
                                    <option value="Social Media">Social Media</option>
                                    <option value="Exhibition">Exhibition / Event</option>
                                    <option value="Partner">Channel Partner</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="invalid-feedback" id="lead_sourceError"></div>
                            </div>

                            <!-- Assigned To (Hidden for sales reps, visible for admin/manager) -->
                            <?php if ($isSales): ?>
                                <input type="hidden" name="assigned_to" id="assigned_to" value="<?= e($currentUserId) ?>">
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label for="assigned_to" class="form-label fw-semibold">Assigned Staff Rep</label>
                                    <select class="form-select" id="assigned_to" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($staffUsers as $staff): ?>
                                            <option value="<?= e($staff['id']) ?>" <?= (int)$staff['id'] === (int)$currentUserId ? 'selected' : '' ?>>
                                                <?= e($staff['name']) ?> (<?= e($staff['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback" id="assigned_toError"></div>
                                </div>
                            <?php endif; ?>

                            <!-- Status -->
                            <div class="col-md-6">
                                <label for="status" class="form-label fw-semibold">Client Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="new" selected>New</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="invalid-feedback" id="statusError"></div>
                            </div>

                            <!-- Tags -->
                            <div class="col-md-6">
                                <label for="tags" class="form-label fw-semibold">Tags</label>
                                <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g. VIP, High Priority, Q3 Lead">
                                <div class="form-text small">Comma-separated tags for easy search.</div>
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label for="notes" class="form-label fw-semibold">Internal Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter key background information or client expectations..."></textarea>
                            </div>

                            <!-- File Upload Area -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Upload Client Documents</label>
                                <div class="border rounded p-3 bg-light">
                                    <input type="file" class="form-control mb-2" id="documentUploadInput" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                    <div class="form-text small mb-2">Accepted formats: <strong>PDF, JPG, PNG</strong>. Max size: <strong>5 MB per file</strong>. Maximum <strong>3 files</strong>.</div>
                                    <div id="selectedFilesList">
                                        <span class="text-muted small">No documents selected (optional).</span>
                                    </div>
                                </div>
                            </div>

                            <!-- DPDP / GDPR Consent Checkbox -->
                            <div class="col-12 mt-4">
                                <div class="card border-primary-subtle bg-primary-subtle p-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="consent_given" name="consent_given" value="1" required>
                                        <label class="form-check-label fw-semibold text-dark" for="consent_given">
                                            Consent Confirmation <span class="text-danger">*</span>
                                        </label>
                                        <p class="small text-muted mb-0 mt-1">
                                            I verify that explicit consent has been collected from the client to store, manage, and process their contact and business information in accordance with DPDP compliance guidelines.
                                        </p>
                                        <div class="invalid-feedback" id="consent_givenError"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 5: Review & Submit -->
                    <div class="wizard-pane d-none" id="stepPane5">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">5. Review Registration Summary</h5>
                        <p class="text-muted small mb-4">Please verify all entered details before submitting. You may click "Edit" on any section to make updates.</p>

                        <div class="row g-3">
                            <!-- Section 1 Review -->
                            <div class="col-md-6">
                                <div class="card h-100 shadow-none border">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                        <span class="fw-bold small text-uppercase">Basic Information</span>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-goto-step="1">Edit</button>
                                    </div>
                                    <div class="card-body p-3 small">
                                        <div class="mb-1"><strong class="text-muted">Type:</strong> <span id="rev_client_type">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Name:</strong> <span id="rev_name" class="fw-bold">—</span></div>
                                        <div class="mb-1 d-none" id="rev_contact_person_row"><strong class="text-muted">Contact:</strong> <span id="rev_contact_person">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Email:</strong> <span id="rev_email">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Mobile:</strong> <span id="rev_mobile">—</span></div>
                                        <div><strong class="text-muted">Alt Mobile:</strong> <span id="rev_alt_mobile">—</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2 Review -->
                            <div class="col-md-6">
                                <div class="card h-100 shadow-none border">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                        <span class="fw-bold small text-uppercase">Business Details</span>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-goto-step="2">Edit</button>
                                    </div>
                                    <div class="card-body p-3 small">
                                        <div class="mb-1"><strong class="text-muted">Industry:</strong> <span id="rev_industry">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Size:</strong> <span id="rev_company_size">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Website:</strong> <span id="rev_website">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">GSTIN:</strong> <span id="rev_gst_no" class="font-monospace">—</span></div>
                                        <div><strong class="text-muted">PAN:</strong> <span id="rev_pan_no" class="font-monospace">—</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3 Review -->
                            <div class="col-md-6">
                                <div class="card h-100 shadow-none border">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                        <span class="fw-bold small text-uppercase">Address Information</span>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-goto-step="3">Edit</button>
                                    </div>
                                    <div class="card-body p-3 small">
                                        <div class="mb-1"><strong class="text-muted">Address:</strong> <span id="rev_address">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Location:</strong> <span id="rev_city_state">—</span></div>
                                        <div><strong class="text-muted">Country:</strong> <span id="rev_country">India</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 4 Review -->
                            <div class="col-md-6">
                                <div class="card h-100 shadow-none border">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                        <span class="fw-bold small text-uppercase">CRM Details & Files</span>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-goto-step="4">Edit</button>
                                    </div>
                                    <div class="card-body p-3 small">
                                        <div class="mb-1"><strong class="text-muted">Source:</strong> <span id="rev_lead_source">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Assigned To:</strong> <span id="rev_assigned_to">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Status:</strong> <span id="rev_status" class="badge bg-secondary-subtle text-secondary">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Tags:</strong> <span id="rev_tags">—</span></div>
                                        <div class="mb-1"><strong class="text-muted">Documents:</strong> <span id="rev_documents">—</span></div>
                                        <div><strong class="text-muted">Notes:</strong> <span id="rev_notes">—</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer Navigation Buttons -->
                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-secondary px-4" id="prevStepBtn" disabled>
                        &larr; Previous
                    </button>

                    <div>
                        <button type="button" class="btn btn-primary px-4 fw-semibold" id="nextStepBtn">
                            Next Step &rarr;
                        </button>
                        <button type="submit" class="btn btn-success px-4 fw-semibold d-none" id="submitClientBtn">
                            Submit Registration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Stepper Pill Styling */
.wizard-step-item {
    cursor: default;
    color: var(--text-muted);
    min-width: 80px;
}
.wizard-step-item .step-num {
    background-color: var(--border-color);
    color: var(--text-muted);
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}
.wizard-step-item.active .step-num {
    background-color: var(--primary);
    color: #ffffff;
    box-shadow: 0 0 0 4px var(--primary-light);
}
.wizard-step-item.active .step-title {
    color: var(--primary);
}
.wizard-step-item.completed .step-num {
    background-color: #10b981;
    color: #ffffff;
}
.wizard-step-item.completed .step-title {
    color: #10b981;
}
@media (max-width: 420px) {
    .wizard-step-item { min-width: 60px; }
    .wizard-step-item .step-title { font-size: 0.7rem; }
}
</style>
