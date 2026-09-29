<?php

declare(strict_types=1);

/**
 * CRM Demo Data Seeder (Development / Testing Only)
 * CLI command: php database/seeds/demo.php
 */

$projectRoot = dirname(__DIR__, 2);

// Load Composer autoloader
$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

$cliEnv = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? null);

// Load environment variables
if (class_exists(\Dotenv\Dotenv::class) && file_exists($projectRoot . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

// 1. Strict guard: Dev-only, NEVER run in production
$appEnv = strtolower((string)($cliEnv ?: ($_ENV['APP_ENV'] ?? 'local')));
if ($appEnv === 'production') {
    fwrite(STDERR, "ERROR: Demo seeder cannot run in production environment!" . PHP_EOL);
    exit(1);
}

use App\Core\Database;
use App\Helpers\Crypto;
use App\Services\Cache\Cache;

echo "Running CRM demo data seeder (Environment: {$appEnv})..." . PHP_EOL;

try {
    $pdo = Database::getConnection();

    // Find admin user
    $userStmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $adminUserId = (int)($userStmt->fetchColumn() ?: 1);

    $now = time();
    $demoClients = [
        [
            'client_code' => 'DEMO-CL-0001',
            'client_type' => 'company',
            'name' => 'Alpha Global Technologies Pvt Ltd',
            'contact_person' => 'Rajesh Sharma',
            'email' => 'rajesh.sharma@alphatech.demo',
            'mobile' => '9820011221',
            'gst_no' => '27AAACA1234A1Z1',
            'pan_no' => 'AAACA1234A',
            'industry' => 'Information Technology',
            'company_size' => '51-200',
            'website' => 'https://alphatech.demo',
            'address_line1' => 'Plot 42, Bandra Kurla Complex',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400051',
            'lead_source' => 'Referral',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 months', $now)),
        ],
        [
            'client_code' => 'DEMO-CL-0002',
            'client_type' => 'company',
            'name' => 'Bharat Retail Ventures LLP',
            'contact_person' => 'Sunita Verma',
            'email' => 'sunita.v@bharatretail.demo',
            'mobile' => '9820022332',
            'gst_no' => '27BBBCB2345B1Z2',
            'pan_no' => 'BBBCB2345B',
            'industry' => 'Retail & E-Commerce',
            'company_size' => '11-50',
            'website' => 'https://bharatretail.demo',
            'address_line1' => 'FC Road, Shivajinagar',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411005',
            'lead_source' => 'Website',
            'status' => 'new',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month', $now)),
        ],
        [
            'client_code' => 'DEMO-CL-0003',
            'client_type' => 'individual',
            'name' => 'Greenleaf Organic Products',
            'contact_person' => 'Ananya Rao',
            'email' => 'ananya@greenleaf.demo',
            'mobile' => '9820033443',
            'gst_no' => '29CCCC93456C1Z3',
            'pan_no' => 'CCCC93456C',
            'industry' => 'Agriculture & Organic Goods',
            'company_size' => '1-10',
            'website' => 'https://greenleaf.demo',
            'address_line1' => 'Indiranagar 100ft Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lead_source' => 'LinkedIn',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 months', $now)),
        ],
        [
            'client_code' => 'DEMO-CL-0004',
            'client_type' => 'company',
            'name' => 'Zenith Freight & Logistics Ltd',
            'contact_person' => 'Vikram Malhotra',
            'email' => 'vikram.m@zenithfreight.demo',
            'mobile' => '9820044554',
            'gst_no' => '07DDDD04567D1Z4',
            'pan_no' => 'DDDD04567D',
            'industry' => 'Transportation & Logistics',
            'company_size' => '201-500',
            'website' => 'https://zenithfreight.demo',
            'address_line1' => 'Barakhamba Road, Connaught Place',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
            'lead_source' => 'Cold Call',
            'status' => 'new',
            'created_at' => date('Y-m-d H:i:s', $now), // this month
        ],
        [
            'client_code' => 'DEMO-CL-0005',
            'client_type' => 'individual',
            'name' => 'Sunrise Diagnostic & Healthcare',
            'contact_person' => 'Dr. Manoj Patel',
            'email' => 'dr.patel@sunrisecare.demo',
            'mobile' => '9820055665',
            'gst_no' => '24EEEE15678E1Z5',
            'pan_no' => 'EEEE15678E',
            'industry' => 'Healthcare & Pharmaceuticals',
            'company_size' => '11-50',
            'website' => 'https://sunrisecare.demo',
            'address_line1' => 'SG Highway, Bodakdev',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'pincode' => '380054',
            'lead_source' => 'Direct',
            'status' => 'inactive',
            'created_at' => date('Y-m-d H:i:s', strtotime('-4 months', $now)),
        ],
    ];

    $clientInsertStmt = $pdo->prepare(
        "INSERT INTO `clients` (
            `client_code`, `client_type`, `name`, `contact_person`, `email`,
            `mobile`, `gst_no`, `pan_no`, `industry`, `company_size`,
            `website`, `address_line1`, `city`, `state`, `pincode`,
            `country`, `lead_source`, `assigned_to`, `status`, `consent_given`,
            `consent_at`, `created_by`, `created_at`, `updated_at`
        ) VALUES (
            :client_code, :client_type, :name, :contact_person, :email,
            :mobile, :gst_no, :pan_no, :industry, :company_size,
            :website, :address_line1, :city, :state, :pincode,
            'India', :lead_source, :assigned_to, :status, 1,
            :consent_at, :created_by, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `deleted_at` = NULL, `updated_at` = NOW()"
    );

    $insertedIds = [];

    foreach ($demoClients as $client) {
        $encryptedPan = Crypto::encryptPan($client['pan_no']);
        $clientInsertStmt->execute([
            ':client_code' => $client['client_code'],
            ':client_type' => $client['client_type'],
            ':name' => $client['name'],
            ':contact_person' => $client['contact_person'],
            ':email' => $client['email'],
            ':mobile' => $client['mobile'],
            ':gst_no' => $client['gst_no'],
            ':pan_no' => $encryptedPan,
            ':industry' => $client['industry'],
            ':company_size' => $client['company_size'],
            ':website' => $client['website'],
            ':address_line1' => $client['address_line1'],
            ':city' => $client['city'],
            ':state' => $client['state'],
            ':pincode' => $client['pincode'],
            ':lead_source' => $client['lead_source'],
            ':assigned_to' => $adminUserId,
            ':status' => $client['status'],
            ':consent_at' => $client['created_at'],
            ':created_by' => $adminUserId,
            ':created_at' => $client['created_at'],
            ':updated_at' => $client['created_at'],
        ]);

        $id = (int)$pdo->lastInsertId();
        if ($id === 0) {
            $q = $pdo->prepare("SELECT id FROM clients WHERE client_code = ?");
            $q->execute([$client['client_code']]);
            $id = (int)$q->fetchColumn();
        }
        $insertedIds[$client['client_code']] = $id;
        echo "  - Added/updated client: {$client['name']} ({$client['client_code']})" . PHP_EOL;
    }

    // Add demo follow-ups
    if (!empty($insertedIds['DEMO-CL-0001'])) {
        $fuStmt = $pdo->prepare(
            "INSERT INTO `follow_ups` (`client_id`, `user_id`, `due_at`, `type`, `notes`, `status`, `created_at`, `updated_at`)
             VALUES (:client_id, :user_id, :due_at, :type, :notes, :status, NOW(), NOW())"
        );

        // 1. Follow-up for today
        $fuStmt->execute([
            ':client_id' => $insertedIds['DEMO-CL-0001'],
            ':user_id' => $adminUserId,
            ':due_at' => date('Y-m-d 15:00:00'),
            ':type' => 'call',
            ':notes' => 'Discuss enterprise contract renewal and pricing',
            ':status' => 'pending',
        ]);

        // 2. Overdue follow-up
        if (!empty($insertedIds['DEMO-CL-0002'])) {
            $fuStmt->execute([
                ':client_id' => $insertedIds['DEMO-CL-0002'],
                ':user_id' => $adminUserId,
                ':due_at' => date('Y-m-d 10:00:00', strtotime('-1 day')),
                ':type' => 'meeting',
                ':notes' => 'Follow up on partnership agreement draft',
                ':status' => 'pending',
            ]);
        }

        echo "  - Added demo follow-ups (today and overdue)." . PHP_EOL;
    }

    // Clear dashboard statistics cache
    Cache::forgetByPrefix('dashboard:stats');
    echo "  - Cleared dashboard stats cache." . PHP_EOL;

    echo "Demo data seeding completed successfully! (5 clients created)" . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, "Seeding failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
