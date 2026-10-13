<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_role('admin');

$me     = current_user();
$notice = '';
$noticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
        $noticeType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create_faculty') {
            $name     = trim((string)($_POST['full_name'] ?? ''));
            $email    = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $dept     = (string)($_POST['department'] ?? '');
            $office   = trim((string)($_POST['office_location'] ?? ''));
            $mode     = (string)($_POST['consultation_mode'] ?? 'In-Person');

            $errors = [];
            if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Full name is required (max 100 characters).';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
            if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
            if (!in_array($dept, DEPARTMENTS, true)) $errors[] = 'Please choose a valid department.';
            if (mb_strlen($office) > 100) $errors[] = 'Office location must be 100 characters or fewer.';
            if (!in_array($mode, ['In-Person', 'Online', 'Hybrid'], true)) $errors[] = 'Invalid consultation mode.';

            if (!$errors) {
                $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $chk->execute([$email]);
                if ($chk->fetch()) $errors[] = 'That email is already registered.';
            }

            if ($errors) {
                $notice = implode(' ', $errors);
                $noticeType = 'error';
            } else {
                try {
                    $pdo->beginTransaction();
                    $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'faculty')");
                    $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO faculty_profiles (user_id, department, office_location, bio, consultation_mode) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$newId, $dept, $office !== '' ? $office : null, 'New faculty member.', $mode]);
                    $pdo->commit();
                    $notice = "Faculty account for {$name} was created successfully.";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Create faculty error: ' . $e->getMessage());
                    $notice = 'Could not create the faculty account.';
                    $noticeType = 'error';
                }
            }
        } elseif ($action === 'toggle_active') {
            $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($id && $id !== (int)$me['id']) {
                $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
                $notice = 'User status updated.';
            } else {
                $notice = 'You cannot deactivate your own account.';
                $noticeType = 'error';
            }
        } elseif ($action === 'delete_user') {
            $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$id) { $notice = 'Invalid user.'; $noticeType = 'error'; }
            elseif ($id === (int)$me['id']) { $notice = 'You cannot delete your own account.'; $noticeType = 'error'; }
            else {
                $target = $pdo->prepare('SELECT id, role, full_name FROM users WHERE id = ? LIMIT 1');
                $target->execute([$id]);
                $targetRow = $target->fetch();
                if (!$targetRow) { $notice = 'User not found.'; $noticeType = 'error'; }
                else {
                    $blockDelete = false;
                    if ($targetRow['role'] === 'admin') {
                        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                        if ($adminCount <= 1) { $blockDelete = true; $notice = 'Cannot delete the last remaining admin account.'; $noticeType = 'error'; }
                    }
                    if (!$blockDelete) {
                        try {
                            $pdo->beginTransaction();
                            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                            $pdo->commit();
                            $notice = 'User "' . $targetRow['full_name'] . '" was deleted along with all their bookings and schedules.';
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            error_log('Delete user error: ' . $e->getMessage());
                            $notice = 'Could not delete the user.';
                            $noticeType = 'error';
                        }
                    }
                }
            }
        }
    }
}

