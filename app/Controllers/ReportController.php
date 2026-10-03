<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PermissionService;
use App\Services\ReportService;
use Throwable;

class ReportController
{
    private ReportService $reportService;

    public function __construct(?ReportService $reportService = null)
    {
        $this->reportService = $reportService ?? new ReportService();
    }

    /**
     * Reports Overview & Interactive Viewer
     * GET /reports
     */
    public function index(): void
    {
        Session::start();
        $this->checkReportPermissions();

        $userRole = (string)Session::get('role', 'counselor');
        $lookups = $this->reportService->getFilterLookups();

        View::render('reports/index', [
            'pageTitle' => 'Reports & Performance Analytics',
            'currentPath' => '/reports',
            'userRole' => $userRole,
            'lookups' => $lookups,
            'canViewFinancial' => PermissionService::can('report.view_financial'),
            'canViewLeads' => PermissionService::can('report.view_leads'),
            'canViewAcademic' => PermissionService::can('report.view_academic'),
        ]);
    }

    /**
     * API: Fetch Report Data (Cached 10 min)
     * GET /api/reports/data
     */
    public function apiData(): void
    {
        Session::start();
        $this->checkReportPermissions();

        $type = (string)($_GET['type'] ?? 'sales_collections');
        $filters = [
            'start_date' => !empty($_GET['start_date']) ? (string)$_GET['start_date'] : null,
            'end_date' => !empty($_GET['end_date']) ? (string)$_GET['end_date'] : null,
            'staff_id' => !empty($_GET['staff_id']) ? (int)$_GET['staff_id'] : null,
            'lead_source' => !empty($_GET['lead_source']) ? (string)$_GET['lead_source'] : null,
            'course_id' => !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null,
            'service_id' => !empty($_GET['service_id']) ? (int)$_GET['service_id'] : null,
            'aging_bucket' => !empty($_GET['aging_bucket']) ? (string)$_GET['aging_bucket'] : null,
        ];

        try {
            $data = $this->reportService->getReport($type, array_filter($filters, fn($v) => $v !== null));
            Response::success($data);
        } catch (Throwable $e) {
            Response::error('Failed to load report: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Export Report to Excel (.xlsx) / CSV or Printable PDF
     * GET /api/reports/export
     */
    public function export(): void
    {
        Session::start();
        $this->checkReportPermissions();

        $type = (string)($_GET['type'] ?? 'sales_collections');
        $format = strtolower((string)($_GET['format'] ?? 'excel'));

        $filters = [
            'start_date' => !empty($_GET['start_date']) ? (string)$_GET['start_date'] : null,
            'end_date' => !empty($_GET['end_date']) ? (string)$_GET['end_date'] : null,
            'staff_id' => !empty($_GET['staff_id']) ? (int)$_GET['staff_id'] : null,
            'lead_source' => !empty($_GET['lead_source']) ? (string)$_GET['lead_source'] : null,
            'course_id' => !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null,
            'service_id' => !empty($_GET['service_id']) ? (int)$_GET['service_id'] : null,
            'aging_bucket' => !empty($_GET['aging_bucket']) ? (string)$_GET['aging_bucket'] : null,
        ];

        $reportData = $this->reportService->getReport($type, array_filter($filters, fn($v) => $v !== null));
        $rows = $reportData['rows'] ?? [];
        $title = $reportData['title'] ?? 'Report';
        $filename = 'report_' . $type . '_' . date('Ymd_His');

        if ($format === 'pdf') {
            $this->streamPrintablePdf($title, $rows);
            return;
        }

        if ($format === 'csv' || !class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->streamCsv($filename . '.csv', $rows);
            return;
        }

        // Export via PhpSpreadsheet
        $this->streamExcel($filename . '.xlsx', $title, $rows);
    }

    private function streamCsv(string $filename, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
        }
        fclose($out);
        exit;
    }

    private function streamExcel(string $filename, string $sheetTitle, array $rows): void
    {
        /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet */
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 30));

        if (!empty($rows)) {
            // Header Row
            $headers = array_keys($rows[0]);
            $colNum = 1;
            foreach ($headers as $h) {
                $sheet->setCellValue([$colNum, 1], ucwords(str_replace('_', ' ', $h)));
                $colNum++;
            }

            // Data Rows
            $rowNum = 2;
            foreach ($rows as $row) {
                $colNum = 1;
                foreach ($row as $val) {
                    $sheet->setCellValue([$colNum, $rowNum], $val);
                    $colNum++;
                }
                $rowNum++;
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function streamPrintablePdf(string $title, array $rows): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars($title) . "</title>
        <link rel='stylesheet' href='/assets/vendor/bootstrap/bootstrap.min.css'>
        <style>
            body { font-family: sans-serif; padding: 25px; color: #2d3748; }
            .header { border-bottom: 2px solid #0f2c59; padding-bottom: 12px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 13px; }
            th { background-color: #f7fafc; border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
            td { border: 1px solid #e2e8f0; padding: 8px; }
            @media print {
                .no-print { display: none; }
                body { padding: 0; }
            }
        </style>
        </head><body>
        <div class='no-print mb-3 text-end'>
            <button class='btn btn-primary btn-sm' onclick='window.print()'>Print / Save to PDF</button>
        </div>
        <div class='header'>
            <h2 style='color: #0f2c59; margin: 0;'>" . htmlspecialchars($title) . "</h2>
            <div style='color: #718096; font-size: 13px; margin-top: 5px;'>Generated on " . date('d M Y, h:i A') . " | Vyapar Care Portal</div>
        </div>";

        if (empty($rows)) {
            echo "<p class='text-muted'>No records found for this period.</p>";
        } else {
            echo "<table class='table table-bordered table-sm'><thead><tr>";
            foreach (array_keys($rows[0]) as $h) {
                echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $h))) . "</th>";
            }
            echo "</tr></thead><tbody>";
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $val) {
                    echo "<td>" . htmlspecialchars((string)($val ?? '')) . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        }

        echo "<script>window.onload = function() { setTimeout(function() { window.print(); }, 400); }</script></body></html>";
        exit;
    }

    private function checkReportPermissions(): void
    {
        $hasPerm = PermissionService::can('report.view_financial')
            || PermissionService::can('report.view_academic')
            || PermissionService::can('report.view_leads');

        if (!$hasPerm) {
            Response::error('Forbidden: insufficient permissions to view reports', 403);
            exit;
        }
    }
}
