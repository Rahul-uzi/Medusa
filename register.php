<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/otp_helper.php';

// If already active/logged in, redirect to index
if (!empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$error      = '';
$goToStep2  = false;   // tells JS to open step 2 on page-reload after PHP error
$form_data  = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'phone'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $fullName  = trim($firstName . ' ' . $lastName);
    $email     = trim($_POST['email']    ?? '');
    $phone     = trim($_POST['phone']    ?? '');
    $password  = $_POST['password']         ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    $form_data = [
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'phone'      => $phone,
    ];

    if (empty($firstName) || empty($phone) || empty($password) || empty($confirmPw)) {
        $error = 'All required fields must be filled.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address format.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Mobile number must be exactly 10 digits.';
    } elseif (strlen($password) < 6) {
        $error     = 'Password must be at least 6 characters.';
        $goToStep2 = true;
    } elseif ($password !== $confirmPw) {
        $error     = 'Passwords do not match. Please try again.';
        $goToStep2 = true;
    } else {
        $goToStep2 = true;   // keep step 2 open on DB errors
        try {
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existing_user = $stmt->fetch();

                if ($existing_user) {
                    $error     = 'An account with this email already exists. Please login.';
                    $goToStep2 = false;
                }
            }

            if (empty($error)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->execute([$phone]);
                if ($stmt->fetch()) {
                    $error     = 'An account with this mobile number already exists.';
                    $goToStep2 = false;
                }
            }

            if (empty($error)) {
                $emailOtp       = !empty($email) ? generateOTP() : NULL;
                $phoneOtp       = generateOTP();
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $otpExpiresAt   = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                $dbEmail = !empty($email) ? $email : NULL;
                $isEmailVerified = empty($email) ? 1 : 0; // if no email, mark verified to bypass

                $ins = $pdo->prepare("
                    INSERT INTO users
                    (full_name, email, phone, password, address, city, state, pincode,
                     email_otp, phone_otp, otp_expires_at,
                     is_email_verified, is_phone_verified, role)
                    VALUES (?, ?, ?, ?, '', '', '', '', ?, ?, ?, ?, 0, 'customer')
                ");
                $ins->execute([$fullName, $dbEmail, $phone, $hashedPassword,
                               $emailOtp, $phoneOtp, $otpExpiresAt, $isEmailVerified]);

                $newUserId = $pdo->lastInsertId();

                // Initialize loyalty reward points row for new user
                $pdo->prepare("INSERT IGNORE INTO reward_points (user_id, points_earned, points_redeemed, points_deducted, current_balance) VALUES (?, 0, 0, 0, 0)")->execute([$newUserId]);

                $_SESSION['otp_verify_user_id'] = $newUserId;
                $_SESSION['last_otp_sent_time']  = time();

                if (!empty($email)) {
                    sendOTPEmail($email, $fullName, $emailOtp);
                }
                sendOTPSMS($phone, $phoneOtp);

                header('Location: verify_otp.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Medusa – Create your account">
    <title>LA-MEDUSAA – Register</title>
    <script src="assets/js/theme-toggle.js"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script>
    const originalWarn = console.warn;
    console.warn = function(...args) {
        if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com should not be used in production')) return;
        originalWarn.apply(console, args);
    };
</script>
<script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              cream: '#f0ebe1',
              'cream-dark': '#e6dfd3',
              maroon: '#5c1a1a',
              'maroon-dark': '#3e0f0f',
              gold: '#b8973a',
              'gold-light': '#d4af5a',
              'text-dark': '#2a1a0e',
              'text-mid': '#5a4533',
              'text-muted': '#8a7260',
              border: 'rgba(90, 69, 51, 0.15)',
            },
            fontFamily: {
              serif: ['"Cormorant Garamond"', 'Georgia', 'serif'],
              sans: ['Jost', 'sans-serif'],
            },
            keyframes: {
              shake: {
                '0%, 100%': { transform: 'translateX(0)' },
                '18%': { transform: 'translateX(-6px)' },
                '36%': { transform: 'translateX(6px)' },
                '54%': { transform: 'translateX(-4px)' },
                '72%': { transform: 'translateX(4px)' },
                '90%': { transform: 'translateX(-2px)' },
              }
            },
            animation: {
              shake: 'shake 0.38s ease',
            }
          }
        }
      }
    </script>
    <style>
        /* Custom styles that are harder to replicate with pure tailwind config without plugins */
        body {
            background-image: url('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1920&h=1080&fit=crop&q=85');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .input-field:-webkit-autofill,
        .input-field:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #f0ebe1 inset;
            -webkit-text-fill-color: #2a1a0e;
        }
    </style>

    <!-- Navbar Performance Optimization Links -->
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="font-sans min-h-screen flex flex-col relative overflow-x-hidden">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-[#0f0703]/65 z-0 pointer-events-none"></div>

    <!-- TOP NAV -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <script src="assets/js/navbar.js" defer></script>

    <!-- MAIN LAYOUT -->
    <div class="relative z-10 flex-1 flex flex-col min-h-screen">
        <main class="flex-1 flex items-center justify-center px-[8%] pt-[108px] pb-16 min-h-screen">
            
            <!-- RIGHT PANEL (FLOATING CARD) -->
            <div id="card" class="bg-cream w-full max-w-[580px] rounded-2xl shadow-[0_25px_50px_rgba(0,0,0,0.5)] px-10 py-[30px] flex flex-col items-center overflow-hidden transition-all duration-300">
                
                <!-- <div class="text-center mb-7 w-full">
                    <i class="fas fa-crown text-[2.2rem] text-text-dark mb-2.5 block"></i>
                    <span class="font-serif text-[1.2rem] font-semibold tracking-[4px] uppercase text-text-dark block">Medusa Club</span>
                    <span class="text-[0.65rem] tracking-[5px] text-text-muted font-medium uppercase block mt-1">Join the Elite</span>
                </div> -->

                <h1 class="font-serif text-[2.2rem] font-normal text-text-dark text-center mb-2">Create Account</h1>
                <div class="text-center mb-[26px]">
                    <svg width="40" height="12" viewBox="0 0 40 12" fill="currentColor" class="text-gold inline-block">
                        <path d="M20 0L24 6L20 12L16 6L20 0Z" />
                        <path d="M8 4L10 6L8 8L6 6L8 4Z" />
                        <path d="M32 4L34 6L32 8L30 6L32 4Z" />
                        <rect x="0" y="5.5" width="4" height="1" />
                        <rect x="36" y="5.5" width="4" height="1" />
                    </svg>
                </div>

                <!-- Error banner (shown on PHP validation fail) -->
                <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-500 border border-red-200 px-3 py-2.5 rounded-lg text-sm mb-4 text-center flex items-center gap-2 justify-center w-full">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Step progress dots -->
                <div class="flex justify-center items-center gap-2 mb-6 w-full">
                    <span id="lbl1" class="text-[0.7rem] font-semibold tracking-[1.2px] uppercase transition-colors text-text-muted">Info</span>
                    <div id="dot1" class="h-2 rounded-full transition-all duration-400 ease-in-out bg-gold w-[22px] transform scale-y-110"></div>
                    <div id="dot2" class="w-2 h-2 rounded-full transition-all duration-400 ease-in-out bg-text-muted/30"></div>
                    <span id="lbl2" class="text-[0.7rem] font-semibold tracking-[1.2px] uppercase transition-colors text-text-muted/50">Password</span>
                </div>

                <!-- ══ Single form – two sliding panels ══ -->
                <form action="register.php" method="POST" id="regForm" class="w-full relative overflow-hidden" novalidate>
                    <div id="track" class="flex w-[200%] transition-transform duration-[480ms] ease-[cubic-bezier(0.4,0,0.2,1)] <?php echo $goToStep2 ? '-translate-x-1/2' : ''; ?>">
                        
                        <!-- ══ STEP 1 — Personal Info ══ -->
                        <div class="w-1/2 shrink-0 px-1" id="step1">

                            <div class="flex items-center gap-2.5 mb-4 w-full">
                                <div class="flex-1 h-px bg-gold/20"></div>
                                <span class="text-gold text-[0.7rem] font-bold tracking-[1.8px] uppercase whitespace-nowrap">Personal Info</span>
                                <div class="flex-1 h-px bg-gold/20"></div>
                            </div>

                            <div class="flex gap-3 w-full">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-[0.72rem] font-medium tracking-[0.5px] text-text-mid mb-[7px]">FIRST NAME *</label>
                                    <div id="w-first" class="flex items-center border border-border rounded bg-transparent px-3.5 py-3 mb-4 transition-all duration-200 focus-within:border-gold/55 focus-within:ring-2 focus-within:ring-gold/10">
                                        <i class="fas fa-user text-text-muted mr-3 text-[0.9rem]"></i>
                                        <input type="text" id="first_name" name="first_name" class="input-field flex-1 bg-transparent border-none outline-none font-sans text-[0.9rem] text-text-dark placeholder-text-muted/70" placeholder="John" value="<?php echo htmlspecialchars($form_data['first_name']); ?>" autocomplete="given-name" required>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block text-[0.72rem] font-medium tracking-[0.5px] text-text-mid mb-[7px]">LAST NAME</label>
                                    <div id="w-last" class="flex items-center border border-border rounded bg-transparent px-3.5 py-3 mb-4 transition-all duration-200 focus-within:border-gold/55 focus-within:ring-2 focus-within:ring-gold/10">
                                        <input type="text" id="last_name" name="last_name" class="input-field flex-1 bg-transparent border-none outline-none font-sans text-[0.9rem] text-text-dark placeholder-text-muted/70" placeholder="Doe (Optional)" value="<?php echo htmlspecialchars($form_data['last_name']); ?>" autocomplete="family-name">
                                    </div>
                                </div>
                            </div>

                            <div class="w-full">
                                <label class="block text-[0.72rem] font-medium tracking-[0.5px] text-text-mid mb-[7px]">EMAIL ADDRESS</label>
                                <div id="w-email" class="flex items-center border border-border rounded bg-transparent px-3.5 py-3 mb-4 transition-all duration-200 focus-within:border-gold/55 focus-within:ring-2 focus-within:ring-gold/10">
                                    <i class="fas fa-envelope text-text-muted mr-3 text-[0.9rem]"></i>
                                    <input type="email" id="email" name="email" class="input-field flex-1 bg-transparent border-none outline-none font-sans text-[0.9rem] text-text-dark placeholder-text-muted/70" placeholder="john@example.com (Optional)" value="<?php echo htmlspecialchars($form_data['email']); ?>" autocomplete="email">
                                </div>
                            </div>

                            <div class="w-full">
                                <label class="block text-[0.72rem] font-medium tracking-[0.5px] text-text-mid mb-[7px]">MOBILE NUMBER *</label>
                                <div id="w-phone" class="flex items-center border border-border rounded bg-transparent px-3.5 py-3 mb-4 transition-all duration-200 focus-within:border-gold/55 focus-within:ring-2 focus-within:ring-gold/10">
                                    <i class="fas fa-phone text-text-muted mr-3 text-[0.9rem]"></i>
                                    <input type="tel" id="phone" name="phone" class="input-field flex-1 bg-transparent border-none outline-none font-sans text-[0.9rem] text-text-dark placeholder-text-muted/70" placeholder="10-digit number" value="<?php echo htmlspecialchars($form_data['phone']); ?>" autocomplete="tel" maxlength="10">
                                </div>
                            </div>

                            <button type="button" id="nextBtn" class="w-full bg-maroon text-white border-none py-3.5 rounded font-sans text-[0.85rem] font-semibold tracking-[2px] uppercase cursor-pointer transition-all duration-250 hover:bg-maroon-dark hover:-translate-y-px active:translate-y-0 mt-2 mb-5">
                                Continue <i class="fas fa-arrow-right ml-1"></i>
                            </button>

                            <div class="text-center text-[0.83rem] text-text-mid">
                                Already a Member? <a href="login.html" class="text-maroon font-semibold no-underline ml-1 transition-colors hover:text-gold">Login Now</a>
                            </div>

                        </div>
                        <!-- /step1 -->

                        <!-- ══ STEP 2 — Set Password ══ -->
                        <div class="w-1/2 shrink-0 px-1" id="step2">

                            <div class="flex items-center gap-2.5 mb-4 w-full">
                                <div class="flex-1 h-px bg-gold/20"></div>
                                <span class="text-gold text-[0.7rem] font-bold tracking-[1.8px] uppercase whitespace-nowrap">Set Password</span>
                                <div class="flex-1 h-px bg-gold/20"></div>
                            </div>

                            <div class="w-full">
                                <label class="block text-[0.72rem] font-medium tracking-[0.5px] text-text-mid mb-[7px]">NEW PASSWORD *</label>
                                <div id="w-pw" class="flex items-center border border-border rounded bg-transparent px-3.5 py-3 mb-[18px] transition-all duration-200 focus-within:border-gold/55 focus-within:ring-2 focus-within:ring-gold/10">
                                    <i class="fas fa-lock text-text-muted mr-3 text-[0.9rem]"></i>
                                    <input type="password" id="password" name="password" class="input-field flex-1 bg-transparent border-none outline-none font-sans text-[0.9rem] text-text-dark placeholder-text-muted/70" placeholder="Enter new password" autocomplete="new-password">
                                    <button type="button" id="toggle-pw" class="bg-none border-none cursor-pointer text-text-muted text-[0.9rem] px-1 hover:text-text-dark transition-colors" aria-label="Toggle password">
                                        <i class="fas fa-eye" id="eye-pw"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Strength bar -->
                            <div class="flex gap-1 -mt-1.5 mb-3 w-full">
                                <div id="seg1" class="flex-1 h-[3px] rounded-sm bg-border transition-colors duration-350"></div>
                                <div id="seg2" class="flex-1 h-[3px] rounded-sm bg-border transition-colors duration-350"></div>
                                <div id="seg3" class="flex-1 h-[3px] rounded-sm bg-border transition-colors duration-350"></div>
                                <div id="seg4" class="flex-1 h-[3px] rounded-sm bg-border transition-colors duration-350"></div>
                            </div>
                            <div id="stext" class="text-[0.7rem] text-right -mt-2 mb-3.5 text-text-muted tracking-[0.3px] min-h-[14px] w-full"></div>

                            <div class="w-full">
                                <label class="block text-[0.72rem] font-medium tracking-[0.5px] text-text-mid mb-[7px]">CONFIRM PASSWORD *</label>
                                <div id="w-cpw" class="flex items-center border border-border rounded bg-transparent px-3.5 py-3 mb-4 transition-all duration-200 focus-within:border-gold/55 focus-within:ring-2 focus-within:ring-gold/10">
                                    <i class="fas fa-lock text-text-muted mr-3 text-[0.9rem]"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" class="input-field flex-1 bg-transparent border-none outline-none font-sans text-[0.9rem] text-text-dark placeholder-text-muted/70" placeholder="Confirm new password" autocomplete="new-password">
                                    <button type="button" id="toggle-cpw" class="bg-none border-none cursor-pointer text-text-muted text-[0.9rem] px-1 hover:text-text-dark transition-colors" aria-label="Toggle confirm">
                                        <i class="fas fa-eye" id="eye-cpw"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-2.5 mt-3 mb-5 w-full">
                                <button type="button" id="backBtn" class="flex-1 bg-transparent text-text-mid border border-border py-3.5 rounded font-sans text-[0.85rem] font-semibold tracking-[1px] uppercase cursor-pointer transition-all duration-250 hover:bg-black/5 hover:text-text-dark hover:-translate-y-px active:translate-y-0">
                                    <i class="fas fa-arrow-left mr-1"></i> Back
                                </button>
                                <button type="submit" id="submitBtn" class="flex-1 bg-maroon text-white border-none py-3.5 rounded font-sans text-[0.85rem] font-semibold tracking-[1px] uppercase cursor-pointer transition-all duration-250 hover:bg-maroon-dark hover:-translate-y-px active:translate-y-0">
                                    Register <i class="fas fa-check ml-1"></i>
                                </button>
                            </div>

                            <div class="text-center text-[0.83rem] text-text-mid">
                                Already a Member? <a href="login.html" class="text-maroon font-semibold no-underline ml-1 transition-colors hover:text-gold">Login Now</a>
                            </div>

                        </div>
                        <!-- /step2 -->

                    </div>
                </form>
            </div>
        </main>
    </div>

<script>
const track   = document.getElementById('track');
const dot1    = document.getElementById('dot1');
const dot2    = document.getElementById('dot2');
const lbl1    = document.getElementById('lbl1');
const lbl2    = document.getElementById('lbl2');
const card    = document.getElementById('card');

let currentStep = <?php echo $goToStep2 ? 2 : 1; ?>;

function updateDots() {
    if (currentStep === 2) {
        dot1.className = "w-2 h-2 rounded-full transition-all duration-400 ease-in-out bg-text-muted/30";
        dot2.className = "h-2 rounded-full transition-all duration-400 ease-in-out bg-gold w-[22px] transform scale-y-110";
        lbl1.className = "text-[0.7rem] font-semibold tracking-[1.2px] uppercase transition-colors text-text-muted/50";
        lbl2.className = "text-[0.7rem] font-semibold tracking-[1.2px] uppercase transition-colors text-text-muted";
    } else {
        dot2.className = "w-2 h-2 rounded-full transition-all duration-400 ease-in-out bg-text-muted/30";
        dot1.className = "h-2 rounded-full transition-all duration-400 ease-in-out bg-gold w-[22px] transform scale-y-110";
        lbl2.className = "text-[0.7rem] font-semibold tracking-[1.2px] uppercase transition-colors text-text-muted/50";
        lbl1.className = "text-[0.7rem] font-semibold tracking-[1.2px] uppercase transition-colors text-text-muted";
    }
}
updateDots();

function goToStep(n) {
    currentStep = n;
    if (n === 2) {
        track.classList.add('-translate-x-1/2');
    } else {
        track.classList.remove('-translate-x-1/2');
    }
    updateDots();
}

/* ── Step 1 validation → Next ── */
document.getElementById('nextBtn').addEventListener('click', () => {
    const first = document.getElementById('first_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    let ok = true;

    const mark = (id, bad) => {
        const el = document.getElementById(id);
        if (bad) {
            el.classList.add('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500/20');
            el.classList.remove('border-border', 'focus-within:border-gold/55', 'focus-within:ring-gold/10');
            ok = false;
        } else {
            el.classList.remove('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500/20');
            el.classList.add('border-border', 'focus-within:border-gold/55', 'focus-within:ring-gold/10');
        }
    };

    mark('w-first', !first);
    mark('w-email', email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
    mark('w-phone', !phone || !/^[0-9]{10}$/.test(phone));

    if (!ok) {
        card.classList.remove('animate-shake');
        void card.offsetWidth; // trigger reflow
        card.classList.add('animate-shake');
        return;
    }

    goToStep(2);
    setTimeout(() => document.getElementById('password').focus(), 480);
});

/* ── Back button ── */
document.getElementById('backBtn').addEventListener('click', () => goToStep(1));

/* ── Eye toggles ── */
function makeEye(btnId, inpId, icoId) {
    document.getElementById(btnId).addEventListener('click', () => {
        const inp  = document.getElementById(inpId);
        const ico  = document.getElementById(icoId);
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        ico.classList.toggle('fa-eye', !show);
        ico.classList.toggle('fa-eye-slash', show);
    });
}
makeEye('toggle-pw',  'password',         'eye-pw');
makeEye('toggle-cpw', 'confirm_password', 'eye-cpw');

/* ── Password strength ── */
const segs   = ['seg1','seg2','seg3','seg4'].map(id => document.getElementById(id));
const stext  = document.getElementById('stext');
const LEVELS = [
    { label:'Weak',   cls:'bg-red-500',   fill:1, color:'#ef4444' },
    { label:'Fair',   cls:'bg-orange-500',fill:2, color:'#f97316' },
    { label:'Good',   cls:'bg-green-500', fill:3, color:'#22c55e' },
    { label:'Strong', cls:'bg-[#b8973a]', fill:4, color:'#b8973a' },
];

function scorePassword(pw) {
    if (!pw) return -1;
    let s = 0;
    if (pw.length >= 6)  s++;
    if (pw.length >= 10) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw)) s++;
    return s;
}

document.getElementById('password').addEventListener('input', function () {
    const score = scorePassword(this.value);
    segs.forEach(s => { s.className = 'flex-1 h-[3px] rounded-sm bg-border transition-colors duration-350'; });
    if (score < 0) { stext.textContent = ''; return; }
    const lvl = LEVELS[Math.min(score, 3)];
    for (let i = 0; i < lvl.fill; i++) {
        segs[i].classList.remove('bg-border');
        segs[i].classList.add(lvl.cls.split('-')[0]+'-'+lvl.cls.split('-')[1]+(lvl.cls.split('-')[2]?'-'+lvl.cls.split('-')[2]:''), lvl.cls);
    }
    stext.textContent  = lvl.label;
    stext.style.color  = lvl.color;
});

/* ── Confirm password live match ── */
document.getElementById('confirm_password').addEventListener('input', function () {
    const pw   = document.getElementById('password').value;
    const wrap = document.getElementById('w-cpw');
    if (this.value && this.value !== pw) {
        wrap.classList.add('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500/20');
        wrap.classList.remove('border-border', 'focus-within:border-gold/55', 'focus-within:ring-gold/10');
    } else {
        wrap.classList.remove('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500/20');
        wrap.classList.add('border-border', 'focus-within:border-gold/55', 'focus-within:ring-gold/10');
    }
});

/* ── Final submit guard ── */
document.getElementById('regForm').addEventListener('submit', function (e) {
    const pw  = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    let err = false;
    
    if (pw.length < 6) {
        const wpw = document.getElementById('w-pw');
        wpw.classList.add('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500/20');
        wpw.classList.remove('border-border', 'focus-within:border-gold/55', 'focus-within:ring-gold/10');
        err = true;
    }
    if (pw !== cpw) {
        const wcpw = document.getElementById('w-cpw');
        wcpw.classList.add('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500/20');
        wcpw.classList.remove('border-border', 'focus-within:border-gold/55', 'focus-within:ring-gold/10');
        err = true;
    }
    
    if (err) {
        e.preventDefault();
        card.classList.remove('animate-shake');
        void card.offsetWidth;
        card.classList.add('animate-shake');
    }
});
</script>
</body>
</html>
