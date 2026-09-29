<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '404 Not Found — CRM Portal') ?></title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { text-align: center; max-width: 480px; padding: 2rem; background: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        h1 { font-size: 3.5rem; margin: 0; color: #64748b; font-weight: 800; }
        p { color: #64748b; font-size: 1.05rem; line-height: 1.5; margin: 1rem 0 1.5rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>404</h1>
        <h2 class="h5 fw-bold text-dark mt-2">Resource Not Found</h2>
        <p><?= e($message ?? 'The requested page or record could not be found.') ?></p>
        <a href="/dashboard" class="btn btn-primary px-4">← Back to Dashboard</a>
    </div>
</body>
</html>
