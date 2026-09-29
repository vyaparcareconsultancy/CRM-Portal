<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Logger;
use App\Core\Request;
use App\Controllers\LogController;
use App\Services\SentryService;

echo "--- Testing Logging, Monitoring & Observability ---\n";

// 1. Request ID format and stability
$reqId1 = Request::requestId();
assert(str_starts_with($reqId1, 'req_'), 'Request ID should start with req_');
$reqId2 = Request::requestId();
assert($reqId1 === $reqId2, 'Request ID should be stable across same request lifecycle');
echo "✔ Request ID generation & memoization passed\n";

// 2. Sensitive Context Sanitization
$rawContext = [
    'user_id' => 42,
    'password' => 'SecretPassword123!',
    'token' => 'jwt.token.string',
    'pan_no' => 'ABCDE1234F',
    'nested' => [
        'api_key' => 'live_sk_12345',
        'safe_value' => 'allowed_data',
    ],
];

$sanitized = Logger::sanitizeContext($rawContext);
assert($sanitized['password'] === '[REDACTED]', 'Password should be redacted');
assert($sanitized['token'] === '[REDACTED]', 'Token should be redacted');
assert($sanitized['pan_no'] === '[REDACTED]', 'PAN should be redacted');
assert($sanitized['nested']['api_key'] === '[REDACTED]', 'Nested API key should be redacted');
assert($sanitized['nested']['safe_value'] === 'allowed_data', 'Safe data must be preserved');
echo "✔ Sensitive context sanitization passed\n";

// 3. Logger Channels: app, error, security, audit
$date = date('Y-m-d');
$logsDir = dirname(__DIR__) . '/storage/logs';

Logger::app('Test application log message', ['meta' => 'test']);
Logger::error('Test error log message', ['error_code' => 500]);
Logger::security('Test security alert', ['failed_attempt' => true, 'password' => 'badpass']);
Logger::audit('Test audit event', ['action' => 'user_create', 'pan_no' => 'ABCDE1234F']);

// Check files exist
$appLogFile = "{$logsDir}/app-{$date}.log";
$errorLogFile = "{$logsDir}/error-{$date}.log";
$securityLogFile = "{$logsDir}/security-{$date}.log";
$auditLogFile = "{$logsDir}/audit-{$date}.log";

assert(file_exists($appLogFile) || file_exists("{$logsDir}/app.log"), 'App log file should exist');
assert(file_exists($errorLogFile) || file_exists("{$logsDir}/error.log"), 'Error log file should exist');
assert(file_exists($securityLogFile) || file_exists("{$logsDir}/security.log"), 'Security log file should exist');
assert(file_exists($auditLogFile) || file_exists("{$logsDir}/audit.log"), 'Audit log file should exist');

// Verify request_id in security log file
$secContent = file_get_contents($securityLogFile);
assert(str_contains($secContent, $reqId1) || str_contains($secContent, 'request_id'), 'Security log must contain request_id');
assert(!str_contains($secContent, 'badpass'), 'Security log must NOT leak raw password');
echo "✔ Multi-channel logging with request_id & rotation passed\n";

// 4. LogController::tail() backward reader
$tempFile = __DIR__ . '/temp_tail_' . bin2hex(random_bytes(4)) . '.txt';
$linesToWrite = [];
for ($i = 1; $i <= 50; $i++) {
    $linesToWrite[] = "Line {$i}: Sample log entry";
}
file_put_contents($tempFile, implode("\n", $linesToWrite));

$tailed = LogController::tail($tempFile, 10);
assert(count($tailed) === 10, 'tail should return exactly 10 lines');
assert($tailed[0] === 'Line 50: Sample log entry', 'tail should return newest lines first');
assert($tailed[9] === 'Line 41: Sample log entry', 'tail line 10 should be Line 41');
@unlink($tempFile);
echo "✔ LogController::tail() chunked reader passed\n";

// 5. SentryService Integration & Scrubbing
assert(SentryService::isEnabled() === false, 'Sentry should be disabled when SENTRY_DSN is empty');

$scrubbedText = SentryService::scrubText('Client PAN is ABCDE1234F with Bearer abcxyz123== token');
assert(str_contains($scrubbedText, '[REDACTED_PAN]'), 'Sentry text scrubber should redact PAN');
assert(!str_contains($scrubbedText, 'ABCDE1234F'), 'Raw PAN must not appear in scrubbed text');
assert(str_contains($scrubbedText, 'Bearer [REDACTED_TOKEN]'), 'Bearer token should be redacted');
echo "✔ SentryService scrubber passed\n";

echo "✔ All Logging, Monitoring & Observability tests completed successfully!\n";
