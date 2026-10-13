<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_role('faculty');

$me = current_user();
$notice = '';
$noticeType = 'success';

/* ---------- FETCH (or create) PROFILE ---------- */
$stmt = $pdo->prepare('SELECT * FROM faculty_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$me['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    $pdo->prepare('INSERT INTO faculty_profiles (user_id, department) VALUES (?, ?)')
        ->execute([$me['id'], DEPARTMENTS[0]]);
    $stmt->execute([$me['id']]);
    $profile = $stmt->fetch();
}

/* ---------- HANDLE SUBMIT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
        $noticeType = 'error';
    } else {
        /* NOTE: Department is NOT read from POST — admin-only field */
        $office = trim((string)($_POST['office_location'] ?? ''));
        $bio    = trim((string)($_POST['bio'] ?? ''));
        $mode   = (string)($_POST['consultation_mode'] ?? 'In-Person');

        $errors = [];

        if (mb_strlen($office) > 100) {
            $errors[] = 'Office location must be 100 characters or fewer.';
        }
        if (mb_strlen($bio) > 1000) {
            $errors[] = 'Bio must be 1000 characters or fewer.';
        }
        if (!in_array($mode, ['In-Person', 'Online', 'Hybrid'], true)) {
            $errors[] = 'Invalid consultation mode.';
        }

        if ($errors) {
            $notice = implode(' ', $errors);
            $noticeType = 'error';
        } else {
            /* Department deliberately excluded from this UPDATE */
            $upd = $pdo->prepare(
                'UPDATE faculty_profiles
                 SET office_location   = :o,
                     bio               = :b,
                     consultation_mode = :m
                 WHERE user_id = :uid'
            );
            $upd->execute([
                ':o'   => $office !== '' ? $office : null,
                ':b'   => $bio !== '' ? $bio : null,
                ':m'   => $mode,
                ':uid' => $me['id'],
            ]);

            $notice = 'Profile updated successfully.';

            // Refresh data
            $stmt->execute([$me['id']]);
            $profile = $stmt->fetch();
        }
    }
}

$pageTitle = 'My Profile';
require __DIR__ . '/includes/header.php';
?>

<section class="hero hero-compact">
    <div class="hero-split">
        <div>
            <span class="hero-eyebrow">Faculty</span>
            <h1 class="page-title">My faculty profile</h1>
            <p class="page-sub">This information is shown to students on the browsing page.</p>
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

<div class="profile-wrap">
    <form method="post" class="profile-form panel">
        <div class="panel-head">
            <div class="panel-head-icon panel-head-icon-blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div>
                <h2 class="panel-title">Edit your information</h2>
                <p class="panel-sub">Changes appear immediately to students.</p>
            </div>
        </div>

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="readonly-row">
            <div>
                <span class="readonly-label">Department</span>
                <div class="readonly-value"><?= e($profile['department']) ?></div>
            </div>
            <span class="readonly-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Admin-only
            </span>
        </div>

        <label class="field">
            <span>Office location <em>(optional)</em></span>
            <input type="text" name="office_location" maxlength="100"
                   value="<?= e($profile['office_location'] ?? '') ?>"
                   placeholder="e.g. Room 304, Tech Building">
        </label>

        <label class="field">
            <span>Consultation mode</span>
            <select name="consultation_mode">
                <?php foreach (['In-Person', 'Online', 'Hybrid'] as $m): ?>
                    <option value="<?= e($m) ?>" <?= $profile['consultation_mode'] === $m ? 'selected' : '' ?>>
                        <?= e($m) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Short bio <em>(max 1000 characters)</em></span>
            <textarea name="bio" rows="5" maxlength="1000"
                      placeholder="Tell students about your areas of expertise and how you like to run consultations."><?= e($profile['bio'] ?? '') ?></textarea>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <path d="M17 21v-8H7v8M7 3v5h8"/>
                </svg>
                Save changes
            </button>
        </div>
    </form>

    <aside class="profile-side">
        <div class="panel">
            <h3 class="panel-title" style="margin-bottom:.25rem">Live preview</h3>
            <p class="panel-sub" style="margin-bottom:1rem">This is how students see your card.</p>

            <article class="faculty-card preview-card">
                <header class="card-head">
                    <div class="avatar"><?= e(initials($me['full_name'])) ?></div>
                    <div class="card-identity">
                        <h2 class="card-name"><?= e($me['full_name']) ?></h2>
                        <p class="card-dept"><?= e($profile['department']) ?></p>
                    </div>
                </header>
                <div class="card-badges">
                    <span class="badge badge-mode"><?= e($profile['consultation_mode']) ?></span>
                </div>
                <?php if (!empty($profile['office_location'])): ?>
                    <p class="card-meta"><strong>Office:</strong> <?= e($profile['office_location']) ?></p>
                <?php endif; ?>
                <?php if (!empty($profile['bio'])): ?>
                    <p class="card-bio"><?= e(mb_strimwidth($profile['bio'], 0, 150, '…')) ?></p>
                <?php endif; ?>
            </article>
        </div>

        <div class="panel panel-info">
            <strong>Next step</strong>
            <p style="margin:.35rem 0 0;font-size:.9rem">
                Publish your weekly consultation hours from the
                <a href="faculty_schedules.php">Schedule page</a>
                so students can book a slot with you.
            </p>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>