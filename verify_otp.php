<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/otp_helper.php';

// Verify session exists
if (empty($_SESSION['otp_verify_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId = $_SESSION['otp_verify_user_id'];
$error = '';
$success = '';
$redirect = false;

if (isset($_SESSION['otp_error'])) {
    $error = $_SESSION['otp_error'];
    unset($_SESSION['otp_error']);
}
if (isset($_SESSION['otp_success'])) {
    $success = $_SESSION['otp_success'];
    unset($_SESSION['otp_success']);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        unset($_SESSION['otp_verify_user_id']);
        header('Location: register.php');
        exit;
    }

    $isEmailVerified = (int)$user['is_email_verified'] === 1;
    $isPhoneVerified = (int)$user['is_phone_verified'] === 1;

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $emailOtpInput = trim($_POST['email_otp'] ?? '');
    $phoneOtpInput = trim($_POST['phone_otp'] ?? '');

    // Check expiry
    $now = new DateTime();
    $expiresAt = new DateTime($user['otp_expires_at']);

    if ($now > $expiresAt) {
        $error = 'Verification codes have expired. Please request a new OTP.';
    } else {
        $newEmailVerified = $isEmailVerified;
        $newPhoneVerified = $isPhoneVerified;

        // Verify Email OTP if not already verified
        if (!$isEmailVerified) {
            if ($emailOtpInput === $user['email_otp']) {
                $newEmailVerified = true;
            } else {
                $error = 'Invalid Email verification code.';
            }
        }

        // Verify Phone OTP if not already verified
        if (!$isPhoneVerified) {
            if ($phoneOtpInput === $user['phone_otp']) {
                $newPhoneVerified = true;
            } else {
                // Keep pre-existing error or set new one
                if (empty($error)) {
                    $error = 'Invalid Phone verification code.';
                } else {
                    $error = 'Invalid Email and Phone verification codes.';
                }
            }
        }

        // If changes, update database
        if (empty($error) || ($newEmailVerified !== $isEmailVerified || $newPhoneVerified !== $isPhoneVerified)) {
            try {
                $update = $pdo->prepare("UPDATE users SET is_email_verified = ?, is_phone_verified = ? WHERE id = ?");
                $update->execute([$newEmailVerified ? 1 : 0, $newPhoneVerified ? 1 : 0, $userId]);

                $isEmailVerified = $newEmailVerified;
                $isPhoneVerified = $newPhoneVerified;

                // Check if fully verified
                if ($isEmailVerified && $isPhoneVerified) {
                    $activate = $pdo->prepare("UPDATE users SET email_otp = NULL, phone_otp = NULL, otp_expires_at = NULL WHERE id = ?");
                    $activate->execute([$userId]);

                    // Send welcome and account confirmation emails
                    sendWelcomeEmail($user);
                    sendConfirmationEmail($user);

                    unset($_SESSION['otp_verify_user_id']);
                    $success = 'Verification successful! Your account is now active. Redirecting to login...';
                    $redirect = true;
                }
            } catch (PDOException $e) {
                $error = 'Failed to update database: ' . $e->getMessage();
            }
        }
    }
}

// Calculate remaining resend time
$lastSent = $_SESSION['last_otp_sent_time'] ?? 0;
$timeSinceLast = time() - $lastSent;
$resendDelay = 30;
$secondsLeft = max(0, $resendDelay - $timeSinceLast);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<style id="nav-pt-style">
    #nav-page-transition {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #120307;
        z-index: 999999;
        opacity: 1;
        transition: opacity 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: all;
    }
    #nav-page-transition.nav-pt-fadeout {
        opacity: 0 !important;
        pointer-events: none !important;
    }
</style>

