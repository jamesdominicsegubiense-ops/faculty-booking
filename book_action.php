<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, string $message, array $extra = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

/* ---------- GUARDS ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', [], 405);
}

$user = current_user();

if (!$user) {
    respond(false, 'Please log in to book a consultation.', [], 401);
}
if ($user['role'] !== 'student') {
    respond(false, 'Only student accounts can create bookings.', [], 403);
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    respond(false, 'Your session expired. Please refresh the page and try again.', [], 419);
}

/* ---------- INPUT ---------- */
$facultyId  = filter_input(INPUT_POST, 'faculty_id',  FILTER_VALIDATE_INT);
$scheduleId = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
$date       = trim((string)($_POST['booking_date'] ?? ''));
$purpose    = trim((string)($_POST['purpose'] ?? ''));
$notes      = trim((string)($_POST['notes'] ?? ''));

/* ---------- VALIDATION ---------- */
$errors = [];

if (!$facultyId)  { $errors[] = 'Invalid faculty selected.'; }
if (!$scheduleId) { $errors[] = 'Please choose a time slot.'; }

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    $errors[] = 'Please choose a valid date.';
} elseif ($date < date('Y-m-d')) {
    $errors[] = 'You cannot book a date in the past.';
} elseif ($date > date('Y-m-d', strtotime('+90 days'))) {
    $errors[] = 'Bookings can only be made up to 90 days in advance.';
}

if ($purpose === '') {
    $errors[] = 'Please describe the purpose of your consultation.';
} elseif (mb_strlen($purpose) > 150) {
    $errors[] = 'The purpose must be 150 characters or fewer.';
}

if (mb_strlen($notes) > 500) {
    $errors[] = 'Notes must be 500 characters or fewer.';
}

if ($errors) {
    respond(false, implode(' ', $errors), ['errors' => $errors], 422);
}

/* ---------- BUSINESS LOGIC ---------- */
try {
    $slotStmt = $pdo->prepare(
    'SELECT id, faculty_id, day_of_week, slot_date, start_time, end_time
     FROM schedules
     WHERE id = ? AND faculty_id = ? AND is_active = 1
     LIMIT 1'
    );
    $slotStmt->execute([$scheduleId, $facultyId]);
    $slot = $slotStmt->fetch();

    if (!$slot) {
        respond(false, 'That time slot is no longer available.', [], 404);
    }

    if (!empty($slot['slot_date'])) {
    /* Fixed-date slot — the requested date must match exactly */
    if ($date !== $slot['slot_date']) {
        respond(
            false,
            'That slot is only available on ' . date('M j, Y', strtotime($slot['slot_date'])) . '.',
            [],
            422
        );
    }
} else {
    /* Recurring slot — must match the weekday */
    if (date('l', strtotime($date)) !== $slot['day_of_week']) {
        respond(false, 'The selected date does not match this slot\'s day of the week.', [], 422);
    }
}

    $pdo->beginTransaction();

    $lock = $pdo->prepare(
        "SELECT id
         FROM bookings
         WHERE faculty_id   = :fid
           AND booking_date = :bdate
           AND status IN ('Pending','Approved')
           AND start_time < :end_time
           AND end_time   > :start_time
         LIMIT 1
         FOR UPDATE"
    );
    $lock->execute([
        ':fid'        => $facultyId,
        ':bdate'      => $date,
        ':start_time' => $slot['start_time'],
        ':end_time'   => $slot['end_time'],
    ]);

    if ($lock->fetch()) {
        $pdo->rollBack();
        respond(false, 'Sorry — that slot was just taken. Please pick another time.', [], 409);
    }

    $insert = $pdo->prepare(
        "INSERT INTO bookings
            (student_id, faculty_id, schedule_id, booking_date,
             start_time, end_time, purpose, notes, status)
         VALUES
            (:student_id, :faculty_id, :schedule_id, :booking_date,
             :start_time, :end_time, :purpose, :notes, 'Pending')"
    );
    $insert->execute([
        ':student_id'  => $user['id'],
        ':faculty_id'  => $facultyId,
        ':schedule_id' => $scheduleId,
        ':booking_date'=> $date,
        ':start_time'  => $slot['start_time'],
        ':end_time'    => $slot['end_time'],
        ':purpose'     => $purpose,
        ':notes'       => $notes !== '' ? $notes : null,
    ]);

    $newId = (int)$pdo->lastInsertId();
    $pdo->commit();

    respond(true, 'Request submitted! You will be notified once it is reviewed.', [
        'booking_id' => $newId,
        'status'     => 'Pending',
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Booking error: ' . $e->getMessage());
    respond(false, 'Something went wrong while saving your booking. Please try again.', [], 500);
}