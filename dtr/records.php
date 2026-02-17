<?php
require_once 'config/db.php';
requireLogin();

$user = getUser($pdo, $_SESSION['user_id']);

// Filters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? ORDER BY date DESC");
$stmt->execute([$_SESSION['user_id'], $month, $year]);
$records = $stmt->fetchAll();

// Monthly summary
$stmt = $pdo->prepare("SELECT COUNT(*) as days, COALESCE(SUM(hours_worked), 0) as total_hours, COALESCE(SUM(under_time), 0) as total_under FROM time_records WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$stmt->execute([$_SESSION['user_id'], $month, $year]);
$monthlySummary = $stmt->fetch();

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records — DTR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { 50: '#f0f3f9', 100: '#d9e0ef', 200: '#b3c1df', 300: '#8da2cf', 400: '#6783bf', 500: '#4a6aa8', 600: '#3b5486', 700: '#2c3f65', 800: '#1e2a44', 900: '#0f1523' },
                        gold: { 50: '#fdf9ef', 100: '#faf0d0', 200: '#f5e0a1', 300: '#efd072', 400: '#e8be43', 500: '#d4a828', 600: '#a9861f', 700: '#7e6417', 800: '#54430f', 900: '#2a2108' },
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-screen bg-navy-900 text-white">

    <!-- Navigation -->
    <nav class="border-b border-navy-700/50 bg-navy-800/30 backdrop-blur sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gold-500/10 border border-gold-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="font-semibold text-sm tracking-tight">DTR System by Raf Medina</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="dashboard.php" class="text-sm text-navy-300 hover:text-white transition">Dashboard</a>
                <a href="records.php" class="text-sm text-gold-400 font-medium">Records</a>
                <div class="flex items-center gap-3 pl-6 border-l border-navy-700/50">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-medium"><?= htmlspecialchars($user['full_name']) ?></p>
                        <p class="text-xs text-navy-400"><?= htmlspecialchars($user['position']) ?></p>
                    </div>
                    <a href="logout.php" class="text-navy-400 hover:text-red-400 transition" title="Sign out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

        <!-- Header & Filter -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-semibold">Time Records</h1>
                <p class="text-navy-400 text-sm mt-1"><?= $months[$month] ?> <?= $year ?> &mdash; <?= $monthlySummary['days'] ?> days, <?= number_format($monthlySummary['total_hours'], 1) ?> hours</p>
            </div>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <form method="GET" class="flex items-center gap-2">
                    <select name="month" class="px-3 py-2 rounded-lg bg-navy-800 border border-navy-700/50 text-sm text-white focus:outline-none focus:border-gold-500/50 transition">
                        <?php foreach ($months as $key => $name): ?>
                            <option value="<?= $key ?>" <?= $key === $month ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="year" class="px-3 py-2 rounded-lg bg-navy-800 border border-navy-700/50 text-sm text-white focus:outline-none focus:border-gold-500/50 transition">
                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-gold-500/10 border border-gold-500/20 text-gold-400 text-sm font-medium hover:bg-gold-500/20 transition">
                        Filter
                    </button>
                </form>
                <a href="generate_pdf.php?month=<?= urlencode($month) ?>&year=<?= urlencode($year) ?>" target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-navy-700/50 border border-gold-500/30 text-gold-400 text-sm font-medium hover:bg-gold-500/10 hover:border-gold-500/60 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M14 3v4a1 1 0 001 1h4M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2zM12 11v6m-3-3h6"/>
                    </svg>
                    View PDF
                </a>
            </div>
        </div>

        <!-- Records Table -->
        <div class="bg-navy-800/50 border border-navy-700/50 rounded-2xl overflow-hidden">
            <?php if (empty($records)): ?>
                <div class="px-6 py-16 text-center text-navy-500 text-sm">No records found for this period.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-navy-700/50 text-xs text-navy-400 uppercase tracking-wider">
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Day</th>
                                <th class="px-4 py-3 text-left">AM In</th>
                                <th class="px-4 py-3 text-left">AM Out</th>
                                <th class="px-4 py-3 text-left">PM In</th>
                                <th class="px-4 py-3 text-left">PM Out</th>
                                <th class="px-4 py-3 text-left">Hours</th>
                                <th class="px-4 py-3 text-left">Under Time</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Journal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-navy-700/30">
                            <?php foreach ($records as $record): ?>
                                <tr class="hover:bg-navy-700/20 transition-colors">
                                    <td class="px-4 py-4 font-medium whitespace-nowrap"><?= date('M d, Y', strtotime($record['date'])) ?></td>
                                    <td class="px-4 py-4 text-navy-300 whitespace-nowrap"><?= date('D', strtotime($record['date'])) ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap"><?= !empty($record['time_in'])     ? date('h:i A', strtotime($record['time_in']))     : '—' ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap"><?= !empty($record['am_time_out']) ? date('h:i A', strtotime($record['am_time_out'])) : '—' ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap"><?= !empty($record['pm_time_in'])  ? date('h:i A', strtotime($record['pm_time_in']))  : '—' ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap"><?= !empty($record['time_out'])    ? date('h:i A', strtotime($record['time_out']))    : '—' ?></td>
                                    <td class="px-4 py-4 font-medium whitespace-nowrap"><?= $record['hours_worked'] !== null ? number_format($record['hours_worked'], 1) . ' hrs' : '—' ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php if ($record['under_time'] > 0): ?>
                                            <span class="text-gold-400 font-medium"><?= number_format($record['under_time'], 1) ?> hrs</span>
                                        <?php else: ?>
                                            <span class="text-navy-500">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?= $record['status'] === 'present' ? 'bg-emerald-500/10 text-emerald-400' : ($record['status'] === 'half-day' ? 'bg-gold-500/10 text-gold-400' : 'bg-red-500/10 text-red-400') ?>">
                                            <?= ucfirst($record['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if (!empty($record['remarks'])): ?>
                                            <button type="button"
                                                onclick="openJournalView(<?= htmlspecialchars(json_encode($record['remarks'])) ?>, <?= htmlspecialchars(json_encode(date('M d, Y', strtotime($record['date'])))) ?>)"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-xs transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-navy-500">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Monthly Total -->
                <div class="px-6 py-4 border-t border-navy-700/50 flex flex-wrap items-center justify-between gap-2 bg-navy-800/30">
                    <span class="text-sm text-navy-400">Monthly Total</span>
                    <div class="flex items-center gap-4 text-sm font-semibold">
                        <span><?= number_format($monthlySummary['total_hours'], 1) ?> hrs &mdash; <?= $monthlySummary['days'] ?> day(s)</span>
                        <?php if ($monthlySummary['total_under'] > 0): ?>
                            <span class="text-gold-400">Under time: <?= number_format($monthlySummary['total_under'], 1) ?> hrs</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Journal View Modal -->
    <div id="journalViewModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeJournalView()"></div>
        <div class="relative bg-navy-800 border border-navy-700/50 rounded-2xl w-full max-w-lg p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-medium text-white">Daily Journal</h3>
                    <p class="text-xs text-navy-400" id="journalViewDate"></p>
                </div>
                <button onclick="closeJournalView()" class="text-navy-400 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="journalViewContent" class="px-4 py-3 rounded-xl bg-navy-900/50 border border-navy-600/50 text-sm text-navy-200 whitespace-pre-wrap max-h-80 overflow-y-auto"></div>
        </div>
    </div>

    <script>
        function openJournalView(text, date) {
            document.getElementById('journalViewContent').textContent = text;
            document.getElementById('journalViewDate').textContent = date;
            const modal = document.getElementById('journalViewModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeJournalView() {
            const modal = document.getElementById('journalViewModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>
