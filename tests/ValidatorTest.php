<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Validator;
use PDO;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRulePasses(): void
    {
        $validator = Validator::make(
            ['name' => 'John Doe', 'items' => [1, 2]],
            ['name' => 'required', 'items' => 'required']
        );

        $this->assertTrue($validator->passes());
        $this->assertEmpty($validator->errors());
    }

    public function testRequiredRuleFailsOnNullEmptyOrEmptyArray(): void
    {
        $validator = Validator::make(
            ['name' => '', 'title' => null, 'items' => []],
            ['name' => 'required', 'title' => 'required', 'items' => 'required']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
        $this->assertArrayHasKey('title', $validator->errors());
        $this->assertArrayHasKey('items', $validator->errors());
    }

    public function testEmailRulePassesAndFails(): void
    {
        $valid = Validator::make(
            ['email' => 'contact@example.com'],
            ['email' => 'required|email']
        );
        $this->assertTrue($valid->passes());

        $invalid = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'required|email']
        );
        $this->assertTrue($invalid->fails());
        $this->assertStringContainsString('valid email', $invalid->errors()['email']);
    }

    public function testMinAndMaxRulesOnStrings(): void
    {
        $valid = Validator::make(
            ['code' => 'ABCDE'],
            ['code' => 'min:3|max:6']
        );
        $this->assertTrue($valid->passes());

        $tooShort = Validator::make(
            ['code' => 'AB'],
            ['code' => 'min:3']
        );
        $this->assertTrue($tooShort->fails());
        $this->assertStringContainsString('at least 3 characters', $tooShort->errors()['code']);

        $tooLong = Validator::make(
            ['code' => 'ABCDEFG'],
            ['code' => 'max:5']
        );
        $this->assertTrue($tooLong->fails());
        $this->assertStringContainsString('not exceed 5 characters', $tooLong->errors()['code']);
    }

    public function testMinAndMaxRulesOnNumbers(): void
    {
        $valid = Validator::make(
            ['age' => 25],
            ['age' => 'numeric|min:18|max:65']
        );
        $this->assertTrue($valid->passes());

        $tooSmall = Validator::make(
            ['age' => 16],
            ['age' => 'numeric|min:18']
        );
        $this->assertTrue($tooSmall->fails());

        $tooLarge = Validator::make(
            ['age' => 70],
            ['age' => 'numeric|max:65']
        );
        $this->assertTrue($tooLarge->fails());
    }

    public function testNumericRulePassesAndFails(): void
    {
        $valid = Validator::make(
            ['amount' => '1250.50'],
            ['amount' => 'numeric']
        );
        $this->assertTrue($valid->passes());

        $invalid = Validator::make(
            ['amount' => 'one-thousand'],
            ['amount' => 'numeric']
        );
        $this->assertTrue($invalid->fails());
        $this->assertStringContainsString('must be a number', $invalid->errors()['amount']);
    }

    public function testRegexRulePassesAndFails(): void
    {
        // Indian PAN format regex: 5 letters, 4 digits, 1 letter
        $panRule = 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';

        $valid = Validator::make(
            ['pan' => 'ABCDE1234F'],
            ['pan' => $panRule]
        );
        $this->assertTrue($valid->passes());

        $invalid = Validator::make(
            ['pan' => '12345ABCDE'],
            ['pan' => $panRule]
        );
        $this->assertTrue($invalid->fails());
        $this->assertStringContainsString('format is invalid', $invalid->errors()['pan']);
    }

    public function testInRulePassesAndFails(): void
    {
        $valid = Validator::make(
            ['status' => 'active'],
            ['status' => 'in:new,active,inactive']
        );
        $this->assertTrue($valid->passes());

        $invalid = Validator::make(
            ['status' => 'deleted'],
            ['status' => 'in:new,active,inactive']
        );
        $this->assertTrue($invalid->fails());
        $this->assertStringContainsString('selected Status is invalid', $invalid->errors()['status']);
    }

    public function testUniqueRuleWithPdo(): void
    {
        // Use an in-memory SQLite PDO to test unique query execution
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, deleted_at TEXT)");
        $pdo->exec("INSERT INTO users (id, email, deleted_at) VALUES (1, 'taken@example.com', NULL)");
        $pdo->exec("INSERT INTO users (id, email, deleted_at) VALUES (2, 'trashed@example.com', '2026-01-01 00:00:00')");

        // 1. Should fail on already existing active email
        $v1 = Validator::make(
            ['email' => 'taken@example.com'],
            ['email' => 'unique:users,email'],
            $pdo
        );
        $this->assertTrue($v1->fails());
        $this->assertStringContainsString('already been taken', $v1->errors()['email']);

        // 2. Should pass on available email
        $v2 = Validator::make(
            ['email' => 'fresh@example.com'],
            ['email' => 'unique:users,email'],
            $pdo
        );
        $this->assertTrue($v2->passes());

        // 3. Should pass when ignoring current user ID
        $v3 = Validator::make(
            ['email' => 'taken@example.com'],
            ['email' => 'unique:users,email,1'],
            $pdo
        );
        $this->assertTrue($v3->passes());

        // 4. Should pass if conflicting record is soft-deleted
        $v4 = Validator::make(
            ['email' => 'trashed@example.com'],
            ['email' => 'unique:users,email'],
            $pdo
        );
        $this->assertTrue($v4->passes());
    }

    public function testFileRuleValidation(): void
    {
        // Valid file simulation
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test pdf content');

        $validFile = [
            'name' => 'doc.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 1024, // 1 KB
        ];

        $valid = Validator::make(
            ['document' => $validFile],
            ['document' => 'file:pdf,png,2048']
        );
        $this->assertTrue($valid->passes());

        // File too large (max 1KB vs 5KB)
        $oversizedFile = [
            'name' => 'doc.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 5 * 1024,
        ];

        $oversized = Validator::make(
            ['document' => $oversizedFile],
            ['document' => 'file:pdf,1']
        );
        $this->assertTrue($oversized->fails());

        // Disallowed extension
        $badExtension = [
            'name' => 'virus.exe',
            'type' => 'application/x-msdownload',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];

        $disallowed = Validator::make(
            ['document' => $badExtension],
            ['document' => 'file:pdf,png']
        );
        $this->assertTrue($disallowed->fails());

        @unlink($tempFile);
    }

    public function testReturnsFieldWiseErrors(): void
    {
        $validator = Validator::make(
            ['email' => 'bad', 'age' => 12],
            ['email' => 'email', 'age' => 'min:18', 'name' => 'required']
        );

        $errors = $validator->errors();

        $this->assertCount(3, $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertIsString($validator->firstError());
    }
}
