<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Student;
use App\Models\StudentProgress;
use PDO;
use RuntimeException;

class TrainingInstituteService
{
    private Course $courseModel;
    private Batch $batchModel;
    private Enrollment $enrollmentModel;
    private Attendance $attendanceModel;
    private StudentProgress $progressModel;
    private Student $studentModel;
    private InvoiceService $invoiceService;
    private PDO $pdo;

    public function __construct(
        ?Course $courseModel = null,
        ?Batch $batchModel = null,
        ?Enrollment $enrollmentModel = null,
        ?Attendance $attendanceModel = null,
        ?StudentProgress $progressModel = null,
        ?Student $studentModel = null,
        ?InvoiceService $invoiceService = null,
        ?PDO $pdo = null
    ) {
        $this->courseModel = $courseModel ?? new Course();
        $this->batchModel = $batchModel ?? new Batch();
        $this->enrollmentModel = $enrollmentModel ?? new Enrollment();
        $this->attendanceModel = $attendanceModel ?? new Attendance();
        $this->progressModel = $progressModel ?? new StudentProgress();
        $this->studentModel = $studentModel ?? new Student();
        $this->invoiceService = $invoiceService ?? new InvoiceService();
        $this->pdo = $pdo ?? Database::getConnection();
    }

    // =========================================================================
    // 1. COURSES MANAGEMENT
    // =========================================================================

    /**
     * List courses with decoded syllabus modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCourses(bool $activeOnly = false): array
    {
        return $activeOnly ? $this->courseModel->getActive() : $this->courseModel->getAll();
    }

    /**
     * Get single course details.
     */
    public function getCourse(int $id): ?array
    {
        return $this->courseModel->find($id);
    }

    /**
     * Create a new course with syllabus modules.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createCourse(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException("Course name is required.", 422);
        }

        $code = strtoupper(trim($data['course_code'] ?? ''));
        if ($code === '') {
            $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 6)));
            $code = 'CRS-' . $slug . '-' . date('y');
        }

        $fee = (float)($data['fee'] ?? 0.00);
        $durationWeeks = (int)($data['duration_weeks'] ?? 4);
        $duration = trim((string)($data['duration'] ?? ($durationWeeks . ' Weeks')));
        $totalHours = (int)($data['total_hours'] ?? ($durationWeeks * 10));

        // Format syllabus modules
        $modules = $this->parseModulesList($data['modules'] ?? $data['syllabus_modules'] ?? []);

        $courseId = (int)$this->courseModel->create([
            'course_code' => $code,
            'name' => $name,
            'duration_weeks' => $durationWeeks,
            'duration' => $duration,
            'total_hours' => $totalHours,
            'fee' => $fee,
            'syllabus_summary' => $data['syllabus_summary'] ?? null,
            'syllabus_modules' => json_encode($modules, JSON_UNESCAPED_UNICODE),
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        return $this->courseModel->find($courseId);
    }

    /**
     * Update an existing course.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateCourse(int $id, array $data): array
    {
        $course = $this->courseModel->find($id);
        if (!$course) {
            throw new RuntimeException("Course not found.", 404);
        }

        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = trim($data['name']);
        }
        if (isset($data['fee'])) {
            $updates['fee'] = (float)$data['fee'];
        }
        if (isset($data['duration_weeks'])) {
            $updates['duration_weeks'] = (int)$data['duration_weeks'];
        }
        if (isset($data['duration'])) {
            $updates['duration'] = trim((string)$data['duration']);
        }
        if (isset($data['total_hours'])) {
            $updates['total_hours'] = (int)$data['total_hours'];
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (int)(bool)$data['is_active'];
        }
        if (isset($data['syllabus_summary'])) {
            $updates['syllabus_summary'] = $data['syllabus_summary'];
        }
        if (isset($data['modules']) || isset($data['syllabus_modules'])) {
            $modules = $this->parseModulesList($data['modules'] ?? $data['syllabus_modules'] ?? []);
            $updates['syllabus_modules'] = json_encode($modules, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($updates)) {
            $this->courseModel->update($id, $updates);
        }

        return $this->courseModel->find($id);
    }

    /**
     * Delete course (soft-delete).
     */
    public function deleteCourse(int $id): bool
    {
        return $this->courseModel->delete($id);
    }

