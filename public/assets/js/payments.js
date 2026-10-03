/**
 * Payments & Invoicing Ledger JavaScript Controller
 */
document.addEventListener('DOMContentLoaded', () => {
    let currentTab = 'payments';
    let pmtPage = 1;
    let invPage = 1;
    let availableInvoices = [];

    const getCsrfToken = () => document.querySelector('input[name="csrf_token"]')?.value || '';

    // Elements
    const paymentsTableBody = document.getElementById('paymentsTableBody');
    const invoicesTableBody = document.getElementById('invoicesTableBody');
    const filterSearch = document.getElementById('filterSearch');
    const filterPaymentMode = document.getElementById('filterPaymentMode');
    const filterInvoiceStatus = document.getElementById('filterInvoiceStatus');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    const btnApplyFilters = document.getElementById('btnApplyFilters');
    const btnResetFilters = document.getElementById('btnResetFilters');
    const invoiceStatusWrapper = document.getElementById('invoiceStatusFilterWrapper');

    // Tab switcher
    document.getElementById('payments-tab')?.addEventListener('shown.bs.tab', () => {
        currentTab = 'payments';
        invoiceStatusWrapper.style.display = 'none';
        filterPaymentMode.parentElement.style.display = 'block';
        loadPayments(1);
    });

    document.getElementById('invoices-tab')?.addEventListener('shown.bs.tab', () => {
        currentTab = 'invoices';
        invoiceStatusWrapper.style.display = 'block';
        filterPaymentMode.parentElement.style.display = 'none';
        loadInvoices(1);
    });

    // Formatting helpers
    const formatCurrency = (amount) => {
        const val = parseFloat(amount || 0);
        return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const statusBadge = (status) => {
        const map = {
            'paid': '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Paid</span>',
            'partially_paid': '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">Partially Paid</span>',
            'unpaid': '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Unpaid</span>',
            'overdue': '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Overdue</span>',
            'cancelled': '<span class="badge bg-dark-subtle text-muted border border-dark-subtle px-2 py-1">Cancelled</span>'
        };
        return map[status] || `<span class="badge bg-secondary">${status}</span>`;
    };

    // 1. Load Payments
    async function loadPayments(page = 1) {
        pmtPage = page;
        paymentsTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading payments...</td></tr>';

        const params = new URLSearchParams({
            page: pmtPage,
            per_page: 15,
            search: filterSearch.value.trim(),
            payment_mode: filterPaymentMode.value,
            date_from: filterDateFrom.value,
            date_to: filterDateTo.value
        });

        try {
            const res = await fetch(`/api/payments?${params.toString()}`);
            const data = await res.json();

            if (data.status === 'success') {
                renderPayments(data.data.items || []);
                renderPagination('payments', data.data);

                // Update KPI Cards
                if (data.data.totals) {
                    const totals = data.data.totals;
                    document.getElementById('kpiTotalCollected').textContent = formatCurrency(totals.total_collected);
                    document.getElementById('kpiTodayTotal').textContent = formatCurrency(totals.today_total);
                    document.getElementById('kpiBankUpiTotal').textContent = formatCurrency(totals.bank_upi_total);
                    document.getElementById('kpiCashTotal').textContent = formatCurrency(totals.cash_total);
                    document.getElementById('kpiTotalCount').textContent = data.data.total || 0;
                }
            } else {
                paymentsTableBody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">${data.message || 'Error loading payments'}</td></tr>`;
            }
        } catch (e) {
            paymentsTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Failed to connect to payments API</td></tr>';
        }
    }

    function renderPayments(items) {
        if (!items.length) {
            paymentsTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No payment records found matching criteria.</td></tr>';
            return;
        }

        paymentsTableBody.innerHTML = items.map(p => {
            const customer = p.client_name 
                ? `<strong>${p.client_name}</strong> <span class="badge bg-light text-dark border ms-1">${p.client_code || ''}</span>`
                : (p.student_name ? `<strong>${p.student_name}</strong> <span class="badge bg-info-subtle text-info border ms-1">${p.student_code || 'Student'}</span>` : 'Direct');

            return `
                <tr>
                    <td><strong class="text-primary">${p.receipt_no}</strong></td>
                    <td class="text-nowrap">${p.payment_date}</td>
                    <td>${customer}</td>
                    <td><small class="text-muted">${p.invoice_no}</small><br><span class="small">${p.invoice_title || ''}</span></td>
                    <td class="text-end fw-bold text-success">${formatCurrency(p.amount)}</td>
                    <td><span class="badge bg-light text-uppercase border">${p.payment_mode}</span></td>
                    <td><small class="text-muted font-monospace">${p.reference_no || '-'}</small></td>
                    <td><small class="text-secondary">${p.received_by_name || 'System'}</small></td>
                    <td class="text-end text-nowrap">
                        <a href="/payments/${p.id}/receipt" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Download Receipt PDF">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> PDF
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-email-receipt" data-id="${p.id}" data-receipt="${p.receipt_no}" data-email="${p.client_email || p.student_email || ''}" title="Email Receipt">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Bind email buttons
        document.querySelectorAll('.btn-email-receipt').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const receipt = btn.getAttribute('data-receipt');
                const email = btn.getAttribute('data-email');

                document.getElementById('emailReceiptPmtId').value = id;
                document.getElementById('emailReceiptNumberBadge').textContent = receipt;
                document.getElementById('emailReceiptInput').value = email;

                const modal = new bootstrap.Modal(document.getElementById('emailReceiptModal'));
                modal.show();
            });
        });
    }

    // 2. Load Invoices
    async function loadInvoices(page = 1) {
        invPage = page;
        invoicesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading invoices...</td></tr>';

        const params = new URLSearchParams({
            page: invPage,
            per_page: 15,
            search: filterSearch.value.trim(),
            status: filterInvoiceStatus.value,
            date_from: filterDateFrom.value,
            date_to: filterDateTo.value
        });

        try {
            const res = await fetch(`/api/invoices?${params.toString()}`);
            const data = await res.json();

            if (data.status === 'success') {
                renderInvoices(data.data.items || []);
                renderPagination('invoices', data.data);
            } else {
                invoicesTableBody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">${data.message || 'Error loading invoices'}</td></tr>`;
            }
        } catch (e) {
            invoicesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Failed to connect to invoices API</td></tr>';
        }
    }

    function renderInvoices(items) {
        if (!items.length) {
            invoicesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No invoices found matching criteria.</td></tr>';
            return;
        }

        invoicesTableBody.innerHTML = items.map(inv => {
            const customer = inv.client_name 
                ? `<strong>${inv.client_name}</strong> <span class="badge bg-light text-dark border ms-1">${inv.client_code || ''}</span>`
                : (inv.student_name ? `<strong>${inv.student_name}</strong> <span class="badge bg-info-subtle text-info border ms-1">${inv.student_code || 'Student'}</span>` : 'Direct');

            return `
                <tr>
                    <td><strong class="text-dark">${inv.invoice_no}</strong></td>
                    <td>
                        <span class="fw-semibold">${inv.title}</span><br>
                        <small class="text-muted">Issued: ${inv.issue_date}</small>
                    </td>
                    <td>${customer}</td>
                    <td class="text-end fw-bold">${formatCurrency(inv.net_amount)}</td>
                    <td class="text-end text-success">${formatCurrency(inv.paid_amount)}</td>
                    <td class="text-end fw-bold ${parseFloat(inv.balance_amount) > 0 ? 'text-danger' : 'text-muted'}">${formatCurrency(inv.balance_amount)}</td>
                    <td class="text-nowrap">${inv.due_date}</td>
                    <td>${statusBadge(inv.status)}</td>
                    <td class="text-end text-nowrap">
                        ${parseFloat(inv.balance_amount) > 0 ? `
                        <button type="button" class="btn btn-sm btn-primary btn-quick-pay" data-id="${inv.id}" data-balance="${inv.balance_amount}" data-no="${inv.invoice_no}">
                            Pay Due
                        </button>
                        ` : '<span class="text-muted small">Cleared</span>'}
                    </td>
                </tr>
            `;
        }).join('');

        // Bind quick pay buttons
        document.querySelectorAll('.btn-quick-pay').forEach(btn => {
            btn.addEventListener('click', () => {
                const invId = btn.getAttribute('data-id');
                const balance = btn.getAttribute('data-balance');
                
                openRecordPaymentModalWithInvoice(invId, balance);
            });
        });
    }

    function renderPagination(type, data) {
        const info = document.getElementById(`${type}PageInfo`);
        const btns = document.getElementById(`${type}PageBtns`);
        if (!info || !btns) return;

        const total = data.total || 0;
        const page = data.page || 1;
        const totalPages = data.total_pages || 1;

        info.textContent = `Showing page ${page} of ${totalPages} (${total} total records)`;

        let html = '';
        if (page > 1) {
            html += `<button type="button" class="btn btn-outline-secondary" onclick="window.financeGoToPage('${type}', ${page - 1})">Prev</button>`;
        }
        if (page < totalPages) {
            html += `<button type="button" class="btn btn-outline-secondary" onclick="window.financeGoToPage('${type}', ${page + 1})">Next</button>`;
        }
        btns.innerHTML = html;
    }

    window.financeGoToPage = (type, page) => {
        if (type === 'payments') loadPayments(page);
        else loadInvoices(page);
    };

    // Filter controls
    btnApplyFilters?.addEventListener('click', () => {
        if (currentTab === 'payments') loadPayments(1);
        else loadInvoices(1);
    });

    btnResetFilters?.addEventListener('click', () => {
        filterSearch.value = '';
        filterPaymentMode.value = '';
        filterInvoiceStatus.value = '';
        filterDateFrom.value = '';
        filterDateTo.value = '';
        if (currentTab === 'payments') loadPayments(1);
        else loadInvoices(1);
    });

    // 3. Populate Lookups (Invoices, Clients, Students)
    async function populatePaymentInvoicesDropdown() {
        try {
            const res = await fetch('/api/invoices?per_page=100');
            const data = await res.json();
            if (data.status === 'success') {
                availableInvoices = data.data.items || [];
                const select = document.getElementById('pmtInvoiceSelect');
                if (!select) return;

                select.innerHTML = '<option value="">-- Choose an unpaid/partial invoice --</option>' +
                    availableInvoices.map(inv => {
                        const bal = parseFloat(inv.balance_amount || 0);
                        const label = `${inv.invoice_no} - ${inv.client_name || inv.student_name || 'Direct'} (${inv.title}) [Due: ₹${bal.toFixed(2)}]`;
                        return `<option value="${inv.id}" data-balance="${bal}">${label}</option>`;
                    }).join('');
            }
        } catch (e) {
            console.error('Failed to load invoices lookup', e);
        }
    }

    async function populateCustomersForNewInvoice() {
        try {
            // Clients
            const clientRes = await fetch('/api/clients?per_page=100');
            const clientData = await clientRes.json();
            if (clientData.status === 'success') {
                const cSelect = document.getElementById('invClientSelect');
                if (cSelect) {
                    cSelect.innerHTML = '<option value="">-- None / Student only --</option>' +
                        (clientData.data.clients || []).map(c => `<option value="${c.id}">${c.name} (${c.client_code})</option>`).join('');
                }
            }
        } catch (e) {
            console.error('Failed to load clients lookup', e);
        }

        try {
            // Students
            const studentRes = await fetch('/api/students?per_page=100');
            const studentData = await studentRes.json();
            if (studentData.status === 'success') {
                const sSelect = document.getElementById('invStudentSelect');
                if (sSelect) {
                    sSelect.innerHTML = '<option value="">-- None / Client only --</option>' +
                        (studentData.data || []).map(s => `<option value="${s.id}">${s.name} (${s.student_code})</option>`).join('');
                }
            }
        } catch (e) {
            // Students API might be empty or fallback
        }
    }

    // Auto-update amount when selecting invoice in Record Payment modal
    document.getElementById('pmtInvoiceSelect')?.addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        const bal = opt ? parseFloat(opt.getAttribute('data-balance') || 0) : 0;
        const pmtAmt = document.getElementById('pmtAmount');
        if (pmtAmt && bal > 0) {
            pmtAmt.value = bal.toFixed(2);
        }
        document.getElementById('invoiceDetailsPreview').textContent = bal > 0 ? `Current balance due: ₹${bal.toFixed(2)}` : '';
    });

    function openRecordPaymentModalWithInvoice(invoiceId, balance) {
        const modalEl = document.getElementById('recordPaymentModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const select = document.getElementById('pmtInvoiceSelect');
        if (select) {
            select.value = invoiceId;
        }
        const amt = document.getElementById('pmtAmount');
        if (amt && balance) {
            amt.value = parseFloat(balance).toFixed(2);
        }
        document.getElementById('invoiceDetailsPreview').textContent = `Current balance due: ₹${parseFloat(balance || 0).toFixed(2)}`;
    }

    // 4. Live Net Amount Calculator in New Invoice Modal
    const calculateNetPreview = () => {
        const total = parseFloat(document.getElementById('invTotalAmount')?.value || 0);
        const discount = parseFloat(document.getElementById('invDiscount')?.value || 0);
        const gstRate = parseFloat(document.getElementById('invGstRate')?.value || 0);

        const taxable = Math.max(0, total - discount);
        const gstAmount = Math.round((taxable * (gstRate / 100)) * 100) / 100;
        const net = Math.round((taxable + gstAmount) * 100) / 100;

        const previewEl = document.getElementById('invNetPreview');
        if (previewEl) {
            previewEl.value = net.toFixed(2);
        }
    };

    document.getElementById('invTotalAmount')?.addEventListener('input', calculateNetPreview);
    document.getElementById('invDiscount')?.addEventListener('input', calculateNetPreview);
    document.getElementById('invGstRate')?.addEventListener('change', calculateNetPreview);

    // 5. Dynamic Installment Plan Rows
    const chkEnableInstallments = document.getElementById('chkEnableInstallments');
    const installmentPlanContainer = document.getElementById('installmentPlanContainer');
    const installmentRows = document.getElementById('installmentRows');
    const btnAddInstallmentRow = document.getElementById('btnAddInstallmentRow');

    chkEnableInstallments?.addEventListener('change', (e) => {
        if (e.target.checked) {
            installmentPlanContainer.style.display = 'block';
            if (installmentRows.children.length === 0) {
                addInstallmentRow(1);
                addInstallmentRow(2);
            }
        } else {
            installmentPlanContainer.style.display = 'none';
        }
    });

    btnAddInstallmentRow?.addEventListener('click', () => {
        const nextIdx = installmentRows.children.length + 1;
        addInstallmentRow(nextIdx);
    });

    function addInstallmentRow(index) {
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 align-items-center installment-row';
        div.innerHTML = `
            <div class="col-4">
                <input type="date" class="form-control form-control-sm inst-date" required title="Due Date">
            </div>
            <div class="col-4">
                <input type="number" step="0.01" min="0" class="form-control form-control-sm inst-amount" placeholder="Amount (₹)" required>
            </div>
            <div class="col-3">
                <input type="text" class="form-control form-control-sm inst-notes" placeholder="e.g. Installment ${index}">
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.installment-row').remove()">×</button>
            </div>
        `;
        installmentRows.appendChild(div);
    }

    // 6. Submit Record Payment Form
    document.getElementById('recordPaymentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('btnSubmitPayment');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        const body = {
            csrf_token: getCsrfToken(),
            invoice_id: parseInt(document.getElementById('pmtInvoiceSelect').value),
            amount: parseFloat(document.getElementById('pmtAmount').value),
            payment_date: document.getElementById('pmtDate').value,
            payment_mode: document.getElementById('pmtMode').value,
            reference_no: document.getElementById('pmtRef').value.trim(),
            notes: document.getElementById('pmtNotes').value.trim()
        };

        try {
            const res = await fetch('/api/payments', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();

            if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal')).hide();
                document.getElementById('recordPaymentForm').reset();
                alert(`Payment recorded successfully! Receipt: ${data.data.receipt_no}`);
                loadPayments(1);
                populatePaymentInvoicesDropdown();
            } else {
                alert(data.message || 'Failed to record payment');
            }
        } catch (err) {
            alert('Network error while recording payment');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save & Generate Receipt';
        }
    });

    // 7. Submit New Invoice Form
    document.getElementById('newInvoiceForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('btnSubmitInvoice');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

        const clientIdVal = document.getElementById('invClientSelect').value;
        const studentIdVal = document.getElementById('invStudentSelect').value;

        const body = {
            csrf_token: getCsrfToken(),
            client_id: clientIdVal ? parseInt(clientIdVal) : null,
            student_id: studentIdVal ? parseInt(studentIdVal) : null,
            title: document.getElementById('invTitle').value.trim(),
            total_amount: parseFloat(document.getElementById('invTotalAmount').value),
            discount_amount: parseFloat(document.getElementById('invDiscount').value || 0),
            gst_rate_pct: parseFloat(document.getElementById('invGstRate').value || 0),
            issue_date: document.getElementById('invIssueDate').value,
            due_date: document.getElementById('invDueDate').value,
            notes: document.getElementById('invNotes').value.trim(),
            installments: []
        };

        if (chkEnableInstallments?.checked) {
            document.querySelectorAll('#installmentRows .installment-row').forEach(row => {
                const date = row.querySelector('.inst-date').value;
                const amt = parseFloat(row.querySelector('.inst-amount').value || 0);
                const notes = row.querySelector('.inst-notes').value.trim();
                if (amt > 0) {
                    body.installments.push({ due_date: date, amount: amt, notes: notes });
                }
            });
        }

        try {
            const res = await fetch('/api/invoices', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();

            if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('newInvoiceModal')).hide();
                document.getElementById('newInvoiceForm').reset();
                alert(`Invoice created successfully! Invoice No: ${data.data.invoice_no}`);
                if (currentTab === 'invoices') loadInvoices(1);
                populatePaymentInvoicesDropdown();
            } else {
                alert(data.message || 'Failed to create invoice');
            }
        } catch (err) {
            alert('Network error while creating invoice');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Invoice';
        }
    });

    // 8. Submit Email Receipt Form
    document.getElementById('emailReceiptForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('btnSendEmailReceipt');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

        const pmtId = document.getElementById('emailReceiptPmtId').value;
        const email = document.getElementById('emailReceiptInput').value.trim();

        try {
            const res = await fetch(`/api/payments/${pmtId}/email-receipt`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: getCsrfToken(), email: email })
            });
            const data = await res.json();

            if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('emailReceiptModal')).hide();
                alert('Receipt emailed successfully!');
            } else {
                alert(data.message || 'Failed to email receipt');
            }
        } catch (err) {
            alert('Error sending email');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Receipt';
        }
    });

    // Initialize
    loadPayments(1);
    populatePaymentInvoicesDropdown();
    populateCustomersForNewInvoice();
});
