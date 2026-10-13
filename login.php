<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Please enter both your email and password.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, full_name, email, password_hash, role, is_active
                 FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && (int)$row['is_active'] === 1 && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'        => (int)$row['id'],
                    'full_name' => $row['full_name'],
                    'email'     => $row['email'],
                    'role'      => $row['role'],
                ];
                unset($_SESSION['csrf_token']);

                $dest = match ($row['role']) {
                    'admin'   => 'admin_dashboard.php',
                    'faculty' => 'faculty_dashboard.php',
                    default   => 'index.php',
                };
                header('Location: ' . $dest);
                exit;
            }

            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Log In';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">

    <aside class="auth-brand">
        <div class="auth-brand-body">
            <h2 class="auth-brand-title">Welcome back to your academic hub.</h2>
            <p class="auth-brand-sub">
                Book consultations, manage schedules, and stay in sync with your professors — all in one place.
            </p>

            <ul class="auth-features">
                <li><span class="feature-dot"></span>Browse faculty availability in real time</li>
                <li><span class="feature-dot"></span>Pick a slot that fits your schedule</li>
                <li><span class="feature-dot"></span>Track every request from pending to completed</li>
            </ul>
        </div>

        <div class="auth-brand-foot">
            <span>© <?= date('Y') ?> Faculty Consult</span>
        </div>
    </aside>

    <section class="auth-form-panel">
        <div class="auth-form-inner">
            <h1 class="auth-title">Sign in</h1>
            <p class="auth-sub">Enter your credentials to continue.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label class="field">
                    <span>Email address</span>
                    <input type="email" name="email" required value="<?= e($email) ?>"
                           placeholder="you@university.edu" autofocus>
                </label>

                <label class="field">
                    <span>Password</span>
                    <div class="password-wrap">
                        <input type="password" name="password" id="loginPassword" required
                               placeholder="Enter your password">
                        <button type="button" class="password-toggle"
                                onclick="togglePassword('loginPassword', this)"
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

                <button type="submit" class="btn btn-primary btn-block btn-lg">Log in</button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <p class="auth-foot">
                Don't have an account?
                <a href="register.php" class="auth-link">Create an account →</a>
            </p>
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