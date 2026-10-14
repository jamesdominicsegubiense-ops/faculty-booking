<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

/* ---------- FILTERS ---------- */
$q    = trim((string)($_GET['q'] ?? ''));
$dept = trim((string)($_GET['dept'] ?? ''));
$mode = trim((string)($_GET['mode'] ?? ''));

$sql = "SELECT u.id, u.full_name, u.email,
               p.department, p.office_location, p.bio, p.consultation_mode
        FROM users u
        INNER JOIN faculty_profiles p ON p.user_id = u.id
        WHERE u.role = 'faculty' AND u.is_active = 1";

$params = [];

if ($q !== '') {
    $sql .= " AND (u.full_name LIKE :q OR p.department LIKE :q OR p.bio LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($dept !== '') {
    $sql .= " AND p.department = :dept";
    $params[':dept'] = $dept;
}
if ($mode !== '') {
    $sql .= " AND p.consultation_mode = :mode";
    $params[':mode'] = $mode;
}
$sql .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facultyList = $stmt->fetchAll();

$departments = DEPARTMENTS;

/* ---------- SCHEDULES ---------- */
$schedulesByFaculty = [];

if ($facultyList) {
    $ids = array_map('intval', array_column($facultyList, 'id'));
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $sStmt = $pdo->prepare(
    "SELECT id, faculty_id, day_of_week, slot_date, start_time, end_time
     FROM schedules
     WHERE is_active = 1 AND faculty_id IN ($in)
     ORDER BY slot_date ASC, start_time ASC"
);
    $sStmt->execute($ids);

    foreach ($sStmt->fetchAll() as $row) {
        $schedulesByFaculty[(int)$row['faculty_id']][] = [
            'id'          => (int)$row['id'],
            'day_of_week' => $row['day_of_week'],
            'slot_date'   => $row['slot_date'],
            'start_time'  => $row['start_time'],
            'end_time'    => $row['end_time'],
        ];
    }
}

/* ---------- TOTAL ACTIVE SLOTS ---------- */
$totalSlots = 0;
foreach ($schedulesByFaculty as $slots) {
    $totalSlots += count($slots);
}

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';

$user      = current_user();
$isStudent = $user && $user['role'] === 'student';
?>

<!-- ============ HERO BANNER ============ -->
<section class="hero-banner">
    <div class="hero-banner-inner">
        <div class="hero-copy">
            <span class="hero-eyebrow">Faculty Consult</span>
            <h1 class="hero-title">Book a consultation with your professor</h1>
            <p class="hero-desc">
                Browse faculty availability, pick a slot that fits your schedule, and send a request in seconds.
            </p>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat-value"><?= count($facultyList) ?></span>
                <span class="hero-stat-label">Faculty</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-value"><?= count(DEPARTMENTS) ?></span>
                <span class="hero-stat-label">Departments</span>
            </div>
        </div>
    </div>
</section>

<!-- ============ FILTERS ============ -->
<form class="filters filters-modern" method="get" action="index.php" id="faculty-list">
    <div class="filter-row">
        <label class="filter-field filter-search">
            <svg class="filter-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="search" name="q" value="<?= e($q) ?>"
                   placeholder="Search by name, department, or expertise…">
        </label>

        <label class="filter-field">
            <select name="dept">
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>>
                        <?= e($d) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="filter-field">
            <select name="mode">
                <option value="">Any mode</option>
                <?php foreach (['In-Person', 'Online', 'Hybrid'] as $m): ?>
                    <option value="<?= e($m) ?>" <?= $mode === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if ($q !== '' || $dept !== '' || $mode !== ''): ?>
                <a class="btn btn-ghost" href="index.php">Reset</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($q !== '' || $dept !== '' || $mode !== ''): ?>
        <div class="filter-tags">
            <span class="filter-tags-label">Active filters:</span>
            <?php if ($q !== ''): ?><span class="filter-tag">Search: <?= e($q) ?></span><?php endif; ?>
            <?php if ($dept !== ''): ?><span class="filter-tag"><?= e($dept) ?></span><?php endif; ?>
            <?php if ($mode !== ''): ?><span class="filter-tag"><?= e($mode) ?></span><?php endif; ?>
        </div>
    <?php endif; ?>
</form>

<!-- ============ RESULT COUNT ============ -->
<p class="result-count">
    Showing <strong><?= count($facultyList) ?></strong>
    facult<?= count($facultyList) === 1 ? 'y member' : 'y members' ?>
    <?php if ($q !== '' || $dept !== '' || $mode !== ''): ?>
        matching your filters
    <?php endif; ?>
