<?php
// Start Session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$unread_notifications_count = 0;
$user_notifications = [];
$user_name = '';

if (!empty($_SESSION['user_id'])) {
    if (!isset($pdo)) {
        try {
            require_once __DIR__ . '/../api/config.php';
        } catch (Exception $e) {
            // Ignore
        }
    }
    
    if (isset($pdo)) {
        try {
            // Fetch name
            if (empty($_SESSION['user_name'])) {
                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['user_name'] = $stmt->fetchColumn() ?: 'User';
            }
            $user_name = $_SESSION['user_name'];

            // Fetch notifications
            $notif_stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $notif_stmt->execute([$_SESSION['user_id']]);
            $user_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($user_notifications as $n) {
                if (empty($n['is_read'])) {
                    $unread_notifications_count++;
                }
            }
        } catch (PDOException $e) {
            // Ignore
        }
    }
}
?>

<nav id="main-navbar" class="fixed top-0 left-0 w-full z-[100] flex items-center justify-between px-6 md:px-12 h-20 bg-[#3B111B] border-b border-solid border-[#C8A25A]/10 font-sans">
    <!-- Logo Brand -->
    <a href="index.html" class="flex items-center gap-3 no-underline">
        <img src="assets/images/medusaa2(onlylogo).png" alt="Logo" class="w-10 h-10 object-contain brightness-110">
        <div class="font-serif text-[1.15rem] font-semibold text-[#C8A25A] tracking-[2px] uppercase leading-tight">
            LA-MEDUSAA
            <small class="block text-[0.58rem] tracking-[4px] font-normal text-[#C8A25A]/65">Bar & Lounge</small>
        </div>
    </a>

    <!-- Desktop Navigation Items (Hidden on mobile/tablet) -->
    <ul class="hidden lg:flex items-center gap-7 list-none m-0 p-0">
        <li><a href="index.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Home</a></li>
        <li><a href="about.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">About</a></li>
        <li><a href="menutest.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Menu</a></li>
        <li><a href="gallery.php" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Gallery</a></li>
        <li><a href="book-table-test.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Book Table</a></li>
        <li><a href="career.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Careers</a></li>
        
        <?php if (!empty($_SESSION['user_id'])): ?>
            <li><a href="my-orders.php" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">My Orders</a></li>
        <?php else: ?>
            <li><a href="contact.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Contact</a></li>
            <li><a href="login.html" class="nav-link-item text-[0.75rem] font-medium tracking-[2px] uppercase text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors no-underline">Login</a></li>
        <?php endif; ?>
    </ul>

    <!-- Right Side Controls (Cart, Notifications, Profile, Reservation Button, Mobile Toggle) -->
    <div class="flex items-center gap-4">
        
        
