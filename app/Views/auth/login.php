<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Sign In — CRM Portal') ?></title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <?php if (!empty($turnstile_enabled) && !empty($turnstile_site_key)): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .brand-header {
            background: #0284c7;
            padding: 2rem 1.5rem;
            text-align: center;
            color: #ffffff;
        }
        .brand-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .brand-header p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }
        .login-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-header">
            <h2>CRM Portal</h2>
            <p>Sign in to access your dashboard</p>
        </div>
        <div class="login-body">
            <div id="alertBox" class="alert d-none" role="alert"></div>

            <form id="loginForm" novalidate>
                <input type="hidden" name="_csrf_token" id="csrfToken" value="<?= e($csrf_token ?? '') ?>">

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required autocomplete="email">
                    <div class="invalid-feedback" id="emailError"></div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold mb-0">Password</label>
                        <a href="#" class="text-decoration-none small text-primary" data-bs-toggle="modal" data-bs-target="#forgotModal">Forgot?</a>
                    </div>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                    <div class="invalid-feedback" id="passwordError"></div>
                </div>

                <?php if (!empty($turnstile_enabled) && !empty($turnstile_site_key)): ?>
                <div class="mb-3 d-flex justify-content-center">
                    <div class="cf-turnstile" data-sitekey="<?= e($turnstile_site_key) ?>" data-theme="light"></div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="submitBtn">
                    <span id="btnText">Sign In</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </form>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotModal" tabindex="-1" aria-labelledby="forgotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="forgotModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="forgotAlert" class="alert d-none" role="alert"></div>
                    <p class="text-muted small">Enter your email address and we will send you a secure link to reset your password.</p>
                    <form id="forgotForm">
                        <div class="mb-3">
                            <label for="forgotEmail" class="form-label fw-semibold">Email address</label>
                            <input type="email" class="form-control" id="forgotEmail" required placeholder="name@company.com">
                            <div class="invalid-feedback" id="forgotEmailError"></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="forgotBtn">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        const loginForm = document.getElementById('loginForm');
        const alertBox = document.getElementById('alertBox');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        function showAlert(msg, type = 'danger') {
            alertBox.className = `alert alert-${type}`;
            alertBox.textContent = msg;
            alertBox.classList.remove('d-none');
        }

        function clearErrors() {
            alertBox.classList.add('d-none');
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();

            submitBtn.disabled = true;
            btnText.textContent = 'Authenticating...';
            btnSpinner.classList.remove('d-none');

            const payload = {
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                _csrf_token: document.getElementById('csrfToken').value
            };

            const turnstileInput = document.querySelector('[name="cf-turnstile-response"]');
            if (turnstileInput && turnstileInput.value) {
                payload['cf-turnstile-response'] = turnstileInput.value;
            }

            try {
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': payload._csrf_token
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (res.ok && data.status === 'success') {
                    showAlert('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.data.redirect || '/dashboard';
                    }, 600);
                } else if (res.status === 422 && data.errors) {
                    // Field validation errors
                    for (const [field, msg] of Object.entries(data.errors)) {
                        const input = document.getElementById(field);
                        const errDiv = document.getElementById(field + 'Error');
                        if (input && errDiv) {
                            input.classList.add('is-invalid');
                            errDiv.textContent = msg;
                        }
                    }
                } else {
                    showAlert(data.message || 'Authentication failed.');
                }
            } catch (err) {
                showAlert('Network or server error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Sign In';
                btnSpinner.classList.add('d-none');
                if (window.turnstile && typeof window.turnstile.reset === 'function') {
                    window.turnstile.reset();
                }
            }
        });

        // Forgot password flow
        const forgotForm = document.getElementById('forgotForm');
        const forgotAlert = document.getElementById('forgotAlert');
        const forgotBtn = document.getElementById('forgotBtn');

        forgotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            forgotAlert.classList.add('d-none');
            forgotBtn.disabled = true;

            const email = document.getElementById('forgotEmail').value.trim();
            const csrf = document.getElementById('csrfToken').value;

            try {
                const res = await fetch('/api/auth/forgot', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ email, _csrf_token: csrf })
                });

                const data = await res.json();
                forgotAlert.className = 'alert alert-info';
                forgotAlert.textContent = data.message || 'If that email address is registered, instructions have been sent.';
                forgotAlert.classList.remove('d-none');
                forgotForm.reset();
            } catch (err) {
                forgotAlert.className = 'alert alert-danger';
                forgotAlert.textContent = 'Failed to submit reset request. Please try again.';
                forgotAlert.classList.remove('d-none');
            } finally {
                forgotBtn.disabled = false;
            }
        });
    </script>

    <?= \App\Services\SentryService::renderBrowserScript() ?>
</body>
</html>