</p>

<!-- ============ FACULTY CARDS ============ -->
<?php if (!$facultyList): ?>
    <div class="empty">
        <h3>No faculty match your filters</h3>
        <p>Try widening your search or resetting the filters.</p>
        <p style="margin-top:1rem">
            <a class="btn btn-primary" href="index.php">Show all faculty</a>
        </p>
    </div>
<?php else: ?>
    <div class="faculty-grid">
        <?php foreach ($facultyList as $f): ?>
            <?php
                $slots = $schedulesByFaculty[(int)$f['id']] ?? [];
                $slotCount = count($slots);
                $modeClass = match ($f['consultation_mode']) {
                    'Online'  => 'mode-online',
                    'Hybrid'  => 'mode-hybrid',
                    default   => 'mode-inperson',
                };
            ?>
            <article class="faculty-card faculty-card-v2"
                     data-faculty-id="<?= (int)$f['id'] ?>"
                     data-faculty-name="<?= e($f['full_name']) ?>">

                <div class="card-accent <?= $modeClass ?>"></div>

                <header class="card-head">
                    <div class="avatar"><?= e(initials($f['full_name'])) ?></div>
                    <div class="card-identity">
                        <h2 class="card-name"><?= e($f['full_name']) ?></h2>
                        <p class="card-dept"><?= e($f['department']) ?></p>
                    </div>
                    <?php if ($slotCount > 0): ?>
                        <span class="card-availability" title="<?= $slotCount ?> weekly slot<?= $slotCount === 1 ? '' : 's' ?>">
                            <span class="card-availability-dot"></span>
                            Available
                        </span>
                    <?php else: ?>
                        <span class="card-availability card-availability-busy">Unavailable</span>
                    <?php endif; ?>
                </header>

                <div class="card-badges">
                    <span class="badge <?= $modeClass ?>"><?= e($f['consultation_mode']) ?></span>
                    <?php if ($slotCount > 0): ?>
                        <span class="badge badge-slots">
                            <?= $slotCount ?> weekly slot<?= $slotCount === 1 ? '' : 's' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($f['office_location'])): ?>
                    <p class="card-meta">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                             style="display:inline-block;vertical-align:-2px;margin-right:4px;color:var(--muted)">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <?= e($f['office_location']) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($f['bio'])): ?>
                    <p class="card-bio"><?= e(mb_strimwidth($f['bio'], 0, 130, '…')) ?></p>
                <?php endif; ?>

                <div class="card-foot">
                    <?php if ($isStudent): ?>
                        <button type="button" class="btn btn-primary js-book"
                                data-faculty-id="<?= (int)$f['id'] ?>">
                            Book consultation
                        </button>
                    <?php elseif (!$user): ?>
                        <a class="btn btn-primary" href="login.php">Log in to book</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-disabled" disabled>Students only</button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ================= BOOKING MODAL ================= -->
<div class="modal-overlay" id="bookingModal" style="display:none;">
    <div class="modal" role="dialog" aria-modal="true">
        <header class="modal-head">
            <div>
                <h2>Request a consultation</h2>
                <p class="modal-faculty" id="modalFaculty"></p>
            </div>
            <button type="button" class="modal-close" onclick="window.closeBookingModal()" aria-label="Close">&times;</button>
        </header>

        <form id="bookingForm" class="modal-body" onsubmit="return window.submitBooking(event);">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="faculty_id"  id="facultyId">
            <input type="hidden" name="schedule_id" id="scheduleId">

            <div class="modal-section">
                <h3 class="modal-label">1. Pick a time slot</h3>
                <div class="slot-list" id="slotList"></div>
            </div>

            <div class="modal-section">
                <h3 class="modal-label">2. Confirm the date</h3>
                <label class="field">
                    <span>Date</span>
                    <input type="date" name="booking_date" id="bookingDate"
                           min="<?= date('Y-m-d') ?>" required>
                </label>
            </div>

            <div class="modal-section">
                <h3 class="modal-label">3. Tell them why</h3>
                <label class="field">
                    <span>Purpose <em>(required, max 150 chars)</em></span>
                    <input type="text" name="purpose" maxlength="150" required
                           placeholder="e.g. Thesis chapter 2 review">
                </label>

                <label class="field">
                    <span>Additional notes <em>(optional, max 500 chars)</em></span>
                    <textarea name="notes" rows="3" maxlength="500"
                              placeholder="Anything the professor should prepare or know?"></textarea>
                </label>
            </div>

            <div class="form-msg" id="formMsg" role="status" aria-live="polite"></div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="window.closeBookingModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Submit request</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ INLINE JAVASCRIPT ============ -->
