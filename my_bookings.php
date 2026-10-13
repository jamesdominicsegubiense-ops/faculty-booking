<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_role('student');

$me = current_user();

/* ---------- CANCEL PENDING REQUEST ---------- */
$notice = '';
$noticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
        $noticeType = 'error';
    } else {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

        if (!$bookingId) {
            $notice = 'Invalid request.';
            $noticeType = 'error';
        } else {
            $del = $pdo->prepare(
                "DELETE FROM bookings
                 WHERE id = :id
                   AND student_id = :sid
                   AND status = 'Pending'"
            );
            $del->execute([':id' => $bookingId, ':sid' => $me['id']]);

            if ($del->rowCount()) {
                $notice = "Booking #{$bookingId} was cancelled.";
            } else {
                $notice = 'That booking cannot be cancelled (already approved/declined or not yours).';
                $noticeType = 'error';
            }
        }
    }
}

/* ---------- FETCH STUDENT'S BOOKINGS ---------- */
$stmt = $pdo->prepare(
    "SELECT b.id, b.booking_date, b.start_time, b.end_time,
            b.purpose, b.notes, b.status, b.created_at,
            u.full_name AS faculty_name, u.email AS faculty_email,
            p.department, p.consultation_mode, p.office_location
     FROM bookings b
     INNER JOIN users u ON u.id = b.faculty_id
     LEFT JOIN faculty_profiles p ON p.user_id = b.faculty_id
     WHERE b.student_id = :sid
     ORDER BY b.booking_date DESC, b.start_time DESC"
);
$stmt->execute([':sid' => $me['id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My Consultations';
require __DIR__ . '/includes/header.php';
?>

<section class="hero hero-compact">
    <div class="hero-split">
        <div>
            <span class="hero-eyebrow">Student</span>
            <h1 class="page-title">My consultation requests</h1>
            <p class="page-sub">Track the status of every consultation you have requested.</p>
        </div>
        <a class="btn btn-primary" href="index.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Book new consultation
        </a>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert <?= $noticeType === 'error' ? 'alert-error' : 'alert-success' ?>">
        <?= e($notice) ?>
    </div>
<?php endif; ?>

<?php if (!$bookings): ?>
    <div class="empty">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted);margin-bottom:1rem">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <h3>You haven't booked any consultations yet</h3>
        <p>Browse faculty members and pick a time slot that works for you.</p>
        <p style="margin-top:1.25rem">
            <a class="btn btn-primary" href="index.php">Browse faculty</a>
        </p>
    </div>
<?php else: ?>
    <div class="booking-cards">
        <?php foreach ($bookings as $b): ?>
            <article class="booking-card booking-card-modern">
                <div class="booking-card-band band-<?= strtolower($b['status']) ?>"></div>

                <header class="booking-card-head">
                    <div class="booking-faculty">
                        <div class="avatar avatar-sm"><?= e(initials($b['faculty_name'])) ?></div>
                        <div>
                            <h3 class="booking-faculty-name"><?= e($b['faculty_name']) ?></h3>
                            <p class="booking-faculty-dept"><?= e($b['department'] ?? 'Faculty') ?></p>
                        </div>
                    </div>
                    <span class="status <?= e(status_class($b['status'])) ?>">
                        <?= e($b['status']) ?>
                    </span>
                </header>

                <div class="booking-info">
                    <div class="booking-info-row">
                        <span class="booking-label">When</span>
                        <span class="booking-value">
                            <strong><?= e(date('D, M j, Y', strtotime($b['booking_date']))) ?></strong><br>
                            <small><?= e(substr($b['start_time'], 0, 5)) ?> – <?= e(substr($b['end_time'], 0, 5)) ?></small>
                        </span>
                    </div>

                    <div class="booking-info-row">
                        <span class="booking-label">Mode</span>
                        <span class="booking-value">
                            <span class="badge badge-mode"><?= e($b['consultation_mode'] ?? '—') ?></span>
                        </span>
                    </div>

                    <?php if (!empty($b['office_location'])): ?>
                        <div class="booking-info-row">
                            <span class="booking-label">Location</span>
                            <span class="booking-value"><?= e($b['office_location']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="booking-info-row">
                        <span class="booking-label">Purpose</span>
                        <span class="booking-value"><?= e($b['purpose']) ?></span>
                    </div>

                    <?php if (!empty($b['notes'])): ?>
                        <div class="booking-info-row">
                            <span class="booking-label">Notes</span>
                            <span class="booking-value"><?= nl2br(e($b['notes'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <footer class="booking-card-foot">
                    <small class="muted">
                        Requested <?= e(date('M j, Y g:i A', strtotime($b['created_at']))) ?>
                    </small>

                    <?php if ($b['status'] === 'Pending'): ?>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('Cancel this request?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn btn-decline btn-sm">Cancel request</button>
                        </form>
                    <?php endif; ?>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>