<!-- Reservation Button (Desktop/Tablet) -->
        <a href="book-table-test.html" class="hidden md:block text-[0.72rem] font-medium tracking-[2px] uppercase text-[#C8A25A] border border-solid px-[18px] py-2 hover:bg-[#C8A25A]/10 transition-all no-underline" style="border-color: #C8A25A !important;">Reserve a Table</a>

        <?php if (!empty($_SESSION['user_id'])): ?>
            <!-- Notification Bell -->
            <div class="relative" id="nav-notif-bell">
                <button class="text-[#F8EACE]/75 hover:text-[#C8A25A] transition-colors relative bg-transparent border-none cursor-pointer text-lg p-1 outline-none flex items-center justify-center">
                    <i class="fa-regular fa-bell <?php echo ($unread_notifications_count > 0) ? 'bell-ringing' : ''; ?>" id="navNotifBellIcon"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span id="navNotifRedDot" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full border border-[#3B111B]"></span>
                    <?php endif; ?>
                </button>
                <!-- Notification Dropdown -->
                <div id="nav-notif-dropdown" class="hidden absolute right-0 mt-[12px] w-80 bg-[#F8F3EB] border border-solid border-[#3B111B]/10 rounded-2xl shadow-[0_10px_30px_rgba(59,17,27,0.1)] overflow-hidden z-[110]">
                    <div class="px-4 py-3 border-b border-[#3B111B]/10 text-xs font-serif font-semibold text-[#3B111B] tracking-wider uppercase">Notifications</div>
                    <div class="max-h-72 overflow-y-auto font-sans">
                        <?php if (empty($user_notifications)): ?>
                            <div class="px-4 py-6 text-center text-xs text-[#3B111B]/60">No new notifications</div>
                        <?php else: ?>
                            <?php foreach ($user_notifications as $notif): ?>
                                <div class="px-4 py-3 border-b border-[#3B111B]/10 hover:bg-[#3B111B]/5 transition-colors duration-200">
                                    <div class="text-xs font-medium text-[#3B111B]"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="text-[0.7rem] text-[#3B111B]/80 mt-1"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    <div class="text-[0.6rem] text-[#3B111B]/60 mt-1.5"><?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="profile.php?tab=notifications" class="block text-center py-2.5 border-t border-[#3B111B]/10 text-xs font-semibold text-[#C8A25A] hover:text-[#b8973a] transition-colors duration-200 no-underline uppercase tracking-wider">View All Notifications</a>
                </div>
            </div>

            <!-- Cart Icon and Badge -->
            <div class="relative" id="nav-cart-btn">
                <a href="carttest.html" class="text-[#C8A25A] hover:text-white transition-colors relative no-underline p-1 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" class="w-[18px] h-[18px]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                    <span id="cartCount" class="absolute -top-2 -right-2 bg-[#C8A25A] text-[#3B111B] text-[0.55rem] font-bold rounded-full w-4 h-4 flex items-center justify-center hidden">0</span>
                </a>
            </div>

            <!-- Profile Dropdown -->
            <div class="relative" id="nav-profile-menu">
                <button class="flex items-center justify-center text-lg text-[#C8A25A] hover:text-white transition-colors bg-transparent border-none cursor-pointer p-1 outline-none">
                    <i class="fa-regular fa-user"></i>
                </button>
                <!-- Dropdown List -->
                <div id="nav-profile-dropdown" class="hidden absolute right-0 mt-[12px] w-max min-w-[150px] bg-[#F8F3EB] border border-solid border-[#3B111B]/10 rounded-2xl shadow-[0_10px_30px_rgba(59,17,27,0.1)] overflow-hidden z-[110] p-2 flex flex-col gap-1">
                    <a href="profile.php?tab=profile" class="block px-4 py-2.5 text-xs font-semibold tracking-wider text-[#3B111B] hover:bg-[#3B111B]/5 rounded-xl transition-colors duration-200 no-underline uppercase">My Profile</a>
                    <a href="profile.php?tab=reservations" class="block px-4 py-2.5 text-xs font-semibold tracking-wider text-[#3B111B] hover:bg-[#3B111B]/5 rounded-xl transition-colors duration-200 no-underline uppercase">Reservations</a>
                    <a href="profile.php?tab=loyalty" class="block px-4 py-2.5 text-xs font-semibold tracking-wider text-[#3B111B] hover:bg-[#3B111B]/5 rounded-xl transition-colors duration-200 no-underline uppercase">Rewards</a>
                    <a href="settings.php" class="block px-4 py-2.5 text-xs font-semibold tracking-wider text-[#3B111B] hover:bg-[#3B111B]/5 rounded-xl transition-colors duration-200 no-underline uppercase">Settings</a>
                    <a href="api/logout.php" class="block px-4 py-2.5 text-xs font-semibold tracking-wider text-red-600 hover:bg-[#3B111B]/5 rounded-xl transition-colors duration-200 no-underline uppercase">Logout</a>
                </div>
            </div>
        <?php endif; ?>

<!-- Mobile Hamburger Toggle -->
        <button id="nav-mobile-toggle" class="lg:hidden text-[#F8EACE] bg-transparent border-none cursor-pointer text-xl p-1 outline-none">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</nav>

<!-- Mobile Drawer -->
<div id="nav-mobile-drawer" class="hidden fixed inset-0 top-20 z-[90] bg-[#3B111B]/95 backdrop-blur-md flex flex-col items-center justify-start py-8 gap-6 overflow-y-auto font-sans">
    <a href="index.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Home</a>
    <a href="about.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">About</a>
    <a href="menutest.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Menu</a>
    <a href="gallery.php" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Gallery</a>
    <a href="book-table-test.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Book Table</a>
    <a href="career.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Careers</a>
    
    <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="my-orders.php" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">My Orders</a>
        <a href="carttest.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline flex items-center gap-1.5">Cart <span id="cartCountMobileDrawer" class="px-1.5 py-0.5 text-[0.65rem] bg-[#C8A25A] text-[#3B111B] rounded-full font-bold hidden">0</span></a>
        <a href="profile.php?tab=profile" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">My Profile</a>
        <a href="api/logout.php" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-red-500 hover:text-red-600 transition-colors no-underline">Logout</a>
    <?php else: ?>
        <a href="contact.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Contact</a>
        <a href="login.html" class="nav-link-item text-sm font-semibold tracking-[2px] uppercase text-[#F8EACE] hover:text-[#C8A25A] transition-colors no-underline">Login</a>
    <?php endif; ?>
    
    <a href="book-table-test.html" class="text-xs font-semibold tracking-[2px] uppercase text-[#C8A25A] border border-solid px-6 py-2.5 hover:bg-[#C8A25A]/10 transition-all no-underline mt-4" style="border-color: #C8A25A !important;">Reserve a Table</a>
</div>

<!-- Spacer to push down content so it doesn't get hidden under the fixed navbar -->
<div id="navbar-spacer" class="h-20 w-full pointer-events-none"></div>