<script>
var SCHEDULES = <?= json_encode($schedulesByFaculty, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

var DAY_INDEX = {
    Sunday: 0, Monday: 1, Tuesday: 2, Wednesday: 3,
    Thursday: 4, Friday: 5, Saturday: 6
};

function nextDateFor(dayName) {
    var target = DAY_INDEX[dayName];
    if (target === undefined) { return null; }
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var diff = (target - today.getDay() + 7) % 7;
    if (diff === 0) { diff = 7; }
    var d = new Date(today);
    d.setDate(today.getDate() + diff);
    return d;
}

function toISO(d) {
    return d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0');
}

function fmtTime(t) { return String(t).slice(0, 5); }

document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.js-book');
    if (!btn) { return; }
    ev.preventDefault();

    var card = btn.closest('.faculty-card');
    var id = card.getAttribute('data-faculty-id');
    var name = card.getAttribute('data-faculty-name');

    document.getElementById('facultyId').value = id;
    document.getElementById('scheduleId').value = '';
    document.getElementById('modalFaculty').textContent = name;
    document.getElementById('formMsg').textContent = '';
    document.getElementById('formMsg').className = 'form-msg';
    document.getElementById('bookingForm').reset();
    document.getElementById('facultyId').value = id;

    var slotList = document.getElementById('slotList');
    slotList.innerHTML = '';

    var slots = SCHEDULES[id] || [];
    if (!slots.length) {
        slotList.innerHTML = '<p class="empty-slots">No published consultation hours yet.</p>';
    } else {
        slots.forEach(function (s) {
            var d = s.slot_date
                ? new Date(s.slot_date + 'T00:00:00')
                : nextDateFor(s.day_of_week);
            var slotBtn = document.createElement('button');
            slotBtn.type = 'button';
            slotBtn.className = 'slot-btn';
            slotBtn.setAttribute('data-schedule-id', s.id);
            slotBtn.setAttribute('data-date', d ? toISO(d) : '');
            var label = s.slot_date
                ? new Date(s.slot_date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
                : s.day_of_week;

            slotBtn.innerHTML =
                '<span class="slot-day">' + label + '</span>' +
                '<span class="slot-time">' + fmtTime(s.start_time) + ' – ' + fmtTime(s.end_time) + '</span>';
            slotList.appendChild(slotBtn);
        });
    }

    document.getElementById('bookingModal').style.display = 'grid';
    document.body.classList.add('modal-open');
});

document.addEventListener('click', function (ev) {
    var slotBtn = ev.target.closest('.slot-btn');
    if (!slotBtn) { return; }

    var slotList = document.getElementById('slotList');
    slotList.querySelectorAll('.slot-btn').forEach(function (b) {
        b.classList.remove('is-selected');
    });
    slotBtn.classList.add('is-selected');
    document.getElementById('scheduleId').value = slotBtn.getAttribute('data-schedule-id');
    document.getElementById('bookingDate').value = slotBtn.getAttribute('data-date');
});

window.closeBookingModal = function () {
    document.getElementById('bookingModal').style.display = 'none';
    document.body.classList.remove('modal-open');
};

document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { window.closeBookingModal(); }
});

window.submitBooking = function (ev) {
    ev.preventDefault();

    var scheduleId = document.getElementById('scheduleId').value;
    var msgBox = document.getElementById('formMsg');

    if (!scheduleId) {
        msgBox.textContent = 'Please pick a time slot first.';
        msgBox.className = 'form-msg is-error';
        return false;
    }

    var submitBtn = document.getElementById('submitBtn');
    var original = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';
    msgBox.textContent = '';
    msgBox.className = 'form-msg';

    var formData = new FormData(document.getElementById('bookingForm'));

    fetch('book_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        msgBox.textContent = data.message;
        msgBox.className = 'form-msg ' + (data.ok ? 'is-success' : 'is-error');
        if (data.ok) {
            document.querySelector('input[name="purpose"]').value = '';
            document.querySelector('textarea[name="notes"]').value = '';
        }
    })
    .catch(function (err) {
        console.error(err);
        msgBox.textContent = 'Network error. Please try again.';
        msgBox.className = 'form-msg is-error';
    })
    .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = original;
    });

    return false;
};
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>