<?php

declare(strict_types=1);

// Standalone runner for environments without PHPUnit installed
require_once dirname(__DIR__) . '/app/Core/Validator.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

use App\Core\Validator;

function assertCheck(bool $condition, string $description): void
{
    if (!$condition) {
        echo "FAIL: {$description}\n";
        exit(1);
    }
    echo "PASS: {$description}\n";
}

echo "Running Validator Tests (Standalone)...\n";

// 1. Required
$v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
assertCheck($v->passes(), 'Required passes on non-empty string');

$v = Validator::make(['name' => ''], ['name' => 'required']);
assertCheck($v->fails() && isset($v->errors()['name']), 'Required fails on empty string');

// 2. Email
$v = Validator::make(['email' => 'valid@crm.local'], ['email' => 'email']);
assertCheck($v->passes(), 'Email passes on valid email');

$v = Validator::make(['email' => 'invalid-mail'], ['email' => 'email']);
assertCheck($v->fails() && isset($v->errors()['email']), 'Email fails on invalid format');

// 3. Min / Max
$v = Validator::make(['pass' => '12345678'], ['pass' => 'min:8|max:16']);
assertCheck($v->passes(), 'Min/Max passes within boundaries');

$v = Validator::make(['pass' => '123'], ['pass' => 'min:6']);
assertCheck($v->fails(), 'Min fails when below threshold');

// 4. Numeric
$v = Validator::make(['age' => '42'], ['age' => 'numeric']);
assertCheck($v->passes(), 'Numeric passes on numeric string');

$v = Validator::make(['age' => 'abc'], ['age' => 'numeric']);
assertCheck($v->fails(), 'Numeric fails on letters');

// 5. In
$v = Validator::make(['type' => 'company'], ['type' => 'in:individual,company']);
assertCheck($v->passes(), 'In passes on valid enum value');

$v = Validator::make(['type' => 'alien'], ['type' => 'in:individual,company']);
assertCheck($v->fails(), 'In fails on unexpected enum value');

// 6. Regex
$v = Validator::make(['code' => 'ABC-123'], ['code' => 'regex:/^[A-Z]{3}-[0-9]{3}$/']);
assertCheck($v->passes(), 'Regex passes on matching pattern');

// 7. Field-wise errors
$v = Validator::make(['a' => '', 'b' => 'not-num'], ['a' => 'required', 'b' => 'numeric']);
assertCheck(count($v->errors()) === 2 && isset($v->errors()['a'], $v->errors()['b']), 'Returns field-wise errors array');

echo "\nAll validator standalone checks passed successfully!\n";
