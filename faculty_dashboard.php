<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_role('faculty');

$me     = current_user();
$notice = '';
$noticeType = 'success';

/* ------------------------------------------------------------------
 |  HANDLE STATUS UPDATES (Approve / Decline / Complete)
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
        $noticeType = 'error';
    } else {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        $status    = (string)($_POST['status'] ?? '');

        if (!$bookingId || !in_array($status, ['Approved', 'Declined', 'Completed'], true)) {
            $notice = 'Invalid request.';
            $noticeType = 'error';
        } else {
            $requiredFrom = match ($status) {
                'Approved', 'Declined' => 'Pending',
                'Completed'            => 'Approved',
            };

            $upd = $pdo->prepare(
                'UPDATE bookings
                 SET status = :status
                 WHERE id = :id
                   AND faculty_id = :fid
                   AND status = :from'
            );
            $upd->execute([
                ':status' => $status,
                ':id'     => $bookingId,
                ':fid'    => $me['id'],
                ':from'   => $requiredFrom,
            ]);

            if ($upd->rowCount()) {
                $notice = "Booking #{$bookingId} was marked as {$status}.";
            } else {
                $notice = 'That booking cannot be updated (wrong current status, or not yours).';
                $noticeType = 'error';
            }
        }
    }
}

/* ------------------------------------------------------------------
 |  STATS
 * ------------------------------------------------------------------ */
$stats = ['Pending' => 0, 'Approved' => 0, 'Completed' => 0, 'Declined' => 0];
$sStmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS total FROM bookings WHERE faculty_id = ? GROUP BY status'
);
$sStmt->execute([$me['id']]);
foreach ($sStmt->fetchAll() as $row) {
    if (array_key_exists($row['status'], $stats)) {
        $stats[$row['status']] = (int)$row['total'];
    }
}

/* ------------------------------------------------------------------
 |  BOOKING LIST
 * ------------------------------------------------------------------ */
$filter = (string)($_GET['status'] ?? 'All');
if (!in_array($filter, ['All', 'Pending', 'Approved', 'Completed', 'Declined'], true)) {
    $filter = 'All';
}

$sql = "SELECT b.id, b.booking_date, b.start_time, b.end_time,
               b.purpose, b.notes, b.status, b.created_at,
               u.full_name AS student_name, u.email AS student_email
        FROM bookings b
        INNER JOIN users u ON u.id = b.student_id
        WHERE b.faculty_id = :fid";

$params = [':fid' => $me['id']];

if ($filter !== 'All') {
    $sql .= ' AND b.status = :status';
    $params[':status'] = $filter;
}
$sql .= ' ORDER BY b.booking_date DESC, b.start_time DESC';

$bStmt = $pdo->prepare($sql);
$bStmt->execute($params);
$bookings = $bStmt->fetchAll();

$pageTitle = 'Faculty Dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="hero hero-compact">
    <div class="hero-split">
        <div>
            <span class="hero-eyebrow">Faculty Dashboard</span>
            <h1 class="page-title">Welcome, <?= e($me['full_name']) ?></h1>
            <p class="page-sub">Review, approve, or decline your incoming consultation requests.</p>
        </div>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert <?= $noticeType === 'error' ? 'alert-error' : 'alert-success' ?>">
        <?= e($notice) ?>
    </div>
<?php endif; ?>

<div class="stats stats-4">
    <div class="stat-card stat-pending">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <span class="stat-value"><?= $stats['Pending'] ?></span>
        <span class="stat-label">Pending</span>
    </div>
    <div class="stat-card stat-approved">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <span class="stat-value"><?= $stats['Approved'] ?></span>
        <span class="stat-label">Approved</span>
    </div>
    <div class="stat-card stat-completed">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
        </div>
        <span class="stat-value"><?= $stats['Completed'] ?></span>
        <span class="stat-label">Completed</span>
    </div>
    <div class="stat-card stat-declined">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        </div>
        <span class="stat-value"><?= $stats['Declined'] ?></span>
        <span class="stat-label">Declined</span>
    </div>
</div>

<nav class="pill-tabs">
    <?php foreach (['All', 'Pending', 'Approved', 'Completed', 'Declined'] as $tab): ?>
        <a class="pill-tab <?= $filter === $tab ? 'is-active' : '' ?>"
           href="faculty_dashboard.php?status=<?= urlencode($tab) ?>">
            <?= e($tab) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$bookings): ?>
    <div class="empty">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted);margin-bottom:.75rem">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <h3>No <?= $filter === 'All' ? '' : strtolower($filter) . ' ' ?>requests</h3>
        <p>When students book your published slots, their requests will appear here.</p>
    </div>
<?php else: ?>
    <div class="request-list">
        <?php foreach ($bookings as $b): ?>
            <?php $isPast = strtotime($b['booking_date']) <= strtotime('today'); ?>
            <article class="request-card">
                <div class="request-card-left">
                    <div class="user-avatar role-student"><?= e(initials($b['student_name'])) ?></div>
                </div>

                <div class="request-card-main">
                    <header class="request-card-head">
                        <div>
                            <h3 class="request-card-name"><?= e($b['student_name']) ?></h3>
                            <p class="request-card-email"><?= e($b['student_email']) ?></p>
                        </div>
                        <span class="status <?= e(status_class($b['status'])) ?>"><?= e($b['status']) ?></span>
                    </header>

                    <div class="request-card-body">
                        <div class="request-field">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <span><?= e(date('D, M j, Y', strtotime($b['booking_date']))) ?> · <?= e(substr($b['start_time'], 0, 5)) ?>–<?= e(substr($b['end_time'], 0, 5)) ?></span>
                        </div>
                        <div class="request-field">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <span><?= e($b['purpose']) ?></span>
                        </div>
                        <?php if (!empty($b['notes'])): ?>
                            <details class="request-notes">
                                <summary>View notes</summary>
                                <p><?= nl2br(e($b['notes'])) ?></p>
                            </details>
                        <?php endif; ?>
                    </div>

                    <footer class="request-card-foot">
                        <?php if ($b['status'] === 'Pending'): ?>
                            <form method="post" class="request-actions">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" name="status" value="Approved" class="btn btn-approve btn-sm">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                    Approve
                                </button>
                                <button type="submit" name="status" value="Declined" class="btn btn-decline btn-sm">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                    Decline
                                </button>
                            </form>
                        <?php elseif ($b['status'] === 'Approved'): ?>
                            <?php if ($isPast): ?>
                                <form method="post" onsubmit="return confirm('Mark this consultation as completed?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" name="status" value="Completed" class="btn btn-complete btn-sm">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                                        Mark as done
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="muted small">Scheduled — mark done after the date</span>
                            <?php endif; ?>
                        <?php elseif ($b['status'] === 'Completed'): ?>
                            <span class="status status-completed">✓ Consultation completed</span>
                        <?php else: ?>
                            <span class="muted small">No action needed</span>
                        <?php endif; ?>
                    </footer>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>