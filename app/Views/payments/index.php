<?php
$title = 'Payments & Billing Ledger';
$pageHeading = 'Finance & Payments';
$currentPath = '/payments';
require_once __DIR__ . '/../layouts/app.php';
?>

<div class="container-fluid p-4">
    <!-- Header Row -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Payments & Billing Ledger</h1>
            <p class="text-secondary small mb-0">Record and track client services invoices, student course fees, installments, and receipts.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (can('invoice.manage')): ?>
            <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newInvoiceModal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                <span>New Invoice</span>
            </button>
            <?php endif; ?>

            <?php if (can('payment.record')): ?>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Record Payment</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4" id="kpiCards">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase">Total Collections</span>
                    <h3 class="fw-bold text-dark mb-0 mt-2" id="kpiTotalCollected">₹0.00</h3>
                    <small class="text-success"><span id="kpiTotalCount">0</span> payments recorded</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase">Today's Inflow</span>
                    <h3 class="fw-bold text-success mb-0 mt-2" id="kpiTodayTotal">₹0.00</h3>
                    <small class="text-secondary">Collected today</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase">Bank & UPI</span>
                    <h3 class="fw-bold text-primary mb-0 mt-2" id="kpiBankUpiTotal">₹0.00</h3>
                    <small class="text-secondary">Online / Cheque / Card</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <span class="text-muted small fw-medium text-uppercase">Cash Collections</span>
                    <h3 class="fw-bold text-secondary mb-0 mt-2" id="kpiCashTotal">₹0.00</h3>
                    <small class="text-secondary">Physical cash received</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom border-light p-3">
            <ul class="nav nav-tabs card-header-tabs" id="financeTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-semibold" id="payments-tab" data-bs-toggle="tab" data-bs-target="#paymentsTabPane" type="button" role="tab">
                        Payment Transactions
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoicesTabPane" type="button" role="tab">
                        Invoices & Billing
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3">
            <!-- Filter Bar -->
            <div class="bg-light p-3 rounded-3 mb-4">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-3">
                        <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Search receipt, ref, name...">
                    </div>
                    <div class="col-6 col-md-2">
                        <select class="form-select form-select-sm" id="filterPaymentMode">
                            <option value="">All Payment Modes</option>
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2" id="invoiceStatusFilterWrapper" style="display: none;">
                        <select class="form-select form-select-sm" id="filterInvoiceStatus">
                            <option value="">All Invoice Statuses</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <input type="date" class="form-control form-control-sm" id="filterDateFrom" title="From Date">
                    </div>
                    <div class="col-6 col-md-2">
                        <input type="date" class="form-control form-control-sm" id="filterDateTo" title="To Date">
                    </div>
                    <div class="col-12 col-md-1 d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-primary w-100" id="btnApplyFilters">Apply</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilters" title="Reset">×</button>
                    </div>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="financeTabsContent">
                <!-- 1. Payments Tab -->
                <div class="tab-pane fade show active" id="paymentsTabPane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="paymentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Date</th>
                                    <th>Customer / Student</th>
                                    <th>Invoice Ref</th>
                                    <th class="text-end">Amount</th>
                                    <th>Mode</th>
                                    <th>Reference No</th>
                                    <th>Received By</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTableBody">
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">Loading payments...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3" id="paymentsPagination">
                        <small class="text-secondary" id="paymentsPageInfo"></small>
                        <div class="btn-group btn-group-sm" id="paymentsPageBtns"></div>
                    </div>
                </div>

                <!-- 2. Invoices Tab -->
                <div class="tab-pane fade" id="invoicesTabPane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="invoicesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Title</th>
                                    <th>Customer / Student</th>
                                    <th class="text-end">Net Amount</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance Due</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoicesTableBody">
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">Loading invoices...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3" id="invoicesPagination">
                        <small class="text-secondary" id="invoicesPageInfo"></small>
                        <div class="btn-group btn-group-sm" id="invoicesPageBtns"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Record Payment -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="recordPaymentForm">
                <?= csrf_field() ?>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Invoice <span class="text-danger">*</span></label>
                        <select class="form-select" id="pmtInvoiceSelect" name="invoice_id" required>
                            <option value="">-- Choose an unpaid/partial invoice --</option>
                        </select>
                        <small class="text-muted" id="invoiceDetailsPreview"></small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="pmtAmount" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="pmtDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_mode" id="pmtMode" required>
                                <option value="upi">UPI</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer / NEFT</option>
                                <option value="cheque">Cheque</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Ref / UTR / Cheque No.</label>
                            <input type="text" class="form-control" name="reference_no" id="pmtRef" placeholder="e.g. UPI/12345/TXN">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Remarks</label>
                        <textarea class="form-control" name="notes" id="pmtNotes" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitPayment">Save & Generate Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: New Invoice -->
<div class="modal fade" id="newInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="newInvoiceForm">
                <?= csrf_field() ?>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Create Invoice / Fee Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bill To: Client</label>
                            <select class="form-select" name="client_id" id="invClientSelect">
                                <option value="">-- None / Student only --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bill To: Student</label>
                            <select class="form-select" name="student_id" id="invStudentSelect">
                                <option value="">-- None / Client only --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Invoice Title / Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="invTitle" placeholder="e.g. GST Annual Return Filing / Full Stack Web Dev Fee" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Total Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="total_amount" id="invTotalAmount" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Discount (₹)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="discount_amount" id="invDiscount" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">GST Rate</label>
                            <select class="form-select" name="gst_rate_pct" id="invGstRate">
                                <option value="0">0% (Nil)</option>
                                <option value="5">5%</option>
                                <option value="12">12%</option>
                                <option value="18" selected>18% (Standard)</option>
                                <option value="28">28%</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Net Payable (₹)</label>
                            <input type="text" class="form-control bg-light fw-bold" id="invNetPreview" readonly value="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Issue Date</label>
                            <input type="date" class="form-control" name="issue_date" id="invIssueDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="due_date" id="invDueDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Installment Plan Section -->
                    <div class="card border border-light-subtle rounded-3 p-3 bg-light mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="chkEnableInstallments">
                            <label class="form-check-label fw-semibold" for="chkEnableInstallments">Enable Course Fee Installment Plan</label>
                        </div>
                        <div id="installmentPlanContainer" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-secondary">Specify due dates and amounts for installments:</small>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddInstallmentRow">+ Add Installment</button>
                            </div>
                            <div id="installmentRows"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Terms</label>
                        <textarea class="form-control" name="notes" id="invNotes" rows="2" placeholder="Optional invoice notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitInvoice">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Email Receipt -->
<div class="modal fade" id="emailReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="emailReceiptForm">
                <?= csrf_field() ?>
                <input type="hidden" id="emailReceiptPmtId">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Email Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-secondary mb-3">Send official PDF payment receipt (<span id="emailReceiptNumberBadge" class="fw-bold text-dark"></span>) directly to the recipient.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="emailReceiptInput" required placeholder="client@example.com">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSendEmailReceipt">Send Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/payments.js"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
