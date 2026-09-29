<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Models\Client;
use PDO;
use PHPUnit\Framework\TestCase;

class ClientCodeGeneratorTest extends TestCase
{
    private PDO $pdo;
    private Client $clientModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_code TEXT UNIQUE,
                name TEXT,
                deleted_at TEXT NULL
            );
        ");

        Database::setConnection($this->pdo);
        $this->clientModel = new Client();
    }

    public function testInitialCodeForYearIsZeroZeroZeroOne(): void
    {
        $code = $this->clientModel->generateClientCode(2026);
        $this->assertSame('CL-2026-0001', $code);
    }

    public function testSequentialIncrementation(): void
    {
        $this->pdo->exec("INSERT INTO clients (client_code, name) VALUES ('CL-2026-0001', 'Client 1')");
        $code2 = $this->clientModel->generateClientCode(2026);
        $this->assertSame('CL-2026-0002', $code2);

        $this->pdo->exec("INSERT INTO clients (client_code, name) VALUES ('CL-2026-0002', 'Client 2')");
        $code3 = $this->clientModel->generateClientCode(2026);
        $this->assertSame('CL-2026-0003', $code3);
    }

    public function testYearChangeResetsSequence(): void
    {
        $this->pdo->exec("INSERT INTO clients (client_code, name) VALUES ('CL-2025-0145', 'Client 2025')");
        $code2026 = $this->clientModel->generateClientCode(2026);
        $this->assertSame('CL-2026-0001', $code2026);
    }

    public function testZeroPaddingFormatting(): void
    {
        $this->pdo->exec("INSERT INTO clients (client_code, name) VALUES ('CL-2026-0009', 'Client 9')");
        $code10 = $this->clientModel->generateClientCode(2026);
        $this->assertSame('CL-2026-0010', $code10);

        $this->pdo->exec("INSERT INTO clients (client_code, name) VALUES ('CL-2026-0099', 'Client 99')");
        $code100 = $this->clientModel->generateClientCode(2026);
        $this->assertSame('CL-2026-0100', $code100);
    }
}
