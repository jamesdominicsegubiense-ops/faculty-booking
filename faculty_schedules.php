<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_role('faculty');

$me = current_user();
$notice = '';
$noticeType = 'success';

/* ---------- HANDLE ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
        $noticeType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');

        /* -------- ADD -------- */
        if ($action === 'add') {
    $slotDate = (string)($_POST['slot_date'] ?? '');
    $start    = (string)($_POST['start_time'] ?? '');
    $end      = (string)($_POST['end_time'] ?? '');

    $errors = [];

    $d = DateTime::createFromFormat('Y-m-d', $slotDate);
    if (!$d || $d->format('Y-m-d') !== $slotDate) {
        $errors[] = 'Please choose a valid date.';
    } elseif ($slotDate < date('Y-m-d')) {
        $errors[] = 'You cannot add a slot in the past.';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
        $errors[] = 'Invalid start time.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) {
        $errors[] = 'Invalid end time.';
    }
    if (!$errors && $start >= $end) {
        $errors[] = 'End time must be after start time.';
    }

    /* Overlap check — same date + overlapping time */
    if (!$errors) {
        $overlap = $pdo->prepare(
            "SELECT id FROM schedules
             WHERE faculty_id = ?
               AND slot_date = ?
               AND is_active = 1
               AND start_time < ?
               AND end_time > ?
             LIMIT 1"
        );
        $overlap->execute([$me['id'], $slotDate, $end . ':00', $start . ':00']);
        if ($overlap->fetch()) {
            $errors[] = 'This slot overlaps with another slot on ' . date('M j, Y', strtotime($slotDate)) . '.';
        }
    }

    if ($errors) {
        $notice = implode(' ', $errors);
        $noticeType = 'error';
    } else {
        /* Also derive day_of_week for old display code */
        $dayName = date('l', strtotime($slotDate));

        $ins = $pdo->prepare(
            'INSERT INTO schedules (faculty_id, slot_date, day_of_week, start_time, end_time, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $ins->execute([$me['id'], $slotDate, $dayName, $start . ':00', $end . ':00']);
        $notice = 'Slot added for ' . date('M j, Y', strtotime($slotDate)) . '.';
    }
}

        /* -------- DELETE -------- */
        elseif ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);

            if ($id) {
                $chk = $pdo->prepare(
                    "SELECT COUNT(*) FROM bookings
                     WHERE schedule_id = ?
                       AND status IN ('Pending','Approved')
                       AND booking_date >= CURDATE()"
                );
                $chk->execute([$id]);
                $count = (int)$chk->fetchColumn();

                if ($count > 0) {
                    $notice = "Cannot delete — there " . ($count === 1 ? 'is 1 upcoming booking' : "are {$count} upcoming bookings") . " on this slot.";
                    $noticeType = 'error';
                } else {
                    $del = $pdo->prepare('DELETE FROM schedules WHERE id = ? AND faculty_id = ?');
                    $del->execute([$id, $me['id']]);

                    if ($del->rowCount()) {
                        $notice = 'Schedule removed.';
                    } else {
                        $notice = 'Schedule not found.';
                        $noticeType = 'error';
                    }
                }
            }
        }

        /* -------- TOGGLE ACTIVE -------- */
        elseif ($action === 'toggle') {
            $id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);

            if ($id) {
                $upd = $pdo->prepare(
                    'UPDATE schedules SET is_active = NOT is_active WHERE id = ? AND faculty_id = ?'
                );
                $upd->execute([$id, $me['id']]);

                if ($upd->rowCount()) {
                    $notice = 'Schedule status updated.';
                } else {
                    $notice = 'Schedule not found.';
                    $noticeType = 'error';
                }
            }
        }
    }
}

/* ---------- FETCH ALL SCHEDULES ---------- */
$stmt = $pdo->prepare(
    "SELECT id, slot_date, day_of_week, start_time, end_time, is_active, created_at
     FROM schedules
     WHERE faculty_id = ?
     ORDER BY slot_date ASC, start_time ASC"
);
$stmt->execute([$me['id']]);
$schedules = $stmt->fetchAll();

$activeCount = 0;
foreach ($schedules as $s) { if ((int)$s['is_active'] === 1) { $activeCount++; } }

$pageTitle = 'My Schedule';
require __DIR__ . '/includes/header.php';
?>

<section class="hero hero-compact">
    <div class="hero-split">
        <div>
            <span class="hero-eyebrow">Faculty</span>
            <h1 class="page-title">My weekly consultation hours</h1>
            <p class="page-sub">Publish recurring time slots that students can book.</p>
        </div>
        <a class="btn btn-ghost" href="faculty_dashboard.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert <?= $noticeType === 'error' ? 'alert-error' : 'alert-success' ?>">
        <?= e($notice) ?>
    </div>
<?php endif; ?>

<div class="schedule-grid">

    <!-- Add slot -->
    <section class="panel">
        <div class="panel-head">
            <div class="panel-head-icon panel-head-icon-blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </div>
            <div>
                <h2 class="panel-title">Add new slot</h2>
                <p class="panel-sub">Publish a weekly hour.</p>
            </div>
        </div>

        <form method="post" class="stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">

            <label class="field">
                <span>Date</span>
                <input type="date" name="slot_date" required min="<?= date('Y-m-d') ?>">
            </label>

            <div class="time-row">
                <label class="field">
                    <span>Start</span>
                    <input type="time" name="start_time" required step="900">
                </label>
                <label class="field">
                    <span>End</span>
                    <input type="time" name="end_time" required step="900">
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Add slot
            </button>

            <p class="muted small" style="margin:.5rem 0 0">
                💡 Tip: add each hour as its own slot (e.g. 09:00–10:00, 10:00–11:00) so students can pick precisely.
            </p>
        </form>
    </section>

    <!-- Slots list -->
    <section class="panel">
        <div class="panel-head">
            <div class="panel-head-icon panel-head-icon-gold">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <div style="flex:1">
                <h2 class="panel-title">Published slots</h2>
                <p class="panel-sub"><?= $activeCount ?> active</p>
            </div>
        </div>

        <?php if (!$schedules): ?>
            <div class="empty" style="padding:2.5rem 1rem;border:none;background:transparent">
                <h3>No slots yet</h3>
                <p>Add your first weekly consultation hour using the form.</p>
            </div>
        <?php else: ?>
            <ul class="slot-list-manage">
                <?php foreach ($schedules as $s): ?>
                    <li class="slot-item <?= (int)$s['is_active'] === 1 ? '' : 'slot-item-inactive' ?>">
                        <div class="slot-item-left">
                            <span class="slot-item-day">
                                <?php if (!empty($s['slot_date'])): ?>
                                    <?= e(date('D, M j, Y', strtotime($s['slot_date']))) ?>
                                <?php else: ?>
                                    <?= e($s['day_of_week']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="slot-item-time">
                                <?= e(substr($s['start_time'], 0, 5)) ?> – <?= e(substr($s['end_time'], 0, 5)) ?>
                            </span>
                        </div>

                        <div class="slot-item-right">
                            <?php if ((int)$s['is_active'] === 1): ?>
                                <span class="status status-approved">Active</span>
                            <?php else: ?>
                                <span class="status status-declined">Disabled</span>
                            <?php endif; ?>

                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-ghost" title="<?= (int)$s['is_active'] === 1 ? 'Disable' : 'Enable' ?>">
                                    <?php if ((int)$s['is_active'] === 1): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
                                    <?php endif; ?>
                                </button>
                            </form>

                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Delete this slot?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-danger" title="Delete">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>