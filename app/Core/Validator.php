<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private ?PDO $pdo;

    public function __construct(array $data, array $rules, ?PDO $pdo = null)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->pdo = $pdo;
    }

    public static function make(array $data, array $rules, ?PDO $pdo = null): self
    {
        $validator = new self($data, $rules, $pdo);
        $validator->validate();
        return $validator;
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;

            foreach ($ruleList as $ruleItem) {
                $ruleItem = trim((string)$ruleItem);
                if ($ruleItem === '') {
                    continue;
                }

                [$ruleName, $parameters] = $this->parseRule($ruleItem);

                // If not required and empty, skip remaining validations
                if ($ruleName !== 'required' && $this->isEmpty($value)) {
                    continue;
                }

                $passes = match ($ruleName) {
                    'required' => $this->validateRequired($value),
                    'email' => $this->validateEmail($value),
                    'min' => $this->validateMin($value, $parameters),
                    'max' => $this->validateMax($value, $parameters),
                    'numeric' => $this->validateNumeric($value),
                    'regex' => $this->validateRegex($value, $parameters),
                    'in' => $this->validateIn($value, $parameters),
                    'unique' => $this->validateUnique($field, $value, $parameters),
                    'file' => $this->validateFile($value, $parameters),
                    'password_policy' => $this->validatePasswordPolicy($value),
                    default => true,
                };

                if (!$passes) {
                    $this->errors[$field] = $this->formatErrorMessage($field, $ruleName, $parameters);
                    // Stop on first failure for this field
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }

        return reset($this->errors);
    }

    private function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [strtolower($rule), []];
        }

        [$name, $paramString] = explode(':', $rule, 2);
        $name = strtolower(trim($name));

        // For regex, the parameter may contain commas, so don't split by comma
        if ($name === 'regex') {
            return [$name, [$paramString]];
        }

        $params = array_map('trim', explode(',', $paramString));
        return [$name, $params];
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }
        if (is_array($value) && isset($value['error']) && $value['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }

        return false;
    }

    private function validateRequired(mixed $value): bool
    {
        return !$this->isEmpty($value);
    }

    private function validateEmail(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin(mixed $value, array $params): bool
    {
        $min = (float)($params[0] ?? 0);

        if (is_numeric($value)) {
            return (float)$value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= (int)$min;
        }

        if (is_array($value)) {
            return count($value) >= (int)$min;
        }

        return false;
    }

    private function validateMax(mixed $value, array $params): bool
    {
        $max = (float)($params[0] ?? 0);

        if (is_numeric($value)) {
            return (float)$value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= (int)$max;
        }

        if (is_array($value)) {
            return count($value) <= (int)$max;
        }

        return false;
    }

    private function validateNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    private function validateRegex(mixed $value, array $params): bool
    {
        $pattern = $params[0] ?? '';
        if ($pattern === '' || !is_string($value)) {
            return false;
        }

        return preg_match($pattern, $value) === 1;
    }

    private function validateIn(mixed $value, array $params): bool
    {
        return in_array((string)$value, $params, true);
    }

    private function validateUnique(string $field, mixed $value, array $params): bool
    {
        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;
        $ignoreId = $params[2] ?? null;

        if ($table === '' || $column === '') {
            return true;
        }

        try {
            $pdo = $this->pdo ?? Database::getConnection();

            // Check if column/table exists and handles soft-deletes if deleted_at column exists
            $safeTable = str_replace('`', '``', $table);
            $safeColumn = str_replace('`', '``', $column);

            $sql = "SELECT COUNT(*) FROM `{$safeTable}` WHERE `{$safeColumn}` = ?";
            $queryParams = [$value];

            if ($ignoreId !== null && $ignoreId !== '') {
                $sql .= " AND `id` != ?";
                $queryParams[] = $ignoreId;
            }

            // Attempt soft-delete aware check
            try {
                $softDeleteSql = $sql . " AND `deleted_at` IS NULL";
                $stmt = $pdo->prepare($softDeleteSql);
                $stmt->execute($queryParams);
                return ((int)$stmt->fetchColumn()) === 0;
            } catch (Throwable) {
                // Table might not have deleted_at column; fallback to base query
                $stmt = $pdo->prepare($sql);
                $stmt->execute($queryParams);
                return ((int)$stmt->fetchColumn()) === 0;
            }
        } catch (Throwable) {
            // If DB connection fails in non-db context, pass or fail gracefully
            return true;
        }
    }

    private function validateFile(mixed $value, array $params): bool
    {
        if (!is_array($value) || !isset($value['tmp_name'], $value['error'], $value['size'])) {
            return false;
        }

        if ($value['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Params can be: mimes..., maxKB (e.g. ['pdf', 'png', '2048'])
        $maxKb = null;
        $allowedMimesOrExts = [];

        foreach ($params as $param) {
            if (is_numeric($param)) {
                $maxKb = (int)$param;
            } else {
                $allowedMimesOrExts[] = strtolower($param);
            }
        }

        // Validate max size in KB
        if ($maxKb !== null) {
            $sizeInKb = (int)ceil($value['size'] / 1024);
            if ($sizeInKb > $maxKb) {
                return false;
            }
        }

        // Validate allowed extensions/mimes
        if (!empty($allowedMimesOrExts)) {
            $originalName = (string)($value['name'] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, (string)$value['tmp_name']) : '';

            $matched = in_array($extension, $allowedMimesOrExts, true);
            if (!$matched && $mimeType) {
                foreach ($allowedMimesOrExts as $allowed) {
                    if (str_contains($mimeType, $allowed)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function validatePasswordPolicy(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Min 8 chars
        if (mb_strlen($value) < 8) {
            return false;
        }

        // Letters + numbers
        if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
            return false;
        }

        // Common weak passwords blacklist
        $commonPasswords = [
            'password', 'password123', 'password1', '12345678', '123456789', '1234567890',
            'admin123', 'admin@123', 'crmadmin', 'qwerty123', 'welcome123', 'letmein123',
            'pass1234', '12345678a', '12345678A', 'iloveyou', 'sunshine', 'princess',
            'football', 'monkey123', 'dragon123', 'master123', 'access123', 'shadow123',
            'superman', 'trustno1', 'secret123', 'testing123', 'techtians123', 'root1234',
        ];

        if (in_array(strtolower($value), $commonPasswords, true)) {
            return false;
        }

        return true;
    }

    private function formatErrorMessage(string $field, string $rule, array $params): string
    {
        $label = ucfirst(str_replace(['_', '-'], ' ', $field));

        return match ($rule) {
            'required' => "The {$label} field is required.",
            'email' => "The {$label} must be a valid email address.",
            'min' => "The {$label} must be at least " . ($params[0] ?? '') . (is_numeric($this->data[$field] ?? null) ? '.' : ' characters.'),
            'max' => "The {$label} must not exceed " . ($params[0] ?? '') . (is_numeric($this->data[$field] ?? null) ? '.' : ' characters.'),
            'numeric' => "The {$label} must be a number.",
            'regex' => "The {$label} format is invalid.",
            'in' => "The selected {$label} is invalid.",
            'unique' => "The {$label} has already been taken.",
            'file' => "The {$label} must be a valid file" . (!empty($params) ? " (" . implode(', ', $params) . ")" : '') . ".",
            'password_policy' => "The {$label} must be at least 8 characters long, contain both letters and numbers, and not be a common weak password.",
            default => "The {$label} is invalid.",
        };
    }
}