$stats = ['students' => 0, 'faculty' => 0, 'admins' => 0, 'bookings' => 0];
foreach ($pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role")->fetchAll() as $row) {
    $key = match ($row['role']) { 'student' => 'students', 'faculty' => 'faculty', 'admin' => 'admins', default => null };
    if ($key) $stats[$key] = (int)$row['c'];
}
$stats['bookings'] = (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();

$filter = (string)($_GET['role'] ?? 'All');
if (!in_array($filter, ['All', 'student', 'faculty', 'admin'], true)) $filter = 'All';

$sql = "SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.created_at, p.department
        FROM users u LEFT JOIN faculty_profiles p ON p.user_id = u.id";
$params = [];
if ($filter !== 'All') { $sql .= ' WHERE u.role = :r'; $params[':r'] = $filter; }
$sql .= " ORDER BY FIELD(u.role,'admin','faculty','student'), u.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="hero hero-compact">
    <div class="hero-split">
        <div>
            <span class="hero-eyebrow">Administrator</span>
            <h1 class="page-title">Admin dashboard</h1>
            <p class="page-sub">Manage faculty accounts and monitor system activity.</p>
        </div>
        <a class="btn btn-primary" href="admin_faculty_report.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3v18h18"/><path d="M7 14l3-3 4 4 6-6"/>
            </svg>
            View faculty report
        </a>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert <?= $noticeType === 'error' ? 'alert-error' : 'alert-success' ?>"><?= e($notice) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats stats-4">
    <div class="stat-card stat-students">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
        </div>
        <span class="stat-value"><?= $stats['students'] ?></span>
        <span class="stat-label">Students</span>
    </div>
    <div class="stat-card stat-faculty">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
        </div>
        <span class="stat-value"><?= $stats['faculty'] ?></span>
        <span class="stat-label">Faculty</span>
    </div>
    <div class="stat-card stat-admins">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </div>
        <span class="stat-value"><?= $stats['admins'] ?></span>
        <span class="stat-label">Admins</span>
    </div>
    <div class="stat-card stat-bookings">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
        </div>
        <span class="stat-value"><?= $stats['bookings'] ?></span>
        <span class="stat-label">Bookings</span>
    </div>
</div>

<div class="admin-grid">

    <!-- Create Faculty -->
    <section class="panel">
        <div class="panel-head">
            <div class="panel-head-icon panel-head-icon-blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/>
                </svg>
            </div>
            <div>
                <h2 class="panel-title">Create faculty account</h2>
                <p class="panel-sub">Faculty members cannot self-register.</p>
            </div>
        </div>

        <form method="post" class="stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_faculty">

            <label class="field">
                <span>Full name</span>
                <input type="text" name="full_name" maxlength="100" required placeholder="e.g. Dr. Jane Smith">
            </label>

            <label class="field">
                <span>Email address</span>
                <input type="email" name="email" maxlength="150" required placeholder="jane.smith@university.edu">
            </label>

            <label class="field">
                <span>Temporary password</span>
                <input type="text" name="password" minlength="8" required value="<?= e(bin2hex(random_bytes(4))) ?>">
                <small class="muted small">Share this with the faculty member.</small>
            </label>

            <label class="field">
                <span>Department</span>
                <select name="department" required>
                    <?php foreach (DEPARTMENTS as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Office location <em>(optional)</em></span>
                <input type="text" name="office_location" maxlength="100" placeholder="e.g. Room 304">
            </label>

            <label class="field">
                <span>Consultation mode</span>
                <select name="consultation_mode">
                    <?php foreach (['In-Person', 'Online', 'Hybrid'] as $m): ?><option value="<?= e($m) ?>"><?= e($m) ?></option><?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="btn btn-primary btn-block">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Create account
            </button>
        </form>
    </section>

    <!-- User List -->
    <section class="panel">
        <div class="panel-head">
            <div class="panel-head-icon panel-head-icon-gold">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div>
                <h2 class="panel-title">All users</h2>
                <p class="panel-sub"><?= count($users) ?> total in this view</p>
            </div>
        </div>

        <nav class="pill-tabs">
            <?php foreach (['All', 'student', 'faculty', 'admin'] as $tab): ?>
                <a class="pill-tab <?= $filter === $tab ? 'is-active' : '' ?>"
                   href="admin_dashboard.php?role=<?= urlencode($tab) ?>">
                    <?= e(ucfirst($tab)) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$users): ?>
            <div class="empty">
                <h3>No users found</h3>
                <p>Try a different filter.</p>
            </div>
        <?php else: ?>
            <ul class="user-list">
                <?php foreach ($users as $u): ?>
                    <?php
                        $roleClass = match ($u['role']) {
                            'admin'   => 'role-admin',
                            'faculty' => 'role-faculty',
                            default   => 'role-student',
                        };
                    ?>
                    <li class="user-row">
                        <div class="user-avatar <?= $roleClass ?>"><?= e(initials($u['full_name'])) ?></div>

                        <div class="user-meta">
                            <div class="user-name"><?= e($u['full_name']) ?></div>
                            <div class="user-email"><?= e($u['email']) ?></div>
                        </div>

                        <div class="user-tags">
                            <span class="role-chip <?= $roleClass ?>"><?= e(ucfirst($u['role'])) ?></span>
                            <?php if (!empty($u['department'])): ?>
                                <span class="dept-chip"><?= e($u['department']) ?></span>
                            <?php endif; ?>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="status status-approved">Active</span>
                            <?php else: ?>
                                <span class="status status-declined">Inactive</span>
                            <?php endif; ?>
                        </div>

                        <div class="user-actions">
                            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                <form method="post" style="display:inline"
                                      onsubmit="return confirm('<?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this account?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn-icon <?= (int)$u['is_active'] === 1 ? 'btn-icon-ghost' : 'btn-icon-ok' ?>" title="<?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                        <?php if ((int)$u['is_active'] === 1): ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        <?php else: ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
                                        <?php endif; ?>
                                    </button>
                                </form>

                                <form method="post" style="display:inline"
                                      onsubmit="return confirm('⚠️ PERMANENTLY delete &quot;<?= e($u['full_name']) ?>&quot;?\n\nThis removes the user AND all their bookings and schedules.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="you-chip">You</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>