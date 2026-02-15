<?php
require_once 'config/db.php';
requireLogin();
require_once 'fpdf.php';

// Sanitize inputs
$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May',     '06' => 'June',     '07' => 'July',  '08' => 'August',
    '09' => 'September','10' => 'October', '11' => 'November','12' => 'December'
];
$month = str_pad((int)($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
$year  = (int)($_GET['year'] ?? date('Y'));
if (!isset($months[$month])) $month = date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

// Queries
$user = getUser($pdo, $_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? ORDER BY date ASC");
$stmt->execute([$_SESSION['user_id'], $month, $year]);
$records = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) as days, COALESCE(SUM(hours_worked), 0) as total_hours FROM time_records WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$stmt->execute([$_SESSION['user_id'], $month, $year]);
$summary = $stmt->fetch();

// Helper: convert UTF-8 string to ISO-8859-1 for FPDF built-in fonts
function pdfStr(string $s): string {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
}

// Custom FPDF class
class DTRpdf extends FPDF
{
    public string $employeeName = '';
    public string $department   = '';
    public string $position     = '';
    public string $periodLabel  = '';

    function Header()
    {
        // Navy banner
        $this->SetFillColor(30, 58, 95);
        $this->Rect(0, 0, $this->GetPageWidth(), 28, 'F');

        // Title in gold
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(201, 162, 39);
        $this->SetXY(10, 6);
        $this->Cell(0, 10, 'DAILY TIME RECORD', 0, 1, 'C');

        // Period label in white
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 17);
        $this->Cell(0, 6, $this->periodLabel, 0, 1, 'C');

        // Employee info block
        $this->SetTextColor(30, 58, 95);
        $this->SetY(32);

        $this->SetX(10);
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Employee:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(70, 6, $this->employeeName, 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(25, 6, 'Position:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, $this->position, 0, 1);

        $this->SetX(10);
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Department:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, $this->department, 0, 1);

        // Gold divider
        $this->SetDrawColor(201, 162, 39);
        $this->SetLineWidth(0.5);
        $this->Line(10, 47, $this->GetPageWidth() - 10, 47);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(30, 58, 95);
        $this->SetY(50);
    }

    function Footer()
    {
        $this->SetY(-13);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Generated on ' . date('F d, Y \a\t h:i A') . '   |   Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Build PDF (Landscape A4)
$pdf = new DTRpdf('L', 'mm', 'A4');
$pdf->employeeName = pdfStr($user['full_name']);
$pdf->department   = pdfStr($user['department'] ?? '');
$pdf->position     = pdfStr($user['position']   ?? '');
$pdf->periodLabel  = $months[$month] . ' ' . $year;
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Column widths (mm). A4 landscape = 297mm; usable = 277mm (10mm margins each side)
$colW = [28, 22, 24, 24, 18, 24, 137];  // Date, Day, Time In, Time Out, Hours, Status, Journal

// Table header row
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$headers = ['Date', 'Day', 'Time In', 'Time Out', 'Hours', 'Status', 'Daily Journal'];
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// Data rows
$pdf->SetFont('Arial', '', 8);
$rowAlt = false;

foreach ($records as $record) {
    // Alternating row background
    $pdf->SetFillColor($rowAlt ? 240 : 255, $rowAlt ? 243 : 255, $rowAlt ? 249 : 255);
    $rowAlt = !$rowAlt;

    $dateLabel = date('M d, Y', strtotime($record['date']));
    $dayLabel  = date('D',      strtotime($record['date']));
    $timeIn    = $record['time_in']  ? date('h:i A', strtotime($record['time_in']))  : '--';
    $timeOut   = $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '--';
    $hours     = $record['hours_worked'] ? number_format($record['hours_worked'], 1) : '--';
    $status    = $record['status'] ?? 'present';
    $journal   = $record['remarks'] ?? '';
    $journalShort = mb_strlen($journal) > 85 ? mb_substr($journal, 0, 82) . '...' : $journal;

    $pdf->SetTextColor(30, 58, 95);
    $pdf->Cell($colW[0], 6, $dateLabel,    1, 0, 'L', true);
    $pdf->Cell($colW[1], 6, $dayLabel,     1, 0, 'C', true);
    $pdf->Cell($colW[2], 6, $timeIn,       1, 0, 'C', true);
    $pdf->Cell($colW[3], 6, $timeOut,      1, 0, 'C', true);
    $pdf->Cell($colW[4], 6, $hours,        1, 0, 'C', true);

    // Status with color
    if ($status === 'present') {
        $pdf->SetTextColor(16, 120, 90);   // green
    } elseif ($status === 'half-day') {
        $pdf->SetTextColor(170, 120, 10);  // gold/amber
    } else {
        $pdf->SetTextColor(180, 50, 50);   // red
    }
    $pdf->Cell($colW[5], 6, ucfirst($status), 1, 0, 'C', true);

    $pdf->SetTextColor(30, 58, 95);
    $pdf->Cell($colW[6], 6, pdfStr($journalShort), 1, 1, 'L', true);
}

// Monthly summary bar
$pdf->Ln(4);
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$summaryText = 'MONTHLY SUMMARY   |   Total Days: ' . $summary['days']
             . '   |   Total Hours Worked: ' . number_format($summary['total_hours'], 1);
$pdf->Cell(0, 10, $summaryText, 0, 1, 'C', true);

// Signature area
$pdf->Ln(10);
$pdf->SetTextColor(30, 58, 95);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(110, 6, 'Employee Signature: ___________________________', 0, 0);
$pdf->Cell(0,   6, 'Supervisor Signature: ___________________________', 0, 1);
$pdf->Ln(6);
$pdf->Cell(110, 6, 'Date Signed: ___________________________', 0, 0);
$pdf->Cell(0,   6, 'Date Signed: ___________________________', 0, 1);

// Output as download
$filename = 'DTR_' . $user['full_name'] . '_' . $months[$month] . '_' . $year . '.pdf';
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
$pdf->Output('I', $filename);
exit;
