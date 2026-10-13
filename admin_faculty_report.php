<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_role('admin');

$me = current_user();

/* ------------------------------------------------------------------
 |  DATE RANGE FILTER
 * ------------------------------------------------------------------ */
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

$dateWhere = '';
$params    = [];

if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $dateWhere .= ' AND b.booking_date >= :from';
    $params[':from'] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $dateWhere .= ' AND b.booking_date <= :to';
    $params[':to'] = $to;
}

/* ------------------------------------------------------------------
 |  FACULTY + STATS
 * ------------------------------------------------------------------ */
$sql = "
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.is_active,
        p.department,
        p.consultation_mode,

        COUNT(b.id) AS total_requests,

        SUM(CASE WHEN b.status = 'Pending'   THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN b.status = 'Approved'  THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN b.status = 'Declined'  THEN 1 ELSE 0 END) AS declined_count,

        SUM(CASE WHEN b.status = 'Approved' AND b.booking_date >= CURDATE()
                 THEN 1 ELSE 0 END) AS upcoming_count,

        MAX(CASE WHEN b.status = 'Completed' THEN b.booking_date END) AS last_consultation
    FROM users u
    INNER JOIN faculty_profiles p ON p.user_id = u.id
    LEFT JOIN bookings b
        ON b.faculty_id = u.id
        $dateWhere
    WHERE u.role = 'faculty'
    GROUP BY u.id, u.full_name, u.email, u.is_active, p.department, p.consultation_mode
    ORDER BY completed_count DESC, total_requests DESC, u.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ------------------------------------------------------------------
 |  OVERALL TOTALS
 * ------------------------------------------------------------------ */
$totals = [
    'faculty'     => count($rows),
    'total'       => 0,
    'pending'     => 0,
    'approved'    => 0,
    'completed'   => 0,
    'declined'    => 0,
    'upcoming'    => 0,
    'no_activity' => 0,
];

foreach ($rows as $r) {
    $totals['total']     += (int)$r['total_requests'];
    $totals['pending']   += (int)$r['pending_count'];
    $totals['approved']  += (int)$r['approved_count'];
    $totals['completed'] += (int)$r['completed_count'];
    $totals['declined']  += (int)$r['declined_count'];
    $totals['upcoming']  += (int)$r['upcoming_count'];

    if ((int)$r['total_requests'] === 0) {
        $totals['no_activity']++;
    }
}

$pageTitle = 'Faculty Consultation Report';
require __DIR__ . '/includes/header.php';
?>

<section class="hero hero-compact">
    <div class="hero-split">
        <div>
            <span class="hero-eyebrow">Analytics</span>
            <h1 class="page-title">Faculty consultation report</h1>
            <p class="page-sub">See which professors are actively consulting students.</p>
        </div>
        <a class="btn btn-ghost" href="admin_dashboard.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to admin panel
        </a>
    </div>
</section>

<div class="stats stats-4">
    <div class="stat-card stat-completed">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>
            </svg>
        </div>
        <span class="stat-value"><?= $totals['completed'] ?></span>
        <span class="stat-label">Completed</span>
    </div>
    <div class="stat-card stat-pending">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
            </svg>
        </div>
        <span class="stat-value"><?= $totals['upcoming'] ?></span>
        <span class="stat-label">Upcoming</span>
    </div>
    <div class="stat-card stat-approved">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <span class="stat-value"><?= $totals['pending'] ?></span>
        <span class="stat-label">Pending</span>
    </div>
    <div class="stat-card stat-declined">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>
            </svg>
        </div>
        <span class="stat-value"><?= $totals['declined'] ?></span>
        <span class="stat-label">Declined</span>
    </div>
</div>

<form class="filter-bar" method="get" action="admin_faculty_report.php">
    <div class="filter-bar-fields">
        <label class="filter-bar-field">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <input type="date" name="from" value="<?= e($from) ?>" placeholder="From date">
        </label>
        <label class="filter-bar-field">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <input type="date" name="to" value="<?= e($to) ?>" placeholder="To date">
        </label>
        <div class="filter-bar-actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a class="btn btn-ghost" href="admin_faculty_report.php">Reset</a>
        </div>
    </div>
</form>

<p class="result-count">
    <strong><?= $totals['faculty'] ?></strong> faculty member<?= $totals['faculty'] === 1 ? '' : 's' ?>
    · <strong><?= $totals['total'] ?></strong> total booking<?= $totals['total'] === 1 ? '' : 's' ?>
    <?php if ($totals['no_activity'] > 0): ?>
        · <span class="warn-note"><?= $totals['no_activity'] ?> with no requests yet</span>
    <?php endif; ?>
</p>

<?php if (!$rows): ?>
    <div class="empty">
        <h3>No faculty accounts found</h3>
        <p>Create faculty accounts from the admin panel first.</p>
    </div>
<?php else: ?>
    <div class="report-cards">
        <?php foreach ($rows as $r): ?>
            <?php
                $total     = (int)$r['total_requests'];
                $completed = (int)$r['completed_count'];
                $upcoming  = (int)$r['upcoming_count'];

                if ($total === 0) {
                    $activityLabel = 'No activity'; $activityClass = 'status-declined';
                } elseif ($completed >= 5) {
                    $activityLabel = 'Very active'; $activityClass = 'status-approved';
                } elseif ($completed >= 1) {
                    $activityLabel = 'Active'; $activityClass = 'status-approved';
                } elseif ($upcoming >= 1) {
                    $activityLabel = 'Scheduled'; $activityClass = 'status-pending';
                } else {
                    $activityLabel = 'Awaiting'; $activityClass = 'status-pending';
                }
            ?>
            <article class="report-card">
                <header class="report-card-head">
                    <div class="report-card-user">
                        <div class="user-avatar role-faculty"><?= e(initials($r['full_name'])) ?></div>
                        <div>
                            <h3 class="report-card-name"><?= e($r['full_name']) ?></h3>
                            <p class="report-card-dept"><?= e($r['department']) ?></p>
                        </div>
                    </div>
                    <span class="status <?= $activityClass ?>"><?= $activityLabel ?></span>
                </header>

                <div class="report-metrics">
                    <div class="report-metric">
                        <span class="report-metric-value"><?= $total ?></span>
                        <span class="report-metric-label">Requests</span>
                    </div>
                    <div class="report-metric report-metric-ok">
                        <span class="report-metric-value"><?= $completed ?></span>
                        <span class="report-metric-label">Completed</span>
                    </div>
                    <div class="report-metric report-metric-pending">
                        <span class="report-metric-value"><?= $upcoming ?></span>
                        <span class="report-metric-label">Upcoming</span>
                    </div>
                    <div class="report-metric report-metric-warn">
                        <span class="report-metric-value"><?= (int)$r['pending_count'] ?></span>
                        <span class="report-metric-label">Pending</span>
                    </div>
                    <div class="report-metric report-metric-bad">
                        <span class="report-metric-value"><?= (int)$r['declined_count'] ?></span>
                        <span class="report-metric-label">Declined</span>
                    </div>
                </div>

                <footer class="report-card-foot">
                    <small class="muted">
                        <strong>Last consultation:</strong>
                        <?= !empty($r['last_consultation'])
                            ? e(date('M j, Y', strtotime($r['last_consultation'])))
                            : '—' ?>
                    </small>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>