<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Logger;
use App\Core\Request;
use App\Controllers\LogController;
use App\Services\SentryService;
use PHPUnit\Framework\TestCase;

class MonitoringTest extends TestCase
{
    public function testRequestIdStability(): void
    {
        $id1 = Request::requestId();
        $this->assertStringStartsWith('req_', $id1);
        $this->assertSame($id1, Request::requestId());
    }

    public function testContextSanitization(): void
    {
        $input = [
            'password' => 'supersecret',
            'token' => 'bearer123',
            'pan_no' => 'ABCDE1234F',
            'safe_param' => 'hello',
        ];

        $sanitized = Logger::sanitizeContext($input);

        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['token']);
        $this->assertSame('[REDACTED]', $sanitized['pan_no']);
        $this->assertSame('hello', $sanitized['safe_param']);
    }

    public function testTailLogReader(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_tail_' . bin2hex(random_bytes(4)) . '.log';
        $content = implode("\n", array_map(fn($i) => "Line {$i}", range(1, 30)));
        file_put_contents($tempFile, $content);

        $tail = LogController::tail($tempFile, 5);
        $this->assertCount(5, $tail);
        $this->assertSame('Line 30', $tail[0]);
        $this->assertSame('Line 26', $tail[4]);

        @unlink($tempFile);
    }

    public function testSentryTextScrubber(): void
    {
        $scrubbed = SentryService::scrubText('User with PAN ABCDE1234F submitted form');
        $this->assertStringContainsString('[REDACTED_PAN]', $scrubbed);
        $this->assertStringNotContainsString('ABCDE1234F', $scrubbed);
    }
}
