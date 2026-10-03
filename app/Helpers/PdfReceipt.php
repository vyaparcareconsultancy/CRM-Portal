<?php

declare(strict_types=1);

namespace App\Helpers;

class PdfReceipt
{
    /**
     * Generate pure-PHP PDF 1.4 binary data for a payment receipt.
     */
    public static function generate(
        array $payment,
        array $invoice,
        array $settings,
        ?array $clientOrStudent = null
    ): string {
        $businessName = $settings['business_name'] ?? 'Vyapar Care Consultancy & Training Institute';
        $businessAddress = $settings['business_address'] ?? '101, Business Towers, Commercial Complex, Mumbai - 400001';
        $businessPhone = $settings['business_phone'] ?? '+91 98765 43210';
        $businessEmail = $settings['business_email'] ?? 'accounts@vyaparcare.com';
        $businessGstin = $settings['business_gstin'] ?? '27AABCU9603R1ZM';

        $receiptNo = $payment['receipt_no'] ?? 'REC-XXXX';
        $paymentDate = $payment['payment_date'] ?? date('Y-m-d');
        $paymentMode = strtoupper((string)($payment['payment_mode'] ?? 'CASH'));
        $refNo = $payment['reference_no'] ?? 'N/A';
        $receivedByName = $payment['received_by_name'] ?? 'Cashier';

        $customerName = $clientOrStudent['name'] ?? 'Valued Customer';
        $customerCode = $clientOrStudent['client_code'] ?? $clientOrStudent['student_code'] ?? 'N/A';
        $customerMobile = $clientOrStudent['mobile'] ?? 'N/A';
        $customerEmail = $clientOrStudent['email'] ?? 'N/A';

        $invoiceNo = $invoice['invoice_no'] ?? 'INV-XXXX';
        $invoiceTitle = $invoice['title'] ?? 'Consultancy & Compliance Services';
        $totalAmount = number_format((float)($invoice['total_amount'] ?? 0), 2, '.', ',');
        $gstAmount = number_format((float)($invoice['gst_amount'] ?? 0), 2, '.', ',');
        $netAmount = number_format((float)($invoice['net_amount'] ?? 0), 2, '.', ',');
        $paidNow = number_format((float)($payment['amount'] ?? 0), 2, '.', ',');
        $balanceDue = number_format((float)($invoice['balance_amount'] ?? 0), 2, '.', ',');

        $amountInWords = IndianNumberToWords::toIndianRupees((float)($payment['amount'] ?? 0));

        // PDF drawing commands (origin is bottom-left, A4: 595.28 x 841.89)
        $ops = [];

        // Outer border box
        $ops[] = "0.85 0.85 0.85 RG 1.5 w";
        $ops[] = "36 36 523 770 re S";

        // Header Background Bar (Navy Blue)
        $ops[] = "0.08 0.22 0.38 rg";
        $ops[] = "36 716 523 90 re f";

        // Business Title & Contact in Header
        $ops[] = "1 1 1 rg";
        $ops[] = "BT /F2 16 Tf 50 770 Td (" . self::escape($businessName) . ") Tj ET";
        $ops[] = "BT /F1 9 Tf 50 752 Td (" . self::escape($businessAddress) . ") Tj ET";
        $ops[] = "BT /F1 9 Tf 50 738 Td (Phone: " . self::escape($businessPhone) . " | Email: " . self::escape($businessEmail) . " | GSTIN: " . self::escape($businessGstin) . ") Tj ET";

        // Document Title Badge
        $ops[] = "0.95 0.96 0.98 rg 0.85 0.88 0.92 RG 1 w";
        $ops[] = "50 670 495 34 re B";
        $ops[] = "0.08 0.22 0.38 rg";
        $ops[] = "BT /F2 14 Tf 60 682 Td (OFFICIAL PAYMENT RECEIPT) Tj ET";
        $ops[] = "0.2 0.2 0.2 rg";
        $ops[] = "BT /F2 10 Tf 350 682 Td (ORIGINAL FOR PAYER) Tj ET";

        // Receipt Meta & Customer Info Box
        $ops[] = "0.98 0.98 0.98 rg 0.85 0.85 0.85 RG 0.5 w";
        $ops[] = "50 540 495 115 re B";

        $ops[] = "0.2 0.2 0.2 rg";
        // Left Column (Receipt Meta)
        $ops[] = "BT /F2 9 Tf 65 635 Td (Receipt No:) Tj ET";
        $ops[] = "BT /F2 10 Tf 140 635 Td (" . self::escape($receiptNo) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 65 615 Td (Payment Date:) Tj ET";
        $ops[] = "BT /F1 9 Tf 140 615 Td (" . self::escape($paymentDate) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 65 595 Td (Payment Mode:) Tj ET";
        $ops[] = "BT /F1 9 Tf 140 595 Td (" . self::escape($paymentMode) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 65 575 Td (Reference No:) Tj ET";
        $ops[] = "BT /F1 9 Tf 140 575 Td (" . self::escape((string)$refNo) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 65 555 Td (Invoice Ref:) Tj ET";
        $ops[] = "BT /F1 9 Tf 140 555 Td (" . self::escape($invoiceNo) . ") Tj ET";

        // Right Column (Customer Details)
        $ops[] = "BT /F2 9 Tf 310 635 Td (Received From:) Tj ET";
        $ops[] = "BT /F2 10 Tf 385 635 Td (" . self::escape($customerName) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 310 615 Td (ID / Code:) Tj ET";
        $ops[] = "BT /F1 9 Tf 385 615 Td (" . self::escape($customerCode) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 310 595 Td (Contact Phone:) Tj ET";
        $ops[] = "BT /F1 9 Tf 385 595 Td (" . self::escape($customerMobile) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 310 575 Td (Contact Email:) Tj ET";
        $ops[] = "BT /F1 9 Tf 385 575 Td (" . self::escape($customerEmail) . ") Tj ET";

        // Payment Particulars Table Header
        $ops[] = "0.08 0.22 0.38 rg";
        $ops[] = "50 495 495 24 re f";
        $ops[] = "1 1 1 rg";
        $ops[] = "BT /F2 9 Tf 60 503 Td (Particulars / Service Description) Tj ET";
        $ops[] = "BT /F2 9 Tf 340 503 Td (Invoice Total) Tj ET";
        $ops[] = "BT /F2 9 Tf 440 503 Td (Amount Paid) Tj ET";

        // Particulars Table Row
        $ops[] = "0.98 0.98 0.98 rg 0.85 0.85 0.85 RG 0.5 w";
        $ops[] = "50 425 495 70 re B";
        $ops[] = "0.1 0.1 0.1 rg";
        $ops[] = "BT /F2 10 Tf 60 475 Td (" . self::escape($invoiceTitle) . ") Tj ET";
        $ops[] = "BT /F1 8 Tf 60 460 Td (Invoice #" . self::escape($invoiceNo) . " | Net Amount: INR " . self::escape($netAmount) . ") Tj ET";
        $ops[] = "BT /F1 8 Tf 60 445 Td (Payment Method: " . self::escape($paymentMode) . " | Ref: " . self::escape((string)$refNo) . ") Tj ET";

        $ops[] = "BT /F1 9 Tf 340 475 Td (INR " . self::escape($netAmount) . ") Tj ET";
        $ops[] = "0.15 0.55 0.2 rg";
        $ops[] = "BT /F2 11 Tf 440 475 Td (INR " . self::escape($paidNow) . ") Tj ET";

        // Amount in Words Box
        $ops[] = "0.94 0.97 0.94 rg 0.7 0.85 0.7 RG 1 w";
        $ops[] = "50 365 495 45 re B";
        $ops[] = "0.1 0.4 0.15 rg";
        $ops[] = "BT /F2 9 Tf 60 395 Td (AMOUNT RECEIVED IN WORDS:) Tj ET";
        $ops[] = "0.1 0.1 0.1 rg";
        $ops[] = "BT /F2 10 Tf 60 377 Td (" . self::escape($amountInWords) . ") Tj ET";

        // Summary Breakdown Box
        $ops[] = "0.98 0.98 0.98 rg 0.85 0.85 0.85 RG 0.5 w";
        $ops[] = "320 270 225 80 re B";
        $ops[] = "0.2 0.2 0.2 rg";
        $ops[] = "BT /F1 9 Tf 330 332 Td (Total Invoice Value:) Tj ET";
        $ops[] = "BT /F1 9 Tf 460 332 Td (INR " . self::escape($netAmount) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 330 312 Td (This Payment:) Tj ET";
        $ops[] = "BT /F2 9 Tf 460 312 Td (INR " . self::escape($paidNow) . ") Tj ET";

        $ops[] = "BT /F2 9 Tf 330 288 Td (Balance Remaining Due:) Tj ET";
        $ops[] = "BT /F2 10 Tf 460 288 Td (INR " . self::escape($balanceDue) . ") Tj ET";

        // Notes and terms
        $ops[] = "0.4 0.4 0.4 rg";
        $ops[] = "BT /F1 8 Tf 50 250 Td (Terms & Notes:) Tj ET";
        $ops[] = "BT /F1 8 Tf 50 238 Td (1. Payments received are subject to realization of funds in case of Cheque/Bank Transfer.) Tj ET";
        $ops[] = "BT /F1 8 Tf 50 226 Td (2. This document serves as official acknowledgement of fees/charges received.) Tj ET";
        $ops[] = "BT /F1 8 Tf 50 214 Td (3. For queries regarding this receipt, quote the receipt number and contact accounts.) Tj ET";

        // Signatures Area
        $ops[] = "0.7 0.7 0.7 RG 1 w";
        $ops[] = "50 120 180 0.5 re S";
        $ops[] = "365 120 180 0.5 re S";

        $ops[] = "0.2 0.2 0.2 rg";
        $ops[] = "BT /F2 9 Tf 50 105 Td (Received By: " . self::escape($receivedByName) . ") Tj ET";
        $ops[] = "BT /F1 8 Tf 50 93 Td (Executive / Authorized Cashier) Tj ET";

        $ops[] = "BT /F2 9 Tf 365 105 Td (For " . self::escape($businessName) . ") Tj ET";
        $ops[] = "BT /F1 8 Tf 365 93 Td (Authorized Signatory) Tj ET";

        // Footer computer generated notice
        $ops[] = "0.5 0.5 0.5 rg";
        $ops[] = "BT /F1 7.5 Tf 170 50 Td (This is a computer-generated receipt and requires no physical seal if digitally authenticated.) Tj ET";

        $contentStream = implode("\n", $ops);
        $streamLength = strlen($contentStream);

        // Build standard PDF 1.4 catalog and objects
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>";
        $objects[4] = "<< /Length {$streamLength} >>\nstream\n{$contentStream}\nendstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $pdf = "%PDF-1.4\n";
        $xref = [];
        $xref[0] = "0000000000 65535 f \n";

        for ($i = 1; $i <= 6; $i++) {
            $xref[$i] = sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= "{$i} 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $startXref = strlen($pdf);
        $pdf .= "xref\n0 7\n";
        for ($i = 0; $i <= 6; $i++) {
            $pdf .= $xref[$i];
        }
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$startXref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        // Strip non-printable ASCII characters for basic PDF Helvetica font
        $clean = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
    }
}