    // =========================================================================
    // 2. BATCHES MANAGEMENT (WITH TRAINER SCOPING)
    // =========================================================================

    /**
     * List batches with optional trainer filter and status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBatches(?int $trainerId = null, ?string $status = null): array
    {
        return $this->batchModel->listBatches($trainerId, $status);
    }

    /**
     * Get single batch with trainer scoping validation.
     */
    public function getBatch(int $id, ?int $scopedTrainerId = null): ?array
    {
        $batch = $this->batchModel->findWithCourseAndTrainer($id);
        if (!$batch) {
            return null;
        }

        if ($scopedTrainerId !== null && (int)($batch['trainer_id'] ?? 0) !== $scopedTrainerId) {
            throw new RuntimeException("Forbidden: you can only view your own assigned batches.", 403);
        }

        return $batch;
    }

    /**
     * Create a new training batch.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createBatch(array $data): array
    {
        $courseId = (int)($data['course_id'] ?? 0);
        $course = $this->courseModel->find($courseId);
        if (!$course) {
            throw new RuntimeException("Valid course is required.", 422);
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException("Batch name is required.", 422);
        }

        $startDate = trim($data['start_date'] ?? '');
        if ($startDate === '') {
            throw new RuntimeException("Batch start date is required.", 422);
        }

        $year = (int)date('Y', strtotime($startDate));
        $code = trim($data['batch_code'] ?? '');
        if ($code === '') {
            $code = $this->batchModel->generateBatchCode($course['course_code'], $year);
        }

        $batchId = (int)$this->batchModel->create([
            'batch_code' => $code,
            'course_id' => $courseId,
            'trainer_id' => !empty($data['trainer_id']) ? (int)$data['trainer_id'] : null,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => !empty($data['end_date']) ? trim($data['end_date']) : null,
            'timing' => $data['timing'] ?? null,
            'days' => $data['days'] ?? null,
            'capacity' => !empty($data['capacity']) ? (int)$data['capacity'] : 30,
            'status' => in_array($data['status'] ?? '', ['upcoming', 'active', 'completed', 'cancelled'], true)
                ? $data['status']
                : 'upcoming',
        ]);

        return $this->batchModel->findWithCourseAndTrainer($batchId);
    }

    /**
     * Update an existing batch.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateBatch(int $id, array $data, ?int $scopedTrainerId = null): array
    {
        $batch = $this->getBatch($id, $scopedTrainerId);
        if (!$batch) {
            throw new RuntimeException("Batch not found.", 404);
        }

        $updates = [];
        foreach (['name', 'start_date', 'end_date', 'timing', 'days', 'status'] as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }
        if (isset($data['capacity'])) {
            $updates['capacity'] = (int)$data['capacity'];
        }
        if (array_key_exists('trainer_id', $data)) {
            $updates['trainer_id'] = !empty($data['trainer_id']) ? (int)$data['trainer_id'] : null;
        }

        if (!empty($updates)) {
            $this->batchModel->update($id, $updates);
        }

        return $this->batchModel->findWithCourseAndTrainer($id);
    }

    /**
     * Delete batch (soft-delete).
     */
    public function deleteBatch(int $id): bool
    {
        return $this->batchModel->delete($id);
    }