<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LA-MEDUSAA — Verify Code</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --burgundy: #4A1521;
            --burgundy-dark: #2D0910;
            --burgundy-light: #5C1D2B;
            --gold: #dfba86;
            --gold-light: #f3dfc1;
            --cream: #FAF6F2;
            --cream-dark: #F2EAE1;
            --text-dark: #3D0C11;
            --text-muted: #6E5D57;
            --text-light: #FAF6F2;
            --border-color: #D1C5B7;
            
            --serif: 'Cormorant Garamond', Georgia, serif;
            --sans: 'Jost', sans-serif;
            --transition: all 0.25s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--sans);
            background-color: var(--burgundy-dark);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── HEADER ── */
        .top-nav {
            background-color: var(--burgundy);
            border-bottom: 1px solid rgba(223, 186, 134, 0.15);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .nav-logo img {
            max-height: 70px;
            width: auto;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-link {
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #D6C7B3;
            text-decoration: none;
            transition: var(--transition);
        }

        .nav-link:hover {
            color: #FFFFFF;
        }

        .btn-reserve {
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-light);
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 10px 24px;
            border-radius: 4px;
            transition: var(--transition);
            background: transparent;
        }

        .btn-reserve:hover {
            border-color: var(--gold);
            color: var(--gold);
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* ── MAIN CONTENT ── */
        .main-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 24px;
            background-image: linear-gradient(rgba(0, 0, 0, 0.72), rgba(0, 0, 0, 0.8)), url('https://images.unsplash.com/photo-1578474846511-04ba529f0b88?q=80&w=1600');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .verification-card {
            background-color: var(--cream);
            color: var(--text-dark);
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            padding: 56px 48px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
            text-align: center;
            position: relative;
        }

        .card-logo-emblem {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: var(--burgundy);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .card-logo-emblem svg {
            width: 36px;
            height: 36px;
            stroke: var(--gold);
            stroke-width: 1.5;
            fill: none;
        }

        .card-title {
            font-family: var(--serif);
            font-size: 2.1rem;
            font-weight: 500;
            color: var(--burgundy);
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .gold-ornament {
            display: flex;
            justify-content: center;
            margin-bottom: 28px;
        }

        .otp-group {
            margin-bottom: 28px;
            text-align: center;
        }

        .otp-instruction {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .otp-target {
            font-family: var(--serif);
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--burgundy);
            margin-bottom: 24px;
            letter-spacing: 0.5px;
            word-break: break-all;
        }

        .otp-inputs-container {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
            direction: ltr;
        }

        .otp-box {
            width: 52px;
            height: 62px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--cream-dark);
            text-align: center;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--burgundy);
            outline: none;
            transition: var(--transition);
        }

        .otp-box::placeholder {
            color: var(--border-color);
            font-weight: 400;
        }

        .otp-box:focus {
            border-color: var(--burgundy);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(74, 21, 33, 0.08);
        }

        .hidden-validation-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }

        /* ── Resend Text & Buttons ── */
        .resend-wrapper {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        .resend-disabled .resend-txt {
            color: var(--burgundy);
            font-weight: 600;
        }

        .resend-disabled .timer-txt {
            color: var(--text-muted);
        }

        .resend-link {
            color: var(--burgundy);
            font-weight: 700;
            text-decoration: underline;
            transition: var(--transition);
        }

        .resend-link:hover {
            color: var(--gold);
        }

        .btn-verify {
            width: 100%;
            background-color: var(--burgundy);
            color: var(--cream);
            border: none;
            border-radius: 6px;
            padding: 16px 20px;
            font-family: var(--sans);
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(74, 21, 33, 0.2);
        }

        .btn-verify:hover {
            background-color: var(--burgundy-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(74, 21, 33, 0.3);
        }

        .btn-verify:active {
            transform: translateY(0);
        }

        .verified-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background-color: rgba(46, 196, 182, 0.08);
            border: 1px solid rgba(46, 196, 182, 0.25);
            border-radius: 8px;
            color: #0d8a7c;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 28px;
        }

        .verified-indicator i {
            font-size: 1.05rem;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 28px;
        }

        /* ── NOTIFICATION MESSAGES ── */
        .msg {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 0.88rem;
            margin-bottom: 28px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
            line-height: 1.4;
        }

        .msg i {
            font-size: 1.05rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .msg-error {
            background-color: #FDF2F2;
            border: 1px solid #F8B4B4;
            color: #9B1C1C;
        }

        .msg-success {
            background-color: #F3FAF5;
            border: 1px solid #DEF7EC;
            color: #03543F;
        }

        /* ── FOOTER ── */
        footer {
            background-color: var(--burgundy-dark);
            border-top: 1px solid rgba(223, 186, 134, 0.12);
            padding: 56px 0 24px;
            color: #BFA89E;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 48px;
            padding: 0 24px;
            margin-bottom: 40px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-light);
            margin-bottom: 16px;
        }

        .footer-brand {
            font-family: var(--serif);
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .footer-brand small {
            font-size: 0.65rem;
            letter-spacing: 1px;
            color: var(--gold);
            font-weight: 400;
        }

        .footer-desc {
            font-size: 0.85rem;
            line-height: 1.6;
            max-width: 240px;
        }

        .footer-col h3 {
            font-family: var(--serif);
            color: var(--gold);
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .footer-col p {
            font-size: 0.85rem;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .footer-col p strong {
            color: var(--text-light);
            font-weight: 500;
        }

        .map-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--gold);
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            margin-top: 8px;
            transition: var(--transition);
        }

        .map-link:hover {
            color: #FFFFFF;
        }

        .social-icons {
            display: flex;
            gap: 12px;
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.85rem;
        }

        .social-icons a:hover {
            border-color: var(--gold);
            color: var(--gold);
            transform: translateY(-2px);
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 24px 24px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.35);
        }

        @media (max-width: 992px) {
            .footer-container {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .main-wrapper {
                padding: 48px 16px;
            }
            .verification-card {
                padding: 40px 24px;
            }
            .card-title {
                font-size: 1.75rem;
            }
            .footer-container {
                grid-template-columns: 1fr;
                gap: 32px;
            }
        }
    </style>

    <!-- Navbar Performance Optimization Links -->
    <link rel="stylesheet" href="assets/css/components.css">
        <script>
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && typeof args[0] === "string" && args[0].includes("cdn.tailwindcss.com should not be used in production")) {
                return;
            }
            originalWarn.apply(console, args);
        };
    </script>
<script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                corePlugins: {
                    preflight: false
                },
                theme: {
                    extend: {
                        colors: {
                            gold: '#b8973a',
                            'gold-light': '#d4af5a',
                        }
                    }
                }
            };
        }
    </script>

    <!-- CRITICAL SPA PAGE TRANSITION CSS & SCRIPT -->
    <style>
        html, body { background-color: #120307; }
        #nav-page-transition {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: #120307;
            pointer-events: all;
            opacity: 1;
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #nav-page-transition.nav-pt-fadeout {
            opacity: 0;
            pointer-events: none;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var overlay = document.getElementById('nav-page-transition');
            if(overlay) {
                setTimeout(function() {
                    overlay.classList.add('nav-pt-fadeout');
                }, 100);
            }
        });
    </script>
