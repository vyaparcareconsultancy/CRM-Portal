<!-- Reusable Profile Message Modal (Phase 9 Omnichannel) -->
<div class="modal fade" id="profileMessageModal" tabindex="-1" aria-labelledby="profileMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="profileMessageModalLabel">Send Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="profileMessageForm">
                <input type="hidden" id="pmEntityType" value="">
                <input type="hidden" id="pmEntityId" value="">
                <input type="hidden" id="pmContactEmail" value="">
                <input type="hidden" id="pmContactPhone" value="">
                <input type="hidden" id="pmContactName" value="">
                <div class="modal-body p-4">
                    <div id="pmAlertPlaceholder"></div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Channel</label>
                        <select class="form-select" id="pmChannel" onchange="pmChannelChanged()" required>
                            <option value="email">Email (PHPMailer SMTP)</option>
                            <option value="whatsapp">WhatsApp (Meta Cloud API)</option>
                            <option value="sms">SMS (MSG91 DLT Gateway)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Recipient</label>
                        <input type="text" class="form-control" id="pmRecipient" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Template</label>
                        <select class="form-select" id="pmTemplateId" onchange="pmTemplateChanged(this.value)">
                            <option value="">-- Custom Message (No Template) --</option>
                        </select>
                    </div>

                    <div class="mb-3" id="pmSubjectGroup">
                        <label class="form-label fw-semibold small">Subject</label>
                        <input type="text" class="form-control" id="pmSubject" placeholder="Email subject line">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Message Content</label>
                        <textarea class="form-control" id="pmBody" rows="4" placeholder="Enter message... Available: {name}, {course}, {amount}, {due_date}, {receipt_no}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="pmSubmitBtn">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let pmTemplatesList = [];

async function loadProfileMessageTemplates() {
    if (pmTemplatesList.length > 0) return;
    try {
        const res = await fetch('/api/message-templates');
        const json = await res.json();
        if (json.success && json.templates) {
            pmTemplatesList = json.templates;
            const select = document.getElementById('pmTemplateId');
            pmTemplatesList.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = `${t.name} (${t.channel.toUpperCase()})`;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Failed to load templates:', e);
    }
}

function openProfileMessageModal(entityType, entityId, contactName, email, phone) {
    document.getElementById('pmEntityType').value = entityType || '';
    document.getElementById('pmEntityId').value = entityId || '';
    document.getElementById('pmContactName').value = contactName || '';
    document.getElementById('pmContactEmail').value = email || '';
    document.getElementById('pmContactPhone').value = phone || '';

    // Set initial channel and recipient
    document.getElementById('pmChannel').value = email ? 'email' : (phone ? 'whatsapp' : 'email');
    pmChannelChanged();

    loadProfileMessageTemplates();
    const modal = new bootstrap.Modal(document.getElementById('profileMessageModal'));
    modal.show();
}

function pmChannelChanged() {
    const ch = document.getElementById('pmChannel').value;
    const email = document.getElementById('pmContactEmail').value;
    const phone = document.getElementById('pmContactPhone').value;

    const recipientInput = document.getElementById('pmRecipient');
    const subjGroup = document.getElementById('pmSubjectGroup');

    if (ch === 'email') {
        recipientInput.value = email;
        recipientInput.placeholder = 'client@example.com';
        subjGroup.style.display = 'block';
    } else {
        recipientInput.value = phone;
        recipientInput.placeholder = '9876543210';
        subjGroup.style.display = 'none';
    }
}

function pmTemplateChanged(templateId) {
    if (!templateId) return;
    const tpl = pmTemplatesList.find(t => String(t.id) === String(templateId));
    if (!tpl) return;

    const name = document.getElementById('pmContactName').value || 'Customer';
    let body = tpl.body_template.replace(/{name}/g, name).replace(/{{name}}/g, name);
    let subj = (tpl.subject || '').replace(/{name}/g, name).replace(/{{name}}/g, name);

    document.getElementById('pmBody').value = body;
    if (subj) {
        document.getElementById('pmSubject').value = subj;
    }
    if (tpl.channel !== 'all') {
        document.getElementById('pmChannel').value = tpl.channel;
        pmChannelChanged();
    }
}

document.getElementById('profileMessageForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('pmSubmitBtn');
    btn.disabled = true;
    btn.innerText = 'Sending...';

    const payload = {
        channel: document.getElementById('pmChannel').value,
        to: document.getElementById('pmRecipient').value,
        subject: document.getElementById('pmSubject').value,
        message: document.getElementById('pmBody').value,
        template_id: document.getElementById('pmTemplateId').value || null,
        entity_type: document.getElementById('pmEntityType').value || 'custom',
        entity_id: document.getElementById('pmEntityId').value || null
    };

    try {
        const res = await fetch('/api/messages/send', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert('Message dispatched successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('profileMessageModal'));
            if (modal) modal.hide();
            // Reload page or refresh timeline if refreshTimeline is available
            if (typeof loadTimelineData === 'function') {
                loadTimelineData();
            } else {
                location.reload();
            }
        } else {
            alert(data.error || 'Failed to dispatch message.');
        }
    } catch (err) {
        alert(err.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'Send Message';
    }
});
</script>
