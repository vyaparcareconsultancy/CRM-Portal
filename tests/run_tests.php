<?php

declare(strict_types=1);

echo "========================================\n";
echo "Running All CRM Unit & Component Checks\n";
echo "========================================\n\n";

require __DIR__ . '/test_router.php';
echo "\n";
require __DIR__ . '/test_validator.php';
echo "\n";
require __DIR__ . '/test_auth.php';
echo "\n";
require __DIR__ . '/test_authz.php';
echo "\n";
require __DIR__ . '/test_client_creation.php';
echo "\n";
require __DIR__ . '/test_client_management.php';
echo "\n";
require __DIR__ . '/test_follow_ups.php';
echo "\n";
require __DIR__ . '/test_dashboard.php';
echo "\n";
require __DIR__ . '/test_security_audit.php';
echo "\n";
require __DIR__ . '/test_rate_limiter.php';
echo "\n";
require __DIR__ . '/test_cache.php';
echo "\n";
require __DIR__ . '/test_monitoring.php';

echo "\n========================================\n";
echo "ALL TESTS COMPLETED SUCCESSFULLY!\n";
echo "========================================\n";
