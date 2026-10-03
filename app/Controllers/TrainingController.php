<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Services\PermissionService;
use App\Services\TrainingInstituteService;
use App\Services\UserService;
use Throwable;

class TrainingController
{
    private TrainingInstituteService $trainingService;

    public function __construct(?TrainingInstituteService $trainingService = null)
    {
        $this->trainingService = $trainingService ?? new TrainingInstituteService();
    }

    /**
     * Resolve scoped trainer ID if current user has Trainer role.
     */
    private function resolveScopedTrainerId(): ?int
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $role = PermissionService::getRole($userId);
        return $role === 'trainer' ? $userId : null;
    }

    // =========================================================================
    // VIEWS
    // =========================================================================

    /**
     * Courses Catalog View.
     */
    public function coursesIndex(): void
    {
        $courses = $this->trainingService->listCourses(false);
        Response::view('training/courses', [
            'title' => 'Courses Catalog — Training Academy',
            'pageHeading' => 'Courses & Certifications',
            'courses' => $courses,
            'canManage' => PermissionService::can('course.manage'),
        ]);
    }

    /**
     * Batches Management View.
     */
    public function batchesIndex(): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $batches = $this->trainingService->listBatches($scopedTrainerId);
        $courses = $this->trainingService->listCourses(true);

        $userService = new UserService();
        $trainers = array_filter(
            $userService->listUsers(),
            static fn($u) => strtolower($u['role_name'] ?? '') === 'trainer' && (int)$u['is_active'] === 1
        );

        Response::view('training/batches', [
            'title' => 'Batches & Scheduling — Training Academy',
            'pageHeading' => $scopedTrainerId ? 'My Assigned Batches' : 'Batches & Schedules',
            'batches' => $batches,
            'courses' => $courses,
            'trainers' => array_values($trainers),
            'isTrainer' => $scopedTrainerId !== null,
            'canManage' => PermissionService::can('batch.manage'),
        ]);
    }

    /**
     * Attendance Marking View.
     */
    public function attendanceIndex(): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $batches = $this->trainingService->listBatches($scopedTrainerId, 'active');
        if (empty($batches)) {
            $batches = $this->trainingService->listBatches($scopedTrainerId);
        }

        Response::view('training/attendance', [
            'title' => 'Attendance Tracker — Training Academy',
            'pageHeading' => 'Daily Attendance & Roll Call',
            'batches' => $batches,
            'isTrainer' => $scopedTrainerId !== null,
            'canManage' => PermissionService::can('attendance.manage'),
        ]);
    }

    /**
     * Students Directory View.
     */
    public function studentsIndex(): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $batches = $this->trainingService->listBatches($scopedTrainerId);

        Response::view('training/students', [
            'title' => 'Students Directory — Training Academy',
            'pageHeading' => 'Students & Admissions',
            'batches' => $batches,
            'isTrainer' => $scopedTrainerId !== null,
            'canManage' => PermissionService::can('student.manage'),
        ]);
    }

    /**
     * Student Profile View.
     */
    public function studentProfile(string|int $id): void
    {
        $studentId = (int)$id;
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $canViewFinancials = PermissionService::can('payment.view') && $scopedTrainerId === null;

        try {
            $profile = $this->trainingService->getStudentProfile($studentId, $scopedTrainerId, $canViewFinancials);
        } catch (Throwable $e) {
            Response::abort($e->getCode() === 403 ? 403 : 404, $e->getMessage());
            return;
        }

        Response::view('training/student_profile', [
            'title' => 'Student Profile — ' . ($profile['student']['name'] ?? 'Details'),
            'pageHeading' => 'Student Profile',
            'profile' => $profile,
            'isTrainer' => $scopedTrainerId !== null,
            'canManage' => PermissionService::can('student.manage'),
            'canMarkAttendance' => PermissionService::can('attendance.manage'),
            'canUpdateProgress' => PermissionService::can('progress.manage'),
        ]);
    }

    // =========================================================================
    // COURSES API
    // =========================================================================

    public function apiCoursesList(): void
    {
        $courses = $this->trainingService->listCourses(false);
        Response::success($courses);
    }

    public function apiCourseStore(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        try {
            $course = $this->trainingService->createCourse($input);
            Response::success($course, 'Course created successfully.', 201);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    public function apiCourseUpdate(string|int $id): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        try {
            $course = $this->trainingService->updateCourse((int)$id, $input);
            Response::success($course, 'Course updated successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    public function apiCourseDelete(string|int $id): void
    {
        try {
            $this->trainingService->deleteCourse((int)$id);
            Response::success(null, 'Course deleted successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    // =========================================================================
    // BATCHES API (WITH TRAINER SCOPING)
    // =========================================================================

    public function apiBatchesList(): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $status = $_GET['status'] ?? null;
        $batches = $this->trainingService->listBatches($scopedTrainerId, $status);
        Response::success($batches);
    }

    public function apiBatchShow(string|int $id): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        try {
            $batch = $this->trainingService->getBatch((int)$id, $scopedTrainerId);
            if (!$batch) {
                Response::error('Batch not found.', 404);
                return;
            }
            Response::success($batch);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    public function apiBatchStore(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        try {
            $batch = $this->trainingService->createBatch($input);
            Response::success($batch, 'Batch created successfully.', 201);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    public function apiBatchUpdate(string|int $id): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $scopedTrainerId = $this->resolveScopedTrainerId();
        try {
            $batch = $this->trainingService->updateBatch((int)$id, $input, $scopedTrainerId);
            Response::success($batch, 'Batch updated successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    public function apiBatchDelete(string|int $id): void
    {
        try {
            $this->trainingService->deleteBatch((int)$id);
            Response::success(null, 'Batch deleted successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    public function apiBatchRoster(string|int $id): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        try {
            $roster = $this->trainingService->getBatchRoster((int)$id, $scopedTrainerId);
            Response::success($roster);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    // =========================================================================
    // ADMISSION & ENROLLMENT API (LINKS TO PHASE 4 INVOICE)
    // =========================================================================

    public function apiAdmit(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        try {
            $enrollment = $this->trainingService->admitStudent($input, $userId);
            Response::success($enrollment, 'Student admitted and enrolled successfully.', 201);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    public function apiUpdateEnrollmentStatus(string|int $id): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $status = $input['status'] ?? '';

        try {
            $enrollment = $this->trainingService->updateEnrollmentStatus((int)$id, $status);
            Response::success($enrollment, 'Enrollment status updated.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    // =========================================================================
    // ATTENDANCE API
    // =========================================================================

    public function apiGetBatchAttendance(string|int $batchId): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $date = $_GET['date'] ?? date('Y-m-d');

        try {
            $data = $this->trainingService->getBatchAttendance((int)$batchId, $date, $scopedTrainerId);
            Response::success($data);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    public function apiMarkAttendance(string|int $batchId): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $date = $input['session_date'] ?? date('Y-m-d');
        $records = $input['attendance'] ?? $input['records'] ?? [];

        try {
            $result = $this->trainingService->markAttendance(
                (int)$batchId,
                $date,
                $records,
                $scopedTrainerId,
                $userId
            );
            Response::success($result, 'Attendance recorded successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    public function apiMonthlySheet(string|int $batchId): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $month = $_GET['month'] ?? date('Y-m');

        try {
            $sheet = $this->trainingService->getMonthlyAttendanceSheet((int)$batchId, $month, $scopedTrainerId);
            Response::success($sheet);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    // =========================================================================
    // PROGRESS & CERTIFICATE API
    // =========================================================================

    public function apiGetProgress(string|int $enrollmentId): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        try {
            $data = $this->trainingService->getEnrollmentProgress((int)$enrollmentId, $scopedTrainerId);
            Response::success($data);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    public function apiUpdateProgress(string|int $enrollmentId): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $moduleName = trim((string)($input['module_name'] ?? ''));
        if ($moduleName === '') {
            Response::error('Module name is required.', 422);
            return;
        }

        try {
            $result = $this->trainingService->updateProgress(
                (int)$enrollmentId,
                $moduleName,
                $input,
                $scopedTrainerId,
                $userId
            );
            Response::success($result, 'Module progress saved successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 400);
        }
    }

    public function apiIssueCertificate(string|int $enrollmentId): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $certNo = trim((string)($input['certificate_no'] ?? ('CERT-' . date('Y') . '-' . sprintf('%04d', (int)$enrollmentId))));

        try {
            $enrollment = $this->trainingService->issueCertificate((int)$enrollmentId, $certNo);
            Response::success($enrollment, 'Certificate issued successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    // =========================================================================
    // STUDENTS DIRECTORY & PROFILE API
    // =========================================================================

    public function apiStudentsList(): void
    {
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $batchId = !empty($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
        $search = $_GET['search'] ?? null;

        $students = $this->trainingService->listStudents($scopedTrainerId, $batchId, $search);
        Response::success($students);
    }

    public function apiStudentProfile(string|int $id): void
    {
        $studentId = (int)$id;
        $scopedTrainerId = $this->resolveScopedTrainerId();
        $canViewFinancials = PermissionService::can('payment.view') && $scopedTrainerId === null;

        try {
            $profile = $this->trainingService->getStudentProfile($studentId, $scopedTrainerId, $canViewFinancials);
            Response::success($profile);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), $e->getCode() === 403 ? 403 : 404);
        }
    }
}