</head>
<body>
<div id="nav-page-transition"></div>

<!-- TOP NAVIGATION -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <script src="assets/js/navbar.js" defer></script>

    <!-- MAIN WRAPPER -->
    <main class="main-wrapper">
        <div class="verification-card">
            
            <div class="card-logo-emblem">
                <svg viewBox="0 0 64 64">
                    <path d="M32 8 C38 16, 48 22, 48 32 C48 42, 38 52, 32 56 C26 52, 16 42, 16 32 C16 22, 26 16, 32 8 Z"/>
                    <path d="M32 18 C35 24, 40 28, 40 32 C40 36, 35 40, 32 44 C29 40, 24 36, 24 32 C24 28, 29 24, 32 18 Z" stroke-width="1.5"/>
                    <path d="M32 8 V56"/>
                </svg>
            </div>
            
            <h1 class="card-title">Verify Your Code</h1>
            
            <div class="gold-ornament">
                <svg width="100" height="16" viewBox="0 0 100 16" fill="none">
                    <line x1="0" y1="8" x2="40" y2="8" stroke="#C5A880" stroke-width="1"/>
                    <path d="M43,8 C43,4 47,4 50,8 C53,12 57,12 57,8 C57,4 53,4 50,8 C47,12 43,12 43,8 Z" stroke="#C5A880" stroke-width="1.2" fill="none"/>
                    <line x1="60" y1="8" x2="100" y2="8" stroke="#C5A880" stroke-width="1"/>
                </svg>
            </div>

            <!-- Error / Success Notifications -->
            <?php if (!empty($error)): ?>
                <div class="msg msg-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="msg msg-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php if ($redirect): ?>
                    <script>
                        setTimeout(() => {
                            window.location.href = 'login.html';
                        }, 3000);
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$redirect): ?>
                <form action="verify_otp.php" method="POST" id="otpForm">
                    
                    <!-- EMAIL OTP VERIFICATION GROUP -->
                    <?php if (!empty($user['email']) && !$isEmailVerified): ?>
                        <div class="otp-group" data-type="email">
                            <p class="otp-instruction">We've sent a 6-digit verification code to your email</p>
                            <p class="otp-target"><?php echo htmlspecialchars($user['email']); ?></p>
                            <div class="otp-inputs-container">
                                <input type="text" maxlength="1" class="otp-box" data-index="0" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="1" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="2" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="3" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="4" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="5" placeholder="—" required>
                            </div>
                            <input type="text" name="email_otp" id="email_otp_hidden" required pattern="[0-9]{6}" class="hidden-validation-input">
                        </div>
                    <?php elseif ($isEmailVerified): ?>
                        <input type="hidden" name="email_otp" value="******">
                        <div class="verified-indicator">
                            <i class="fas fa-circle-check"></i>
                            <span>Email address verified</span>
                        </div>
                    <?php endif; ?>

                    <!-- PHONE OTP VERIFICATION GROUP -->
                    <?php if (!$isPhoneVerified): ?>
                        <div class="otp-group" data-type="phone">
                            <p class="otp-instruction">We've sent a 6-digit verification code to</p>
                            <p class="otp-target"><?php echo htmlspecialchars($user['phone']); ?></p>
                            <div class="otp-inputs-container">
                                <input type="text" maxlength="1" class="otp-box" data-index="0" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="1" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="2" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="3" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="4" placeholder="—" required>
                                <input type="text" maxlength="1" class="otp-box" data-index="5" placeholder="—" required>
                            </div>
                            <input type="text" name="phone_otp" id="phone_otp_hidden" required pattern="[0-9]{6}" class="hidden-validation-input">
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="phone_otp" value="******">
                        <div class="verified-indicator">
                            <i class="fas fa-circle-check"></i>
                            <span>Phone number verified</span>
                        </div>
                    <?php endif; ?>

                    <!-- RESEND CODE COUNTDOWN -->
                    <div class="resend-wrapper" id="resend-wrapper">
                        Didn't receive the code?
                        <span id="resend-container-inner"></span>
                    </div>

                    <button type="submit" class="btn-verify">Verify Code</button>
                </form>
            <?php endif; ?>

            <!-- SECURITY BADGE -->
            <div class="security-badge">
                <svg class="security-shield-svg" width="16" height="18" viewBox="0 0 16 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 1L1 4.11111V8.66667C1 12.8944 3.993 16.8 8 17C12.007 16.8 15 12.8944 15 8.66667V4.11111L8 1Z" stroke="#C5A880" stroke-width="1.2" stroke-linejoin="round"/>
                    <path d="M5.5 9L7 10.5L10.5 7" stroke="#C5A880" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Your security is our priority</span>
            </div>
            
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-container">
            <div class="footer-col footer-about">
                <a href="index.html" class="footer-logo">
                    <img src="assets/images/logo.png" alt="La-Medusaa Logo" style="max-height: 90px; width: auto;">
                </a>
                <p class="footer-desc">Sahibzada Ajit Singh Nagar's premier luxury dining experience.</p>
            </div>
            <div class="footer-col">
                <h3>Contact Us</h3>
                <p><strong>Phone :</strong> +91 94272 72798</p>
                <p><strong>Email :</strong> support@medusarestaurant.com</p>
            </div>
            <div class="footer-col">
                <h3>Location</h3>
                <p>SCO 44,45, District One Market</p>
                <p>Sector 68, SAS Nagar</p>
                <p>Punjab 140308</p>
                <a href="https://www.google.com/maps/search/?api=1&query=SCO+44+45+District+One+Market+Sector+68+Sahibzada+Ajit+Singh+Nagar+Punjab+140308" target="_blank" rel="noopener" class="map-link">View on Google Maps <i class="fas fa-arrow-up-right-from-square"></i></a>
            </div>
            <div class="footer-col footer-social">
                <h3>Follow Us</h3>
                <div class="social-icons">
                    <a href="https://www.instagram.com/la_medusaa_mohali?igsh=MXVwcHA3Nm9wbXV1dQ==" aria-label="Instagram" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Globe"><i class="fas fa-globe"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 La-Medusaa Bar & Lounge. All rights reserved.</p>
        </div>
    </footer>

    <!-- AUTO-ADVANCING INPUTS & TIMER SCRIPT -->
    <script>
        document.querySelectorAll('.otp-group').forEach(group => {
            const boxes = group.querySelectorAll('.otp-box');
            const type = group.dataset.type; // 'email' or 'phone'
            const hiddenInput = document.getElementById(type + '_otp_hidden');

            boxes.forEach((box, idx) => {
                // Restrict input to numbers only
                box.addEventListener('input', e => {
                    box.value = box.value.replace(/[^0-9]/g, '');
                    updateHidden();
                    
                    if (box.value.length === 1 && idx < 5) {
                        boxes[idx + 1].focus();
                    }
                });

                // Keydown logic for Backspace
                box.addEventListener('keydown', e => {
                    if (e.key === 'Backspace') {
                        if (box.value.length === 0 && idx > 0) {
                            boxes[idx - 1].focus();
                            boxes[idx - 1].value = '';
                            updateHidden();
                        } else {
                            box.value = '';
                            updateHidden();
                        }
                    }
                });

                // Paste support
                box.addEventListener('paste', e => {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text').trim().replace(/[^0-9]/g, '');
                    if (pastedData.length > 0) {
                        for (let i = 0; i < 6; i++) {
                            if (idx + i < 6 && i < pastedData.length) {
                                boxes[idx + i].value = pastedData[i];
                            }
                        }
                        updateHidden();
                        const nextFocus = Math.min(5, idx + pastedData.length);
                        boxes[nextFocus].focus();
                    }
                });
            });

            function updateHidden() {
                let code = '';
                boxes.forEach(box => {
                    code += box.value;
                });
                hiddenInput.value = code;
            }
        });

        // Resend Timer Countdown
        (function() {
            let secondsLeft = <?php echo intval($secondsLeft); ?>;
            const container = document.getElementById('resend-container-inner');

            function formatTime(s) {
                const m = Math.floor(s / 60);
                const sec = s % 60;
                return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
            }

            function renderResend() {
                if (secondsLeft > 0) {
                    container.innerHTML = `
                        <span class="resend-disabled">
                            <span class="resend-txt">Resend Code</span> 
                            <span class="timer-txt">(${formatTime(secondsLeft)})</span>
                        </span>`;
                } else {
                    container.innerHTML = `<a href="resend_otp.php" class="resend-link">Resend Code</a>`;
                }
            }

            renderResend();

            if (secondsLeft > 0) {
                const interval = setInterval(() => {
                    secondsLeft--;
                    renderResend();
                    if (secondsLeft <= 0) {
                        clearInterval(interval);
                    }
                }, 1000);
            }
        })();
    </script>
</body>
</html>

