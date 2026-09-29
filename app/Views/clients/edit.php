<?php
/** @var array<string, mixed> $client */
$clientId = (int)($clientId ?? $client['id'] ?? 0);
$clientType = (string)($client['client_type'] ?? 'individual');
$status = (string)($client['status'] ?? 'new');
$currentUserId = (int)(\App\Core\Session::get('user_id') ?? 0);
$isSales = !\App\Services\PermissionService::can('client.view_all');
?>
<div class="row">
    <div class="col-12 col-xl-10 mx-auto">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/clients" class="text-decoration-none">Clients</a></li>
                        <li class="breadcrumb-item"><a href="/clients/<?= $clientId ?>" class="text-decoration-none font-monospace"><?= e($client['client_code'] ?? "#{$clientId}") ?></a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-1">Edit Client: <?= e($client['name'] ?? '') ?></h3>
                <p class="text-muted mb-0">Update client profile and CRM metadata across the 5 wizard steps.</p>
            </div>
            <a href="/clients/<?= $clientId ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back to Profile</span>
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
                        <div class="step-title small fw-semibold">CRM Details</div>
                    </div>
                    <div class="wizard-step-item px-2" data-step="5">
                        <div class="step-num badge rounded-circle mb-1">5</div>
                        <div class="step-title small fw-semibold">Review</div>
                    </div>
                </div>
            </div>

            <!-- Wizard Form -->
            <form id="clientEditForm" data-client-id="<?= $clientId ?>" novalidate>
                <input type="hidden" name="_csrf_token" id="csrfToken" value="<?= e(\App\Helpers\Csrf::getToken()) ?>">

                <div class="card-body p-4 p-md-5">

                    <!-- STEP 1: Basic Information -->
                    <div class="wizard-pane" id="stepPane1">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">1. Basic Information</h5>

                        <!-- Client Type Radio -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-block">Client Type <span class="text-danger">*</span></label>
                            <div class="btn-group" role="group" aria-label="Client Type Selection">
                                <input type="radio" class="btn-check" name="client_type" id="type_individual" value="individual" <?= $clientType === 'individual' ? 'checked' : '' ?> autocomplete="off">
                                <label class="btn btn-outline-primary px-4" for="type_individual">Individual</label>

                                <input type="radio" class="btn-check" name="client_type" id="type_company" value="company" <?= $clientType === 'company' ? 'checked' : '' ?> autocomplete="off">
                                <label class="btn btn-outline-primary px-4" for="type_company">Company</label>
                            </div>
                        </div>

                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-semibold" id="clientNameLabel"><?= $clientType === 'company' ? 'Company Name' : 'Full Name' ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= e($client['name'] ?? '') ?>" required>
                                <div class="invalid-feedback" id="nameError"></div>
                            </div>

                            <!-- Contact Person (Company only) -->
                            <div class="col-md-6 <?= $clientType === 'company' ? '' : 'd-none' ?>" id="contactPersonWrapper">
                                <label for="contact_person" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= e($client['contact_person'] ?? '') ?>">
                                <div class="invalid-feedback" id="contact_personError"></div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($client['email'] ?? '') ?>" required>
                                <div class="invalid-feedback" id="emailError"></div>
                            </div>

                            <!-- Mobile -->
                            <div class="col-md-6">
                                <label for="mobile" class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+91</span>
                                    <input type="tel" class="form-control font-monospace" id="mobile" name="mobile" value="<?= e($client['mobile'] ?? '') ?>" required maxlength="10">
                                </div>
                                <div class="invalid-feedback" id="mobileError"></div>
                            </div>

                            <!-- Alternate Mobile -->
                            <div class="col-md-6">
                                <label for="alt_mobile" class="form-label fw-semibold">Alternate Mobile</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">+91</span>
                                    <input type="tel" class="form-control font-monospace" id="alt_mobile" name="alt_mobile" value="<?= e($client['alt_mobile'] ?? '') ?>" maxlength="10">
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
                                    <?php
                                    $industries = ['IT & Software', 'Manufacturing', 'Healthcare & Pharma', 'Financial Services', 'Retail & E-commerce', 'Real Estate & Construction', 'Professional Consulting', 'Education & Training', 'Logistics & Supply Chain', 'Other'];
                                    foreach ($industries as $ind):
                                    ?>
                                        <option value="<?= e($ind) ?>" <?= ($client['industry'] ?? '') === $ind ? 'selected' : '' ?>><?= e($ind) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" id="industryError"></div>
                            </div>

                            <!-- Company Size -->
                            <div class="col-md-6">
                                <label for="company_size" class="form-label fw-semibold">Company Size</label>
                                <select class="form-select" id="company_size" name="company_size">
                                    <option value="">Select Company Size</option>
                                    <?php
                                    $sizes = ['1-10' => '1-10 Employees (Micro)', '11-50' => '11-50 Employees (Small)', '51-200' => '51-200 Employees (Medium)', '201-500' => '201-500 Employees (Mid-Market)', '500+' => '500+ Employees (Enterprise)'];
                                    foreach ($sizes as $val => $lbl):
                                    ?>
                                        <option value="<?= e($val) ?>" <?= ($client['company_size'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" id="company_sizeError"></div>
                            </div>

                            <!-- Website -->
                            <div class="col-12">
                                <label for="website" class="form-label fw-semibold">Website</label>
                                <input type="url" class="form-control" id="website" name="website" value="<?= e($client['website'] ?? '') ?>" placeholder="https://example.com">
                                <div class="invalid-feedback" id="websiteError"></div>
                            </div>

                            <!-- GST Number -->
                            <div class="col-md-6">
                                <label for="gst_no" class="form-label fw-semibold">GSTIN Number</label>
                                <input type="text" class="form-control font-monospace text-uppercase" id="gst_no" name="gst_no" value="<?= e($client['gst_no'] ?? '') ?>" maxlength="15" placeholder="e.g. 27AAAAA0000A1Z5">
                                <div class="invalid-feedback" id="gst_noError"></div>
                            </div>

                            <!-- PAN Number -->
                            <div class="col-md-6">
                                <label for="pan_no" class="form-label fw-semibold">PAN Number</label>
                                <input type="text" class="form-control font-monospace text-uppercase" id="pan_no" name="pan_no" value="<?= e($client['pan_no'] ?? '') ?>" maxlength="10" placeholder="ABCDE1234F">
                                <div class="invalid-feedback" id="pan_noError"></div>
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
                                <input type="text" class="form-control" id="address_line1" name="address_line1" value="<?= e($client['address_line1'] ?? '') ?>" required>
                                <div class="invalid-feedback" id="address_line1Error"></div>
                            </div>

                            <!-- Address Line 2 -->
                            <div class="col-12">
                                <label for="address_line2" class="form-label fw-semibold">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= e($client['address_line2'] ?? '') ?>">
                                <div class="invalid-feedback" id="address_line2Error"></div>
                            </div>

                            <!-- Pincode -->
                            <div class="col-md-4">
                                <label for="pincode" class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace" id="pincode" name="pincode" value="<?= e($client['pincode'] ?? '') ?>" required maxlength="6">
                                <div class="invalid-feedback" id="pincodeError"></div>
                            </div>

                            <!-- City -->
                            <div class="col-md-4">
                                <label for="city" class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" value="<?= e($client['city'] ?? '') ?>" required>
                                <div class="invalid-feedback" id="cityError"></div>
                            </div>

                            <!-- State -->
                            <div class="col-md-4">
                                <label for="state" class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                                <select class="form-select" id="state" name="state" required>
                                    <option value="">Select State</option>
                                    <?php
                                    $states = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'];
                                    foreach ($states as $st):
                                    ?>
                                        <option value="<?= e($st) ?>" <?= ($client['state'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" id="stateError"></div>
                            </div>

                            <!-- Country -->
                            <div class="col-md-6">
                                <label for="country" class="form-label fw-semibold">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?= e($client['country'] ?? 'India') ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: CRM Details -->
                    <div class="wizard-pane d-none" id="stepPane4">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">4. CRM Workflow Details</h5>

                        <div class="row g-3">
                            <!-- Lead Source -->
                            <div class="col-md-6">
                                <label for="lead_source" class="form-label fw-semibold">Lead Source</label>
                                <select class="form-select" id="lead_source" name="lead_source">
                                    <option value="">Select Lead Source</option>
                                    <?php
                                    $sources = ['Website', 'Referral', 'Cold Call', 'Social Media', 'Exhibition', 'Partner', 'Other'];
                                    foreach ($sources as $src):
                                    ?>
                                        <option value="<?= e($src) ?>" <?= ($client['lead_source'] ?? '') === $src ? 'selected' : '' ?>><?= e($src) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" id="lead_sourceError"></div>
                            </div>

                            <!-- Assigned To -->
                            <?php if ($isSales): ?>
                                <input type="hidden" name="assigned_to" id="assigned_to" value="<?= e($client['assigned_to'] ?? $currentUserId) ?>">
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label for="assigned_to" class="form-label fw-semibold">Assigned Staff Rep</label>
                                    <select class="form-select" id="assigned_to" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($staffUsers as $staff): ?>
                                            <option value="<?= e($staff['id']) ?>" <?= (int)($client['assigned_to'] ?? 0) === (int)$staff['id'] ? 'selected' : '' ?>>
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
                                    <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>New</option>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <div class="invalid-feedback" id="statusError"></div>
                            </div>

                            <!-- Tags -->
                            <div class="col-md-6">
                                <label for="tags" class="form-label fw-semibold">Tags</label>
                                <input type="text" class="form-control" id="tags" name="tags" value="<?= e($client['tags'] ?? '') ?>" placeholder="e.g. VIP, High Priority">
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label for="notes" class="form-label fw-semibold">Internal Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($client['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 5: Review & Save -->
                    <div class="wizard-pane d-none" id="stepPane5">
                        <h5 class="fw-bold mb-3 text-primary border-bottom pb-2">5. Review Changes</h5>

                        <div class="alert alert-info small d-flex align-items-center gap-2 mb-4">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            <span>Please review all updated information before saving. All changes will be recorded in the client's audit timeline.</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped small align-middle mb-0" id="reviewTable">
                                <tbody>
                                    <!-- Rendered dynamically by client-edit.js -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Wizard Footer Navigation -->
                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary d-none" id="prevStepBtn">
                        &larr; Previous
                    </button>
                    <div></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" id="nextStepBtn">
                            Next Step &rarr;
                        </button>
                        <button type="submit" class="btn btn-success d-none" id="submitEditBtn">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
