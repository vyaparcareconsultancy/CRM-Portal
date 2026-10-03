<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Crypto;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Enrollment;
use PDO;
use RuntimeException;

class DocumentService
{
    public const VALID_DOCUMENT_TYPES = [
        'pan',
        'aadhaar',
        'gst_certificate',
        'bank_statement_cheque',
        'itr',
        'photo',
        'other'
    ];

    private Document $docModel;
    private ActivityLog $activityLog;
    private string $storageBasePath;
    private PDO $pdo;

    public function __construct(
        ?Document $docModel = null,
        ?ActivityLog $activityLog = null,
        ?string $storageBasePath = null,
        ?PDO $pdo = null
    ) {
        $this->docModel = $docModel ?? new Document();
        $this->activityLog = $activityLog ?? new ActivityLog();
        $this->storageBasePath = $storageBasePath ?? (dirname(__DIR__, 2) . '/storage/uploads/documents');
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Upload and securely store a document for a lead, client, or student.
     * Encrypts sensitive files at rest and masks Aadhaar numbers.
     *
     * @param array<string, mixed> $meta
     * @param array{name: string, tmp_name: string, size?: int, type?: string, error?: int}|string $file
     * @return array<string, mixed>
     */
    public function uploadDocument(array $meta, array|string $file, int $userId): array
    {
        $entityType = strtolower(trim((string)($meta['entity_type'] ?? 'client')));
        if (!in_array($entityType, ['client', 'lead', 'student'], true)) {
            throw new RuntimeException("Invalid entity type: {$entityType}", 422);
        }

        $entityId = (int)($meta['entity_id'] ?? 0);
        if ($entityId <= 0) {
            throw new RuntimeException("Valid entity ID is required.", 422);
        }

        $docType = strtolower(trim((string)($meta['document_type'] ?? 'other')));
        if (!in_array($docType, self::VALID_DOCUMENT_TYPES, true)) {
            $docType = 'other';
        }

        // Process File Data
        $originalName = '';
        $mimeType = 'application/octet-stream';
        $fileBytes = '';

        if (is_array($file)) {
            if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException("File upload failed with error code " . $file['error'], 400);
            }
            $originalName = basename($file['name'] ?? 'document');
            $mimeType = $file['type'] ?? 'application/octet-stream';
            $tmpPath = $file['tmp_name'] ?? '';
            if (!file_exists($tmpPath)) {
                throw new RuntimeException("Uploaded temporary file not found.", 400);
            }
            $fileBytes = (string)file_get_contents($tmpPath);
        } else {
            // Raw binary content
            $originalName = trim((string)($meta['file_name'] ?? 'document.pdf'));
            $mimeType = (string)($meta['mime_type'] ?? 'application/pdf');
            $fileBytes = $file;
        }

        $sizeBytes = strlen($fileBytes);
        if ($sizeBytes === 0) {
            throw new RuntimeException("Uploaded file is empty.", 422);
        }

        // Aadhaar Masking Requirement:
        // Store ONLY masked number (last 4 digits) in DB; never store full number!
        $docNumber = $meta['document_number'] ?? $meta['aadhaar_no'] ?? null;
        if ($docType === 'aadhaar') {
            $docNumber = Crypto::maskAadhaar($docNumber);
        } elseif ($docType === 'pan' && $docNumber) {
            $docNumber = strtoupper(trim((string)$docNumber));
        }

        // Encrypt sensitive files at rest:
        // Aadhaar, PAN, Bank statement/cheque, ITR are always encrypted at rest.
        $isSensitive = in_array($docType, ['aadhaar', 'pan', 'bank_statement_cheque', 'itr'], true) || !empty($meta['is_encrypted']);
        $isEncrypted = $isSensitive ? 1 : 0;

        $bytesToSave = $isEncrypted ? Crypto::encryptFile($fileBytes) : $fileBytes;

        // Ensure storage directory outside web root
        $targetDir = sprintf('%s/%s/%d', $this->storageBasePath, $entityType, $entityId);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Failed to create secure storage directory.", 500);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = $ext !== '' ? '.' . $ext : '.bin';
        $storedName = bin2hex(random_bytes(16)) . $ext;
        $destPath = $targetDir . '/' . $storedName;

        if (file_put_contents($destPath, $bytesToSave) === false) {
            throw new RuntimeException("Failed to write document to secure storage.", 500);
        }

        // Prepare database record
        $record = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'client_id' => $entityType === 'client' ? $entityId : null,
            'lead_id' => $entityType === 'lead' ? $entityId : null,
            'student_id' => $entityType === 'student' ? $entityId : null,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'document_type' => $docType,
            'title' => $meta['title'] ?? ucfirst(str_replace('_', ' ', $docType)),
            'document_number' => $docNumber,
            'financial_year' => $meta['financial_year'] ?? null,
            'expiry_date' => !empty($meta['expiry_date']) ? $meta['expiry_date'] : null,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'is_encrypted' => $isEncrypted,
            'uploaded_by' => $userId,
        ];

