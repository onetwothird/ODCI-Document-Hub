<?php
require_once __DIR__ . '/../ODCI/includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php?next=changepass.php');
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    logout();
    header('Location: login.php');
    exit();
}

$flash = $_SESSION['password_change_flash'] ?? null;
unset($_SESSION['password_change_flash']);
$message = is_array($flash) ? (string)($flash['message'] ?? '') : '';
$messageType = is_array($flash) ? (string)($flash['type'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verifyCSRFToken($csrfToken)) {
        $message = 'Your session could not verify this request. Refresh the page and try again.';
        $messageType = 'error';
    } elseif ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $message = 'Complete all password fields.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
        $message = 'Use a new password between 8 and 72 bytes.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'The password confirmation does not match.';
        $messageType = 'error';
    } elseif (!password_verify($currentPassword, (string)$currentUser['password'])) {
        $message = 'Your current password is incorrect.';
        $messageType = 'error';
    } elseif (password_verify($newPassword, (string)$currentUser['password'])) {
        $message = 'Choose a new password that differs from your current password.';
        $messageType = 'error';
    } else {
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
            $updateStmt->execute([$passwordHash, $currentUser['id']]);

            logActivity(
                $pdo,
                $currentUser['id'],
                'password_change',
                'user',
                $currentUser['id'],
                'User changed their account password.'
            );

            $_SESSION['password_change_flash'] = [
                'message' => 'Your password was changed successfully.',
                'type' => 'success'
            ];
            unset($_SESSION['csrf_token']);
            session_regenerate_id(true);
            header('Location: changepass.php');
            exit();
        } catch (Throwable $e) {
            error_log('Account password change failed for user ' . $currentUser['id'] . ': ' . $e->getMessage());
            $message = 'The password could not be updated. Please try again.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3924">
    <title>Change Account Password - CVSU Naic</title>
    <link rel="icon" type="image/png" href="img/cvsu-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <?php include __DIR__ . '/includes/theme.php'; ?>
    <style>
        :root {
            --reset-green: #0c3924;
            --reset-deep: #082b1a;
            --reset-accent: #d5ad49;
            --reset-ink: #183126;
            --reset-muted: #6b7c72;
            --reset-line: #e1e9e3;
        }
        * { box-sizing: border-box; }
        body.password-reset-page {
            min-height: 100vh;
            margin: 0;
            padding: clamp(18px, 4vw, 52px);
            display: grid;
            place-items: center;
            color: var(--reset-ink);
            font-family: "DM Sans", sans-serif;
            background:
                radial-gradient(ellipse at 10% 8%, rgba(210, 229, 215, .72), transparent 32%),
                radial-gradient(ellipse at 92% 88%, rgba(216, 231, 219, .58), transparent 30%),
                #f4f7f4;
        }
        .reset-shell {
            width: min(100%, 1020px);
            min-height: 590px;
            display: grid;
            grid-template-columns: .92fr 1.08fr;
            overflow: hidden;
            border: 1px solid rgba(12, 57, 36, .11);
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(12, 57, 36, .13);
        }
        .reset-intro {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: clamp(30px, 5vw, 54px);
            color: #fff;
            background:
                radial-gradient(circle at 100% 0%, rgba(255,255,255,.10), transparent 31%),
                linear-gradient(145deg, var(--reset-green), var(--reset-deep));
        }
        .reset-intro::after {
            position: absolute;
            right: -115px;
            bottom: -145px;
            width: 350px;
            height: 350px;
            border: 1px solid rgba(255,255,255,.11);
            border-radius: 50%;
            box-shadow: 0 0 0 28px rgba(255,255,255,.035), 0 0 0 58px rgba(255,255,255,.025);
            content: "";
            pointer-events: none;
        }
        .brand-lockup, .intro-copy, .intro-foot { position: relative; z-index: 1; }
        .brand-lockup { display: flex; align-items: center; gap: 12px; }
        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.23);
            border-radius: 13px;
            color: #ffe19a;
            background: rgba(255,255,255,.09);
            font-size: 23px;
        }
        .brand-name { font: 800 15px/1.2 "Manrope", sans-serif; letter-spacing: -.02em; }
        .brand-location { margin-top: 4px; color: rgba(255,255,255,.66); font-size: 11px; letter-spacing: .09em; text-transform: uppercase; }
        .intro-copy { max-width: 390px; margin: 66px 0; }
        .intro-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #f1d68d;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .15em;
            text-transform: uppercase;
        }
        .password-reset-page .intro-copy h1 {
            margin: 0;
            color: #fff !important;
            font: 700 clamp(30px, 4vw, 43px)/1.12 "Manrope", sans-serif !important;
            letter-spacing: -.045em !important;
        }
        .intro-copy p { margin: 18px 0 0; color: rgba(255,255,255,.72); font-size: 15px; line-height: 1.75; }
        .intro-foot { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,.6); font-size: 12px; }
        .intro-foot i { color: #e7c56c; font-size: 17px; }
        .reset-panel { align-self: center; padding: clamp(30px, 6vw, 68px); }
        .panel-heading { margin-bottom: 28px; }
        .panel-heading .eyebrow { margin-bottom: 10px; color: #3e7653; font-size: 11px; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; }
        .password-reset-page .panel-heading h2 { margin: 0; color: var(--reset-ink) !important; font: 700 27px/1.25 "Manrope", sans-serif !important; letter-spacing: -.035em !important; }
        .panel-heading p { margin: 9px 0 0; color: var(--reset-muted); font-size: 14px; line-height: 1.6; }
        .form-field { margin-bottom: 19px; }
        .form-field label { display: block; margin-bottom: 8px; color: #344b3e; font-size: 13px; font-weight: 650; }
        .input-wrap { position: relative; }
        .input-wrap > i {
            position: absolute;
            top: 50%;
            left: 14px;
            color: #71877a;
            font-size: 18px;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .input-wrap input {
            width: 100%;
            height: 48px;
            padding: 0 46px 0 43px;
            border: 1px solid var(--reset-line);
            border-radius: 10px;
            outline: none;
            color: var(--reset-ink);
            background: #fbfdfb;
            font: inherit;
            font-size: 14px;
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
        }
        .input-wrap input:focus { border-color: #58866a; background: #fff; box-shadow: 0 0 0 3px rgba(35, 104, 62, .10); }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 8px;
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 8px;
            color: #687c70;
            background: transparent;
            cursor: pointer;
            transform: translateY(-50%);
        }
        .toggle-password:hover { color: var(--reset-green); background: #edf4ef; }
        .password-hint { margin: 7px 0 0; color: #7b8b80; font-size: 11px; }
        .reset-message { display: flex; gap: 10px; margin: 0 0 20px; padding: 12px 14px; border: 1px solid; border-radius: 10px; font-size: 13px; line-height: 1.5; }
        .reset-message i { flex: 0 0 auto; margin-top: 1px; font-size: 17px; }
        .reset-message.success { border-color: #cfe5d5; color: #205b35; background: #f0f8f2; }
        .reset-message.error { border-color: #eed8d3; color: #8c382d; background: #fff5f3; }
        .reset-submit {
            width: 100%;
            min-height: 49px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 25px;
            border: 0;
            border-radius: 10px;
            color: #fff;
            background: var(--reset-green);
            box-shadow: 0 7px 16px rgba(12, 57, 36, .16);
            font: 650 14px "DM Sans", sans-serif;
            cursor: pointer;
            transition: background .16s ease, transform .16s ease, box-shadow .16s ease;
        }
        .reset-submit:hover { background: #145436; box-shadow: 0 9px 20px rgba(12, 57, 36, .22); transform: translateY(-1px); }
        .reset-submit:focus-visible, .toggle-password:focus-visible { outline: 3px solid rgba(213, 173, 73, .65); outline-offset: 2px; }
        .back-link { display: flex; justify-content: center; margin-top: 21px; color: #557361; font-size: 13px; text-decoration: none; }
        .back-link:hover { color: var(--reset-green); text-decoration: underline; }
        @media (max-width: 760px) {
            body.password-reset-page { display: flex; align-items: flex-start; justify-content: center; padding: 18px; }
            .reset-shell { max-width: 520px; min-height: auto; grid-template-columns: 1fr; border-radius: 18px; }
            .reset-intro { min-height: 225px; padding: 25px 28px; }
            .intro-copy { margin: 38px 0 5px; }
            .password-reset-page .intro-copy h1 { max-width: 340px; font-size: 31px !important; }
            .intro-copy p, .intro-foot { display: none; }
            .password-reset-page .panel-heading h2 { font-size: 24px !important; }
            .reset-panel { padding: 30px 28px 32px; }
        }
        @media (max-width: 390px) {
            body.password-reset-page { padding: 10px; }
            .reset-panel { padding: 25px 20px; }
            .reset-intro { padding: 22px 20px; }
        }
    </style>
</head>
<body class="password-reset-page">
    <main class="reset-shell">
        <section class="reset-intro" aria-label="Account security information">
            <div class="brand-lockup">
                <span class="brand-mark" aria-hidden="true"><i class="bx bx-shield-quarter"></i></span>
                <div>
                    <div class="brand-name">ODCI Document Hub</div>
                    <div class="brand-location">CvSU Naic Campus</div>
                </div>
            </div>
            <div class="intro-copy">
                <div class="intro-eyebrow"><i class="bx bx-lock-alt" aria-hidden="true"></i> Account security</div>
                <h1>Your account, secured by you.</h1>
                <p>Choose a strong password to help keep your documents and account information protected.</p>
            </div>
            <div class="intro-foot"><i class="bx bx-check-shield" aria-hidden="true"></i> Only you can change your password</div>
        </section>

        <section class="reset-panel">
            <header class="panel-heading">
                <div class="eyebrow">Personal settings</div>
                <h2>Change password</h2>
                <p>Verify your current password, then enter and confirm your new one.</p>
            </header>

            <?php if ($message !== ''): ?>
                <div class="reset-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="status">
                    <i class="bx <?= $messageType === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="changepass.php" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-field">
                    <label for="current-password">Current password</label>
                    <div class="input-wrap">
                        <i class="bx bx-lock-alt" aria-hidden="true"></i>
                        <input id="current-password" type="password" name="current_password" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" aria-label="Show current password" aria-controls="current-password"><i class="bx bx-show" aria-hidden="true"></i></button>
                    </div>
                </div>

                <div class="form-field">
                    <label for="new-password">New password</label>
                    <div class="input-wrap">
                        <i class="bx bx-key" aria-hidden="true"></i>
                        <input id="new-password" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        <button type="button" class="toggle-password" aria-label="Show new password" aria-controls="new-password"><i class="bx bx-show" aria-hidden="true"></i></button>
                    </div>
                    <p class="password-hint">Use 8 to 72 bytes. Avoid reusing your current password.</p>
                </div>

                <div class="form-field">
                    <label for="confirm-password">Confirm new password</label>
                    <div class="input-wrap">
                        <i class="bx bx-check-shield" aria-hidden="true"></i>
                        <input id="confirm-password" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        <button type="button" class="toggle-password" aria-label="Show password confirmation" aria-controls="confirm-password"><i class="bx bx-show" aria-hidden="true"></i></button>
                    </div>
                </div>

                <button class="reset-submit" type="submit"><i class="bx bx-lock-open-alt" aria-hidden="true"></i> Update password</button>
                <a class="back-link" href="<?= htmlspecialchars(getDashboardUrl($currentUser['role']), ENT_QUOTES, 'UTF-8') ?>"><i class="bx bx-arrow-back" aria-hidden="true"></i>&nbsp; Return to dashboard</a>
            </form>
        </section>
    </main>
    <script>
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.getAttribute('aria-controls'));
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                button.querySelector('i').className = `bx ${visible ? 'bx-hide' : 'bx-show'}`;
            });
        });

        document.querySelector('form').addEventListener('submit', event => {
            const password = document.getElementById('new-password').value;
            const confirmation = document.getElementById('confirm-password').value;
            if (password !== confirmation) {
                event.preventDefault();
                document.getElementById('confirm-password').setCustomValidity('Passwords do not match.');
                document.getElementById('confirm-password').reportValidity();
            }
        });
        document.getElementById('confirm-password').addEventListener('input', event => event.target.setCustomValidity(''));
    </script>
</body>
</html>