    /**
     * Get student roster for a batch with trainer scoping.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBatchRoster(int $batchId, ?int $scopedTrainerId = null): array
    {
        $batch = $this->getBatch($batchId, $scopedTrainerId);
        if (!$batch) {
            throw new RuntimeException("Batch not found.", 404);
        }

        return $this->enrollmentModel->listForBatch($batchId);
    }

    // =========================================================================
    // 3. ADMISSION & ENROLLMENT (STUDENT CREATION + PHASE 4 INVOICE LINKING)
    // =========================================================================

    /**
     * Admit a student (from lead, new registration, or existing student) into a course & batch.
     * Links fee plan to Phase 4 invoice and initializes module progress.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function admitStudent(array $data, ?int $creatorId = null): array
    {
        $courseId = (int)($data['course_id'] ?? 0);
        $course = $this->courseModel->find($courseId);
        if (!$course) {
            throw new RuntimeException("Valid course is required for admission.", 422);
        }

        $batchId = (int)($data['batch_id'] ?? 0);
        $batch = $this->batchModel->find($batchId);
        if (!$batch) {
            throw new RuntimeException("Valid batch is required for admission.", 422);
        }

        // Check batch capacity
        if ($this->batchModel->isCapacityReached($batchId)) {
            throw new RuntimeException("Batch capacity limit ({$batch['capacity']} seats) has been reached.", 422);
        }

        $year = (int)date('Y');
        $studentId = null;

        // 1. Resolve Student Entity
        if (!empty($data['student_id'])) {
            $student = $this->studentModel->find((int)$data['student_id']);
            if (!$student) {
                throw new RuntimeException("Specified student does not exist.", 404);
            }
            $studentId = (int)$student['id'];
        } elseif (!empty($data['lead_id'])) {
            // Convert or link from Lead
            $leadModel = new Lead();
            $lead = $leadModel->find((int)$data['lead_id']);
            if (!$lead) {
                throw new RuntimeException("Specified lead does not exist.", 404);
            }

            // Check if lead was already converted to a student
            if (!empty($lead['converted_student_id'])) {
                $studentId = (int)$lead['converted_student_id'];
            } else {
                // Find or create student
                $existing = $this->studentModel->findByMobile($lead['mobile']);
                if ($existing) {
                    $studentId = (int)$existing['id'];
                } else {
                    $code = $this->studentModel->generateStudentCode($year);
                    $studentId = (int)$this->studentModel->create([
                        'student_code' => $code,
                        'contact_id' => $lead['contact_id'] ?? null,
                        'name' => $lead['name'],
                        'email' => $lead['email'] ?? null,
                        'mobile' => $lead['mobile'],
                        'whatsapp_number' => $lead['whatsapp_number'] ?? $lead['mobile'],
                        'course_name' => $course['name'],
                        'status' => 'active',
                        'created_by' => $creatorId,
                    ]);
                }

                // Update lead to converted
                $leadModel->update((int)$lead['id'], [
                    'status' => 'converted',
                    'converted_student_id' => $studentId,
                ]);
            }
        } else {
            // New Student Data
            $name = trim($data['name'] ?? '');
            $mobile = trim($data['mobile'] ?? '');
            if ($name === '' || $mobile === '') {
                throw new RuntimeException("Student name and mobile are required for new registration.", 422);
            }

            $existing = $this->studentModel->findByMobile($mobile);
            if ($existing) {
                $studentId = (int)$existing['id'];
            } else {
                $code = $this->studentModel->generateStudentCode($year);
                $studentId = (int)$this->studentModel->create([
                    'student_code' => $code,
                    'name' => $name,
                    'email' => $data['email'] ?? null,
                    'mobile' => $mobile,
                    'whatsapp_number' => $data['whatsapp_number'] ?? $mobile,
                    'qualification' => $data['qualification'] ?? null,
                    'guardian_name' => $data['guardian_name'] ?? null,
                    'guardian_mobile' => $data['guardian_mobile'] ?? null,
                    'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'course_name' => $course['name'],
                    'status' => 'active',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $creatorId,
                ]);
            }
        }

        // 2. Agreed Fee
        $agreedFee = isset($data['agreed_fee']) ? (float)$data['agreed_fee'] : (float)$course['fee'];
        $admissionDate = !empty($data['admission_date']) ? trim($data['admission_date']) : date('Y-m-d');
        $enrollmentNo = $this->enrollmentModel->generateEnrollmentNo($year);

        // 3. Fee Plan Linking to Phase 4 Invoice
        $invoiceId = null;
        $shouldCreateInvoice = !empty($data['create_invoice']) || isset($data['installments']) || !empty($data['fee_plan']);

        if (!empty($data['invoice_id'])) {
            $invoiceId = (int)$data['invoice_id'];
        } elseif ($shouldCreateInvoice && $agreedFee > 0) {
            $discount = (float)($data['discount_amount'] ?? 0.00);
            $gstRate = (float)($data['gst_rate_pct'] ?? 0.00);
            $dueDate = !empty($data['due_date']) ? trim($data['due_date']) : $admissionDate;

            $invoicePayload = [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'title' => sprintf('Tuition Fee — %s (%s)', $course['name'], $batch['name']),
                'total_amount' => $agreedFee,
                'discount_amount' => $discount,
                'gst_rate_pct' => $gstRate,
                'due_date' => $dueDate,
                'installments' => $data['installments'] ?? null,
                'notes' => sprintf('Enrollment No: %s | Batch: %s', $enrollmentNo, $batch['batch_code']),
            ];

            $createdInvoice = $this->invoiceService->createInvoice($invoicePayload, $creatorId);
            $invoiceId = (int)$createdInvoice['id'];
        }

        // 4. Create Enrollment Record
        $enrollmentId = (int)$this->enrollmentModel->create([
            'enrollment_no' => $enrollmentNo,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'batch_id' => $batchId,
            'admission_date' => $admissionDate,
            'agreed_fee' => $agreedFee,
            'invoice_id' => $invoiceId,
            'status' => in_array($data['status'] ?? '', ['active', 'completed', 'dropped'], true)
                ? $data['status']
                : 'active',
            'created_by' => $creatorId,
        ]);

        // 5. Initialize Module Progress from Course Modules
        $modules = $course['modules'] ?? [];
        if (!empty($modules)) {
            $this->progressModel->initializeModules($enrollmentId, $studentId, $modules);
        }

        // 6. Update student status to active
        $this->studentModel->update($studentId, ['status' => 'active']);

        // Phase 9: Automated omnichannel admission confirmation message
        try {
            $msgService = new \App\Services\Messaging\MessageService();
            $msgService->onAdmissionConfirmed($enrollmentId);
        } catch (\Throwable $e) {
            \App\Core\Logger::error("Failed to dispatch automated admission confirmation for #{$enrollmentId}: " . $e->getMessage());
        }

        return $this->enrollmentModel->findWithDetails($enrollmentId);
    }

    /**
     * Update enrollment status (e.g. active, completed, dropped).
     */
    public function updateEnrollmentStatus(int $enrollmentId, string $status): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'completed', 'dropped'], true)) {
            throw new RuntimeException("Invalid enrollment status: {$status}", 422);
        }

        $enrollment = $this->enrollmentModel->find($enrollmentId);
        if (!$enrollment) {
            throw new RuntimeException("Enrollment not found.", 404);
        }

        $this->enrollmentModel->update($enrollmentId, ['status' => $status]);
        return $this->enrollmentModel->findWithDetails($enrollmentId);
    }

    // =========================================================================
    // 4. ATTENDANCE (TRAINER MARKING, BULK MARK, MONTHLY SHEET, ATTENDANCE %)
    // =========================================================================

    /**
     * Mark daily attendance for a batch with trainer scoping check.
     *
     * @param int $batchId
     * @param string $sessionDate YYYY-MM-DD
     * @param array<int, string|array{status: string, remarks?: string}> $records
     * @return array{batch_id: int, date: string, marked_count: int}
     */
    public function markAttendance(
        int $batchId,
        string $sessionDate,
        array $records,
        ?int $scopedTrainerId = null,
        ?int $markedBy = null
    ): array {
        // Enforce trainer scoping
        $batch = $this->getBatch($batchId, $scopedTrainerId);
        if (!$batch) {
            throw new RuntimeException("Batch not found.", 404);
        }

        if (empty($sessionDate)) {
            throw new RuntimeException("Session date is required.", 422);
        }

        $count = $this->attendanceModel->bulkMark($batchId, $sessionDate, $records, $markedBy);

        return [
            'batch_id' => $batchId,
            'batch_name' => $batch['name'],
            'session_date' => $sessionDate,
            'marked_count' => $count,
        ];
    }

    /**
     * Get attendance roster for a batch on a specific date.
     */
    public function getBatchAttendance(int $batchId, string $sessionDate, ?int $scopedTrainerId = null): array
    {
        $batch = $this->getBatch($batchId, $scopedTrainerId);
        if (!$batch) {
            throw new RuntimeException("Batch not found.", 404);
        }

        $students = $this->enrollmentModel->listForBatch($batchId);
        $marked = $this->attendanceModel->getBatchAttendanceForDate($batchId, $sessionDate);

        $roster = [];
        foreach ($students as $st) {
            $sId = (int)$st['student_id'];
            $att = $marked[$sId] ?? null;
            $roster[] = [
                'student_id' => $sId,
                'student_name' => $st['student_name'],
                'student_code' => $st['student_code'],
                'mobile' => $st['student_mobile'],
                'status' => $att['status'] ?? 'present',
                'remarks' => $att['remarks'] ?? null,
                'is_recorded' => $att !== null,
            ];
        }

        return [
            'batch' => $batch,
            'session_date' => $sessionDate,
            'roster' => $roster,
        ];
    }

    /**
     * Get monthly attendance sheet matrix.
     */
    public function getMonthlyAttendanceSheet(int $batchId, string $yearMonth, ?int $scopedTrainerId = null): array
    {
        $batch = $this->getBatch($batchId, $scopedTrainerId);
        if (!$batch) {
            throw new RuntimeException("Batch not found.", 404);
        }

        $sheet = $this->attendanceModel->getMonthlySheet($batchId, $yearMonth);
        $sheet['batch'] = $batch;
        return $sheet;
    }

    /**
     * Get student attendance stats.
     *
     * @return array{percentage: float, present: int, late: int, absent: int, excused: int, total: int}
     */
    public function getStudentAttendanceStats(int $studentId, ?int $batchId = null): array
    {
        return $this->attendanceModel->getStudentAttendanceSummary($studentId, $batchId);
    }

    // =========================================================================
    // 5. PROGRESS & CERTIFICATE READINESS
    // =========================================================================

    /**
     * Update progress of a specific module for an enrolled student.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateProgress(
        int $enrollmentId,
        string $moduleName,
        array $data,
        ?int $scopedTrainerId = null,
        ?int $updatedBy = null
    ): array {
        $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            throw new RuntimeException("Enrollment not found.", 404);
        }

        if ($scopedTrainerId !== null && (int)($enrollment['trainer_id'] ?? 0) !== $scopedTrainerId) {
            throw new RuntimeException("Forbidden: you can only update progress for your own batches.", 403);
        }

        $status = $data['status'] ?? 'completed';
        $remarks = $data['trainer_remarks'] ?? $data['remarks'] ?? null;
        $testScore = isset($data['test_score']) ? (float)$data['test_score'] : null;

        $this->progressModel->updateModule(
            $enrollmentId,
            $moduleName,
            $status,
            $remarks,
            $testScore,
            $updatedBy
        );

        $updatedEnrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        $allModules = $this->progressModel->getProgressForEnrollment($enrollmentId);

        return [
            'enrollment' => $updatedEnrollment,
            'certificate_ready' => (bool)($updatedEnrollment['certificate_ready'] ?? false),
            'modules' => $allModules,
        ];
    }

    /**
     * Get module progress for an enrollment with scoping check.
     */
    public function getEnrollmentProgress(int $enrollmentId, ?int $scopedTrainerId = null): array
    {
        $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            throw new RuntimeException("Enrollment not found.", 404);
        }

        if ($scopedTrainerId !== null && (int)($enrollment['trainer_id'] ?? 0) !== $scopedTrainerId) {
            throw new RuntimeException("Forbidden: you can only view progress for your own batches.", 403);
        }

        $modules = $this->progressModel->getProgressForEnrollment($enrollmentId);
        return [
            'enrollment' => $enrollment,
            'certificate_ready' => (bool)($enrollment['certificate_ready'] ?? false),
            'modules' => $modules,
        ];
    }

    /**
     * Issue certificate when student is certificate-ready.
     */
    public function issueCertificate(int $enrollmentId, string $certificateNo): array
    {
        $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            throw new RuntimeException("Enrollment not found.", 404);
        }

        if (empty($enrollment['certificate_ready'])) {
            throw new RuntimeException("Student is not yet certificate-ready. All modules must be completed first.", 422);
        }

        $this->enrollmentModel->update($enrollmentId, [
            'certificate_no' => trim($certificateNo),
            'certified_at' => date('Y-m-d'),
            'status' => 'completed',
        ]);

        return $this->enrollmentModel->findWithDetails($enrollmentId);
    }

    // =========================================================================
    // 6. STUDENT PROFILE COMPOSITE VIEW (SCOPED FINANCIALS)
    // =========================================================================

    /**
     * Build rich student profile: personal info, enrolled courses, batches,
     * attendance %, module progress, and scoped fees due.
     *
     * @return array<string, mixed>
     */
    public function getStudentProfile(
        int $studentId,
        ?int $scopedTrainerId = null,
        bool $canViewFinancials = true
    ): array {
        $student = $this->studentModel->find($studentId);
        if (!$student) {
            throw new RuntimeException("Student not found.", 404);
        }

        // Enrollments list
        $allEnrollments = $this->enrollmentModel->listForStudent($studentId);

        // If trainer is scoped, verify student is in at least one of their batches
        if ($scopedTrainerId !== null) {
            $isAssigned = false;
            foreach ($allEnrollments as $enr) {
                if ((int)($enr['trainer_id'] ?? 0) === $scopedTrainerId) {
                    $isAssigned = true;
                    break;
                }
            }
            if (!$isAssigned) {
                throw new RuntimeException("Forbidden: you can only view profiles of students in your assigned batches.", 403);
            }
        }

        // Overall attendance stats
        $attendanceStats = $this->attendanceModel->getStudentAttendanceSummary($studentId);

        // Process enrollments with individual attendance and progress
        $processedEnrollments = [];
        $totalFeesInvoiced = 0.0;
        $totalFeesPaid = 0.0;
        $totalFeesDue = 0.0;

        foreach ($allEnrollments as $enr) {
            $enrId = (int)$enr['id'];
            $batchId = (int)$enr['batch_id'];

            // Batch specific attendance
            $batchAtt = $this->attendanceModel->getStudentAttendanceSummary($studentId, $batchId);
            $enr['attendance_stats'] = $batchAtt;

            // Module progress
            $enr['progress'] = $this->progressModel->getProgressForEnrollment($enrId);

            if ($canViewFinancials) {
                $invBalance = (float)($enr['invoice_balance'] ?? 0.0);
                $invTotal = (float)($enr['invoice_total'] ?? 0.0);
                $invPaid = (float)($enr['invoice_paid'] ?? 0.0);

                $totalFeesInvoiced += $invTotal;
                $totalFeesPaid += $invPaid;
                $totalFeesDue += $invBalance;
            } else {
                // REDACT FINANCIAL DATA FOR TRAINER!
                unset(
                    $enr['invoice_total'],
                    $enr['invoice_paid'],
                    $enr['invoice_balance'],
                    $enr['agreed_fee']
                );
            }

            $processedEnrollments[] = $enr;
        }

        $profile = [
            'student' => $student,
            'attendance' => $attendanceStats,
            'enrollments' => $processedEnrollments,
            'financial_access' => $canViewFinancials,
        ];

        if ($canViewFinancials) {
            $profile['financials'] = [
                'total_invoiced' => round($totalFeesInvoiced, 2),
                'total_paid' => round($totalFeesPaid, 2),
                'fees_due' => round($totalFeesDue, 2),
            ];
        } else {
            $profile['financials'] = null;
        }

        return $profile;
    }

    /**
     * List all students with trainer scoping and search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listStudents(?int $scopedTrainerId = null, ?int $batchId = null, ?string $search = null): array
    {
        return $this->studentModel->listStudents($scopedTrainerId, $batchId, $search);
    }

    // =========================================================================
    // HELPER: MODULES PARSER
    // =========================================================================

    /**
     * Parse raw modules input (array, JSON string, or newline-delimited text).
     *
     * @param mixed $raw
     * @return string[]
     */
    private function parseModulesList(mixed $raw): array
    {
        if (is_array($raw)) {
            $result = [];
            foreach ($raw as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $result[] = trim($item);
                } elseif (is_array($item) && !empty($item['name'])) {
                    $result[] = trim((string)$item['name']);
                }
            }
            return array_values(array_unique($result));
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            // Check if JSON
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->parseModulesList($decoded);
                }
            }
            // Split by lines or commas
            $lines = preg_split('/[\r\n]+/', $trimmed) ?: [];
            $result = [];
            foreach ($lines as $line) {
                $l = trim($line, " \t\n\r\0\x0B-•*");
                if ($l !== '') {
                    $result[] = $l;
                }
            }
            return array_values(array_unique($result));
        }

        return [];
    }
}
