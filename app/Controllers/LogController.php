<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PermissionService;
use Throwable;

class LogController
{
    /**
     * Admin Log Viewer UI (GET /admin/logs).
     */
    public function index(): void
    {
        Session::start();
        $this->authorizeAdmin();

        $today = date('Y-m-d');
        $errorLines = $this->getLogLines('error', $today, 200);
        $securityLines = $this->getLogLines('security', $today, 200);

        Response::view('admin/logs', [
            'title' => 'System Logs — CRM Admin',
            'pageHeading' => 'System Logs',
            'currentPath' => '/admin/logs',
            'today' => $today,
            'initialErrors' => $errorLines,
            'initialSecurity' => $securityLines,
        ]);
    }

    /**
     * API for live log retrieval (GET /api/admin/logs).
     */
    public function api(): void
    {
        try {
            Session::start();
            $this->authorizeAdmin();

            $channel = strtolower((string)($_GET['channel'] ?? 'error'));
            if (!in_array($channel, ['error', 'security', 'app', 'audit'], true)) {
                $channel = 'error';
            }

            $date = (string)($_GET['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            $limit = max(1, min((int)($_GET['limit'] ?? 200), 500));
            $lines = $this->getLogLines($channel, $date, $limit);

            Response::success([
                'channel' => $channel,
                'date' => $date,
                'count' => count($lines),
                'lines' => $lines,
            ]);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Parse the last N lines from the selected log file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getLogLines(string $channel, string $date, int $limit = 200): array
    {
        $logsDir = dirname(__DIR__, 2) . '/storage/logs';
        $pattern = "{$logsDir}/{$channel}-{$date}.log";

        // Also check un-dated fallback filename if date is today
        $filesToTry = [$pattern];
        if ($date === date('Y-m-d')) {
            $filesToTry[] = "{$logsDir}/{$channel}.log";
        }

        $rawLines = [];
        foreach ($filesToTry as $file) {
            if (file_exists($file)) {
                $rawLines = self::tail($file, $limit);
                break;
            }
        }

        $parsed = [];
        foreach ($rawLines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $extra = $decoded['extra'] ?? [];
                $parsed[] = [
                    'datetime' => $decoded['datetime'] ?? 'N/A',
                    'level' => strtoupper((string)($decoded['level_name'] ?? $decoded['level'] ?? 'INFO')),
                    'channel' => (string)($decoded['channel'] ?? $channel),
                    'message' => (string)($decoded['message'] ?? ''),
                    'request_id' => (string)($decoded['request_id'] ?? $extra['request_id'] ?? '-'),
                    'user_id' => $extra['user_id'] ?? null,
                    'ip' => (string)($extra['ip'] ?? '-'),
                    'context' => $decoded['context'] ?? [],
                ];
            } else {
                $parsed[] = [
                    'datetime' => $date,
                    'level' => 'INFO',
                    'channel' => $channel,
                    'message' => $line,
                    'request_id' => '-',
                    'user_id' => null,
                    'ip' => '-',
                    'context' => [],
                ];
            }
        }

        return $parsed;
    }

    /**
     * Efficiently read the last N lines of a file using fseek chunking.
     *
     * @return array<int, string>
     */
    public static function tail(string $filePath, int $maxLines = 200): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return [];
        }

        $lines = [];
        $buffer = '';
        $chunkSize = 4096;

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        $pos = $fileSize;

        while ($pos > 0 && count($lines) < $maxLines) {
            $seekSize = min($chunkSize, $pos);
            $pos -= $seekSize;
            fseek($handle, $pos);

            $chunk = (string)fread($handle, $seekSize);
            $buffer = $chunk . $buffer;

            $split = explode("\n", $buffer);
            $buffer = array_shift($split) ?? '';

            while (!empty($split) && count($lines) < $maxLines) {
                $line = trim((string)array_pop($split));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        if ($buffer !== '' && count($lines) < $maxLines) {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $lines[] = $trimmed;
            }
        }

        fclose($handle);
        return $lines;
    }

    private function authorizeAdmin(): void
    {
        $role = PermissionService::getRole();
        $canManage = PermissionService::can('user.manage');

        if ($role !== 'admin' && !$canManage) {
            throw new \RuntimeException('Access denied. Administrator privileges required.', 403);
        }
    }
}
