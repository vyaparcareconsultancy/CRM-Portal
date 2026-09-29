<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Reset Password — CRM Portal') ?></title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .card-custom {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: #0284c7;
            padding: 2rem 1.5rem;
            text-align: center;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="card-custom">
        <div class="header">
            <h2 class="h4 mb-1 fw-bold">Set New Password</h2>
            <p class="small mb-0 opacity-75">Enter your new secure password below</p>
        </div>
        <div class="p-4">
            <div id="alertBox" class="alert d-none" role="alert"></div>

            <form id="resetForm" novalidate>
                <input type="hidden" id="csrfToken" value="<?= e($csrf_token ?? '') ?>">
                <input type="hidden" id="token" value="<?= e($token ?? '') ?>">

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email address</label>
                    <input type="email" class="form-control" id="email" value="<?= e($email ?? '') ?>" required readonly>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">New Password</label>
                    <input type="password" class="form-control" id="password" required minlength="8" placeholder="At least 8 characters">
                    <div class="invalid-feedback" id="passwordError"></div>
                </div>

                <div class="mb-4">
                    <label for="passwordConfirmation" class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" class="form-control" id="passwordConfirmation" required placeholder="Re-type new password">
                    <div class="invalid-feedback" id="confirmationError"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="submitBtn">
                    Reset Password
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="/login" class="text-decoration-none small">← Back to Sign In</a>
            </div>
        </div>
    </div>

    <script>
        const resetForm = document.getElementById('resetForm');
        const alertBox = document.getElementById('alertBox');
        const submitBtn = document.getElementById('submitBtn');

        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertBox.classList.add('d-none');
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('passwordConfirmation').value;

            if (password !== passwordConfirmation) {
                document.getElementById('passwordConfirmation').classList.add('is-invalid');
                document.getElementById('confirmationError').textContent = 'Passwords do not match.';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating password...';

            const payload = {
                token: document.getElementById('token').value,
                email: document.getElementById('email').value,
                password: password,
                password_confirmation: passwordConfirmation,
                _csrf_token: document.getElementById('csrfToken').value
            };

            try {
                const res = await fetch('/api/auth/reset', {
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
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = data.message || 'Password reset successfully! Redirecting to login...';
                    alertBox.classList.remove('d-none');
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 1500);
                } else {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message || 'Failed to reset password.';
                    alertBox.classList.remove('d-none');
                }
            } catch (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Network error. Please try again.';
                alertBox.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reset Password';
            }
        });
    </script>
</body>
</html>