        $docId = (int)$this->docModel->create($record);

        // Audit upload
        $this->activityLog->log($userId, 'document', $docId, 'document_uploaded', [
            'original_name' => $originalName,
            'document_type' => $docType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_encrypted' => $isEncrypted === 1,
            'document_number' => $docNumber,
        ]);

        return $this->docModel->findDocument($docId);
    }

    /**
     * Download or view a document with strict permission checks and audit logging.
     *
     * @return array{document: array<string, mixed>, content: string, mime_type: string, original_name: string, size_bytes: int}
     */
    public function downloadDocument(int|string $docId, int $userId, string $userRole): array
    {
        $doc = $this->docModel->findDocument((int)$docId);
        if (!$doc) {
            throw new RuntimeException("Document not found.", 404);
        }

        // Permission check 1: Aadhaar security
        // Requirement: Aadhaar files viewable only by Admin/Accountant!
        $isAadhaar = strtolower((string)$doc['document_type']) === 'aadhaar';
        if ($isAadhaar && !in_array($userRole, ['admin', 'accountant'], true)) {
            throw new RuntimeException("Forbidden: Aadhaar identification documents can only be viewed by Admin or Accountant.", 403);
        }

        // Permission check 2: Trainer scoping
        if ($userRole === 'trainer') {
            if ($doc['entity_type'] !== 'student' || empty($doc['student_id'])) {
                throw new RuntimeException("Forbidden: Trainers can only access student academic documents.", 403);
            }
            // Check student is in trainer's assigned batch
            $enrollmentModel = new Enrollment();
            $enrollments = $enrollmentModel->listForStudent((int)$doc['student_id']);
            $assigned = false;
            foreach ($enrollments as $enr) {
                if ((int)($enr['trainer_id'] ?? 0) === $userId) {
                    $assigned = true;
                    break;
                }
            }
            if (!$assigned) {
                throw new RuntimeException("Forbidden: you can only access documents for students in your assigned batches.", 403);
            }
        }

        // Locate file on disk
        $entityType = (string)$doc['entity_type'];
        $entityId = (int)$doc['entity_id'];
        $storedName = (string)$doc['stored_name'];

        $path = sprintf('%s/%s/%d/%s', $this->storageBasePath, $entityType, $entityId, $storedName);

        // Fallback for legacy client document paths: storage/uploads/clients/{clientId}/{storedName}
        if (!file_exists($path) && $entityType === 'client') {
            $legacyPath = dirname($this->storageBasePath) . '/clients/' . $entityId . '/' . $storedName;
            if (file_exists($legacyPath)) {
                $path = $legacyPath;
            }
        }

        if (!file_exists($path)) {
            throw new RuntimeException("Document file not found on storage server.", 404);
        }

        $rawBytes = (string)file_get_contents($path);

        // Decrypt if encrypted at rest
        $content = !empty($doc['is_encrypted']) ? Crypto::decryptFile($rawBytes) : $rawBytes;

        // Audit download / view
        $this->activityLog->log($userId, 'document', (int)$doc['id'], 'document_downloaded', [
            'original_name' => $doc['original_name'],
            'document_type' => $doc['document_type'],
            'entity_type' => $doc['entity_type'],
            'entity_id' => $doc['entity_id'],
        ]);

        return [
            'document' => $doc,
            'content' => $content,
            'mime_type' => (string)$doc['mime_type'],
            'original_name' => (string)$doc['original_name'],
            'size_bytes' => strlen($content),
        ];
    }

    /**
     * List documents for an entity with role-based filtering (Aadhaar download flag).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDocuments(string $entityType, int|string $entityId, int $userId, string $userRole): array
    {
        $documents = $this->docModel->listForEntity($entityType, (int)$entityId);

        $result = [];
        foreach ($documents as $doc) {
            $isAadhaar = strtolower((string)$doc['document_type']) === 'aadhaar';
            $canDownload = true;

            if ($isAadhaar && !in_array($userRole, ['admin', 'accountant'], true)) {
                $canDownload = false;
            }

            $doc['can_download'] = $canDownload;
            $result[] = $doc;
        }

        return $result;
    }

    /**
     * Soft delete document and log audit.
     */
    public function deleteDocument(int|string $docId, int $userId, string $userRole): bool
    {
        $doc = $this->docModel->findDocument((int)$docId);
        if (!$doc) {
            throw new RuntimeException("Document not found.", 404);
        }

        // Only Admin or Counselor/Accountant can delete
        if ($userRole === 'trainer') {
            throw new RuntimeException("Forbidden: Trainers cannot delete documents.", 403);
        }

        $success = $this->docModel->delete((int)$docId);
        if ($success) {
            $this->activityLog->log($userId, 'document', (int)$docId, 'document_deleted', [
                'original_name' => $doc['original_name'],
                'document_type' => $doc['document_type'],
                'entity_type' => $doc['entity_type'],
                'entity_id' => $doc['entity_id'],
            ]);
        }

        return $success;
    }
}
