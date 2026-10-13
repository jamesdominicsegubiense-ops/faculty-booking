<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old    = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['full_name'] = trim((string)($_POST['full_name'] ?? ''));
        $old['email']     = trim((string)($_POST['email'] ?? ''));
        $password         = (string)($_POST['password'] ?? '');
        $confirm          = (string)($_POST['password_confirm'] ?? '');

        if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
            $errors[] = 'Please enter your full name (max 100 characters).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$old['email']]);

            if ($check->fetch()) {
                $errors[] = 'That email address is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $ins = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role)
                     VALUES (:name, :email, :hash, 'student')"
                );
                $ins->execute([
                    ':name'  => $old['full_name'],
                    ':email' => $old['email'],
                    ':hash'  => $hash,
                ]);
                $newId = (int)$pdo->lastInsertId();

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'        => $newId,
                    'full_name' => $old['full_name'],
                    'email'     => $old['email'],
                    'role'      => 'student',
                ];
                unset($_SESSION['csrf_token']);

                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Create a Student Account';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">

    <aside class="auth-brand">
        <div class="auth-brand-body">
            <h2 class="auth-brand-title">Start booking consultations in seconds.</h2>
            <p class="auth-brand-sub">
                Create your free student account and get instant access to every faculty member on campus.
            </p>

            <ul class="auth-features">
                <li><span class="feature-dot"></span>Free forever — no fees, no ads</li>
                <li><span class="feature-dot"></span>Book with any professor in any department</li>
                <li><span class="feature-dot"></span>Get instant confirmation on every request</li>
            </ul>
        </div>

        <div class="auth-brand-foot">
            <span>© <?= date('Y') ?> Faculty Consult</span>
        </div>
    </aside>

    <section class="auth-form-panel">
        <div class="auth-form-inner">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-sub">Sign up as a student to start booking consultations.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" class="stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label class="field">
                    <span>Full name</span>
                    <input type="text" name="full_name" maxlength="100" required
                           value="<?= e($old['full_name']) ?>" placeholder="Juan Dela Cruz">
                </label>

                <label class="field">
                    <span>Email address</span>
                    <input type="email" name="email" maxlength="150" required
                           value="<?= e($old['email']) ?>" placeholder="you@student.edu">
                </label>

                <div class="field-row">
                    <label class="field">
                        <span>Password</span>
                        <div class="password-wrap">
                            <input type="password" name="password" id="regPassword" minlength="8" required
                                   placeholder="Min. 8 characters">
                            <button type="button" class="password-toggle"
                                    onclick="togglePassword('regPassword', this)"
                                    aria-label="Show password">
                                <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                    </label>

                    <label class="field">
                        <span>Confirm password</span>
                        <div class="password-wrap">
                            <input type="password" name="password_confirm" id="regPasswordConfirm" minlength="8" required
                                   placeholder="Repeat password">
                            <button type="button" class="password-toggle"
                                    onclick="togglePassword('regPasswordConfirm', this)"
                                    aria-label="Show password">
                                <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <p class="auth-foot">
                Already have an account?
                <a href="login.php" class="auth-link">Sign in →</a>
            </p>

            <div class="demo-box demo-box-note">
                <strong>Faculty or admin?</strong>
                <p class="muted small" style="margin:.3rem 0 .75rem">
                    Faculty accounts are created by the administrator.
                    Please contact the admin office if you need access.
                </p>
                <a class="btn btn-primary btn-sm"
                href="https://mail.google.com/mail/?view=cm&to=admin@university.edu&su=Faculty%20Account%20Request&body=Hello%20Admin%2C%0A%0AI%20would%20like%20to%20request%20a%20faculty%20account.%0A%0AName%3A%20%0ADepartment%3A%20%0AReason%3A%20%0A%0AThank%20you."
                target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Contact admin via Gmail
                </a>
            </div>
        </div>
    </section>
</div>

<script>
function togglePassword(id, btn) {
    var inp = document.getElementById(id);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.classList.add('is-visible');
        btn.setAttribute('aria-label', 'Hide password');
    } else {
        inp.type = 'password';
        btn.classList.remove('is-visible');
        btn.setAttribute('aria-label', 'Show password');
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>