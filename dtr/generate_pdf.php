<?php
require_once 'config/db.php';
requireLogin();
require_once 'fpdf.php';

// Sanitize inputs
$months = [
    '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
    '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
    '09'=>'September','10'=>'October','11'=>'November','12'=>'December'
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

$stmt = $pdo->prepare("SELECT COUNT(*) as days, COALESCE(SUM(hours_worked),0) as total_hours, COALESCE(SUM(under_time),0) as total_under FROM time_records WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$stmt->execute([$_SESSION['user_id'], $month, $year]);
$summary = $stmt->fetch();

// Helper: convert UTF-8 to ISO-8859-1 for FPDF built-in fonts
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
        $this->Rect(0, 0, $this->GetPageWidth(), 26, 'F');

        // Title in gold
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(201, 162, 39);
        $this->SetXY(10, 5);
        $this->Cell(0, 9, 'DAILY TIME RECORD', 0, 1, 'C');

        // Period label in white
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 15);
        $this->Cell(0, 6, $this->periodLabel, 0, 1, 'C');

        // Employee info block
        $this->SetTextColor(30, 58, 95);
        $this->SetY(30);

        $this->SetX(10);
        $this->SetFont('Arial', '', 8);
        $this->Cell(25, 5, 'Employee:', 0, 0);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(75, 5, $this->employeeName, 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(20, 5, 'Position:', 0, 0);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 5, $this->position, 0, 1);

        $this->SetX(10);
        $this->SetFont('Arial', '', 8);
        $this->Cell(25, 5, 'Department:', 0, 0);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 5, $this->department, 0, 1);

        // Gold divider
        $this->SetDrawColor(201, 162, 39);
        $this->SetLineWidth(0.5);
        $this->Line(10, 43, $this->GetPageWidth() - 10, 43);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(30, 58, 95);
        $this->SetY(46);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 6);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 8, 'Generated on ' . date('F d, Y \a\t h:i A') . '   |   Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Build PDF — Portrait A4 (210mm wide, usable 190mm with 10mm margins)
$pdf = new DTRpdf('P', 'mm', 'A4');
$pdf->employeeName = pdfStr($user['full_name']);
$pdf->department   = pdfStr($user['department'] ?? '');
$pdf->position     = pdfStr($user['position']   ?? '');
$pdf->periodLabel  = $months[$month] . ' ' . $year;
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// Column widths (mm) — total = 190mm
// Date(28) + Day(16) + AM In(21) + AM Out(21) + PM In(21) + PM Out(21) + Hours(18) + Under(22) + Status(22) = 190
$colW = [28, 16, 21, 21, 21, 21, 18, 22, 22];
$headers = ['Date', 'Day', 'AM In', 'AM Out', 'PM In', 'PM Out', 'Hours', 'Under Time', 'Status'];

// Table header row
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7);
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 6, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// Data rows
$pdf->SetFont('Arial', '', 7);
$rowAlt = false;

foreach ($records as $record) {
    $pdf->SetFillColor($rowAlt ? 240 : 255, $rowAlt ? 243 : 255, $rowAlt ? 249 : 255);
    $rowAlt = !$rowAlt;

    $dateLabel = date('M d, Y', strtotime($record['date']));
    $dayLabel  = date('D',      strtotime($record['date']));
    $amIn  = !empty($record['time_in'])     ? date('h:i A', strtotime($record['time_in']))     : '--';
    $amOut = !empty($record['am_time_out']) ? date('h:i A', strtotime($record['am_time_out'])) : '--';
    $pmIn  = !empty($record['pm_time_in'])  ? date('h:i A', strtotime($record['pm_time_in']))  : '--';
    $pmOut = !empty($record['time_out'])    ? date('h:i A', strtotime($record['time_out']))    : '--';
    $hours      = $record['hours_worked'] !== null ? number_format($record['hours_worked'], 1) : '--';
    $underTime  = ($record['under_time'] > 0)      ? number_format($record['under_time'], 1)   : '--';
    $status     = $record['status'] ?? 'present';

    $pdf->SetTextColor(30, 58, 95);
    $pdf->Cell($colW[0], 5, $dateLabel, 1, 0, 'L', true);
    $pdf->Cell($colW[1], 5, $dayLabel,  1, 0, 'C', true);
    $pdf->Cell($colW[2], 5, $amIn,      1, 0, 'C', true);
    $pdf->Cell($colW[3], 5, $amOut,     1, 0, 'C', true);
    $pdf->Cell($colW[4], 5, $pmIn,      1, 0, 'C', true);
    $pdf->Cell($colW[5], 5, $pmOut,     1, 0, 'C', true);
    $pdf->Cell($colW[6], 5, $hours,     1, 0, 'C', true);

    // Under time in amber if > 0
    if ($record['under_time'] > 0) {
        $pdf->SetTextColor(170, 120, 10);
    }
    $pdf->Cell($colW[7], 5, $underTime, 1, 0, 'C', true);

    // Status color
    $pdf->SetTextColor(
        $status === 'present'  ? 16  : ($status === 'half-day' ? 170 : 180),
        $status === 'present'  ? 120 : ($status === 'half-day' ? 120 : 50),
        $status === 'present'  ? 90  : ($status === 'half-day' ? 10  : 50)
    );
    $pdf->Cell($colW[8], 5, ucfirst($status), 1, 1, 'C', true);
}

// Monthly summary bar
$pdf->Ln(3);
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$summaryText = 'MONTHLY SUMMARY   |   Days: ' . $summary['days']
             . '   |   Total Hours: ' . number_format($summary['total_hours'], 1)
             . '   |   Total Under Time: ' . number_format($summary['total_under'], 1) . ' hrs';
$pdf->Cell(0, 9, $summaryText, 0, 1, 'C', true);

// Signature area
$pdf->Ln(10);
$pdf->SetTextColor(30, 58, 95);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(95, 5, 'Employee Signature: ___________________________', 0, 0);
$pdf->Cell(0,  5, 'Supervisor Signature: ___________________________', 0, 1);
$pdf->Ln(5);
$pdf->Cell(95, 5, 'Date Signed: ___________________________', 0, 0);
$pdf->Cell(0,  5, 'Date Signed: ___________________________', 0, 1);

// Output inline
$filename = 'DTR_' . $user['full_name'] . '_' . $months[$month] . '_' . $year . '.pdf';
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
$pdf->Output('I', $filename);
exit;
