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
    <title>Premium Menu - La-Medusaa</title>
    <!-- Global Theme Controller -->
    <script src="assets/js/theme-toggle.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        /* ============================================================
           CUSTOMIZATION POPUP MODAL STYLES
        ============================================================ */
        .cust-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(5px);
            z-index: 9000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .cust-modal-overlay.show {
            display: flex;
        }
        .cust-modal-box {
            background: linear-gradient(150deg, var(--formal-garden) 0%, var(--formal-garden-deep) 100%);
            border: 1px solid rgba(223, 186, 134, 0.28);
            border-radius: 22px;
            width: 100%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 40px 90px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(223,186,134,0.08);
            animation: custSlideIn 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes custSlideIn {
            from { transform: scale(0.82) translateY(28px); opacity: 0; }
            to   { transform: scale(1) translateY(0); opacity: 1; }
        }

        /* ---- Header ---- */
        .cust-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.5rem 1.6rem 1.1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .cust-dish-img {
            width: 62px;
            height: 62px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid rgba(223,186,134,0.2);
            flex-shrink: 0;
        }
        .cust-dish-info { flex: 1; }
        .cust-dish-name {
            font-family: 'Playfair Display', 'Georgia', serif;
            font-size: 1.25rem;
            color: #ffffff;
            margin-bottom: 0.2rem;
            line-height: 1.3;
        }
        .cust-base-price {
            color: #dfba86;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .cust-close-btn {
            background: rgba(255,255,255,0.06);
            border: none;
            color: #a09f9f;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .cust-close-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }

        /* ---- Body / Groups ---- */
        .cust-modal-body { padding: 1.2rem 1.6rem; }

        .cust-group { margin-bottom: 1.4rem; }

        .cust-group-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #a09f9f;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge-required {
            background: rgba(255,107,107,0.13);
            color: #ff6b6b;
            font-size: 0.63rem;
            padding: 2px 8px;
            border-radius: 20px;
            border: 1px solid rgba(255,107,107,0.22);
            text-transform: uppercase;
        }
        .badge-optional {
            background: rgba(255,255,255,0.05);
            color: #a09f9f;
            font-size: 0.63rem;
            padding: 2px 8px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            text-transform: uppercase;
        }

        .cust-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.65rem 1rem;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            margin-bottom: 0.45rem;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255,255,255,0.025);
            user-select: none;
        }
        .cust-option:hover {
            border-color: rgba(223,186,134,0.35);
            background: rgba(223,186,134,0.05);
        }
        .cust-option.selected {
            border-color: #dfba86;
            background: rgba(223,186,134,0.1);
        }
        .cust-option input[type="radio"],
        .cust-option input[type="checkbox"] {
            accent-color: #dfba86;
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .cust-option-label { flex: 1; color: #f0ece4; font-size: 0.88rem; }
        .cust-option-price { font-size: 0.78rem; font-weight: 600; white-space: nowrap; }
        .price-plus  { color: #2ec4b6; }
        .price-minus { color: #ff7675; }
        .price-free  { color: #6c757d; }

        /* ---- Footer ---- */
        .cust-modal-footer {
            padding: 1rem 1.6rem 1.6rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .cust-error-msg {
            color: #ff6b6b;
            font-size: 0.8rem;
            text-align: center;
            margin-bottom: 0.6rem;
            display: none;
            padding: 0.5rem;
            background: rgba(255,107,107,0.08);
            border-radius: 8px;
            border: 1px solid rgba(255,107,107,0.15);
        }
        .cust-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .cust-total-lbl { color: #a09f9f; font-size: 0.88rem; }
        .cust-total-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dfba86;
            font-family: 'Playfair Display', serif;
        }
        .cust-add-btn {
            width: 100%;
            background: var(--rosewood);
            color: var(--gold);
            border: 1px solid var(--gold);
            border-radius: 13px;
            padding: 0.88rem 1rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.25s;
            letter-spacing: 0.2px;
        }
        .cust-add-btn:hover {
            transform: translateY(-2px);
            background: var(--rosewood-light);
            color: #ffffff;
            box-shadow: 0 10px 28px rgba(90,24,39,0.32);
        }
        .cust-add-btn:active { transform: translateY(0); }

        /* ---- Customizable badge on card ---- */
        .cust-badge {
            display: inline-block;
            background: rgba(223,186,134,0.1);
            color: #dfba86;
            font-size: 0.7rem;
            padding: 2px 9px;
            border-radius: 20px;
            border: 1px solid rgba(223,186,134,0.22);
            margin-bottom: 0.4rem;
        }

        /* ============================================================
           CATEGORY PILLS STYLES
        ============================================================ */
        /* Scrollbar for categories */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .category-pill {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Snap scrolling utilities and dot styling */
        .snap-x {
            scroll-snap-type: x mandatory;
        }
        .snap-start {
            scroll-snap-align: start;
        }
        .carousel-dot {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Card Hover and visual transitions */
        .menu-card {
            transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.4s cubic-bezier(0.25, 1, 0.5, 1), border-color 0.3s;
        }

        .menu-card:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 20px 40px rgba(25, 54, 39, 0.08);
            border-color: rgba(223, 186, 134, 0.3);
        }
    </style>

    <!-- Navbar Performance Optimization Links -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
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

<!-- NAVBAR -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <script src="assets/js/navbar.js" defer></script>
    <script>
        // QR Code Dine-In Mode Logic
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const tableCode = params.get('table');
            if (tableCode) {
                // We are in QR Code Mode!
                const navBrand = document.getElementById('navBrand');
                if (navBrand) navBrand.href = '#';
                const navBack = document.getElementById('navBack');
                if (navBack) navBack.style.display = 'none';
                const navAccount = document.getElementById('navAccount');
                if (navAccount) navAccount.style.display = 'none';
                const navLogout = document.getElementById('navLogout');
                if (navLogout) navLogout.style.display = 'none';
                
                // Append table parameter to Menu and Cart links
                const navMenu = document.getElementById('navMenu');
                if (navMenu) navMenu.href = 'menutest.html?table=' + tableCode;
                const navCart = document.getElementById('navCart');
                if (navCart) navCart.href = 'carttest.html?table=' + tableCode;
                
                // Show 'View Bill' which links to the cart or checkout where table bill is shown
                const navBill = document.getElementById('navBill');
                if (navBill) {
                    navBill.style.display = 'inline-block';
                    navBill.href = 'carttest.html?table=' + tableCode + '&view_bill=true';
                }
                
                // Add a welcome message
                const titleEl = document.querySelector('.section-title');
                if (titleEl) {
                    titleEl.innerHTML = `Welcome to La-Medusaa <br><small class="text-gold" style="font-size:0.5em;">Table ${tableCode} Menu</small>`;
                }
            }
        });
    </script>


    <!-- PAGE TITLE -->
    <section class="py-5">
        <div class="container">
            <h1 class="section-title fade-up">Explore Our Premium Menu</h1>

            <!-- CATEGORY NAVIGATION (Explore Our Categories) -->
            <div class="w-full mb-12 text-center flex flex-col items-center justify-center fade-up relative group" style="animation-delay: 0.2s;">
                <div class="relative w-full flex items-center justify-center mb-6">
                    <div class="absolute left-0 right-0 h-[1px] bg-[#dfba86]/30 flex items-center justify-between pointer-events-none">
                        <div class="w-1/4 border-t border-[#dfba86]/30"></div>
                        <div class="w-1/4 border-t border-[#dfba86]/30"></div>
                    </div>
                    <span class="relative font-serif text-sm md:text-base font-bold text-[#193627] uppercase tracking-widest px-4 bg-[#f9f6f0] z-10 flex items-center gap-2">
                        <i class="fas fa-leaf text-xs text-[#dfba86]"></i> Explore Our Categories <i class="fas fa-leaf text-xs text-[#dfba86]"></i>
                    </span>
                </div>

                <div class="relative w-full">
                    <button onclick="scrollCategories(-300)"
                        class="absolute -left-4 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-[#f9f6f0] shadow-md border border-[#dfba86] flex items-center justify-center text-[#dfba86] hover:bg-[#dfba86] hover:text-[#193627] transition-all hidden md:flex opacity-0 group-hover:opacity-100 disabled:opacity-0 disabled:cursor-not-allowed"
                        id="scrollLeftBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <div class="flex items-center justify-start gap-2 md:gap-3 w-full overflow-x-auto hide-scrollbar py-2 scroll-smooth cursor-grab active:cursor-grabbing"
                        id="categoryScroll" style="padding-left: calc(50% - 50px); padding-right: calc(50% - 50px);"
                        onmousedown="startDrag(event)" onmouseleave="stopDrag()" onmouseup="stopDrag()"
                        onmousemove="doDrag(event)" onscroll="updateScrollButtons()">
                        <!-- Categories injected here -->
                    </div>

                    <button onclick="scrollCategories(300)"
                        class="absolute -right-4 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-[#f9f6f0] shadow-md border border-[#dfba86] flex items-center justify-center text-[#dfba86] hover:bg-[#dfba86] hover:text-[#193627] transition-all hidden md:flex opacity-0 group-hover:opacity-100 disabled:opacity-0 disabled:cursor-not-allowed"
                        id="scrollRightBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- MENU ITEMS CONTAINER -->
            <div id="menuContainer" class="min-h-[400px]">
                <div class="flex justify-center items-center h-40">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-rosewood"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- FLOATING CART BOX -->
    <div class="floating-cart-box" id="floatingCartBox">
        <div class="floating-cart-header">
            <span>🛒 Cart Summary</span>
            <span id="floatingCartCount">0 Items</span>
        </div>
        <div class="floating-cart-total">Total: ₹<span id="floatingCartPrice">0</span></div>
        <a href="carttest.html" class="floating-cart-btn">View Cart</a>
    </div>

    <!-- ==================== CUSTOMIZATION POPUP MODAL ==================== -->
    <div class="cust-modal-overlay" id="custModalOverlay">
        <div class="cust-modal-box">
            <!-- Header -->
            <div class="cust-modal-header">
                <img class="cust-dish-img" id="custDishImg" src="" alt="">
                <div class="cust-dish-info">
                    <div class="cust-dish-name" id="custDishName">Dish Name</div>
                    <div class="cust-base-price">Base Price: ₹<span id="custBasePrice">0</span></div>
                </div>
                <button class="cust-close-btn" onclick="closeCustModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <!-- Body: groups rendered here -->
            <div class="cust-modal-body" id="custModalBody"></div>
            <!-- Footer -->
            <div class="cust-modal-footer">
                <div class="cust-error-msg" id="custErrorMsg">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <span id="custErrorText"></span>
                </div>
                <div class="cust-total-row">
                    <span class="cust-total-lbl">Total Price</span>
                    <span class="cust-total-val">₹<span id="custTotalDisplay">0</span></span>
                </div>
                <button class="cust-add-btn" onclick="confirmCustomAddToCart()">
                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                </button>
            </div>
        </div>
    </div>
    <!-- END CUSTOMIZATION MODAL -->

    <script>
        let allMenuItems = [];
        let allCategories = [];
        let localCart    = {};
        let custCurrentItem = null; // item currently open in customization modal
        let activeCategory = 'All';

        const ICONS = {
            'All': 'fas fa-utensils',
            'Beverages': 'fas fa-wine-glass-alt',
            'Beverage': 'fas fa-wine-glass-alt',
            'Soups': 'fas fa-mug-hot',
            'Salad': 'fas fa-leaf',
            'Bread Basket': 'fas fa-bread-slice',
            'Sides': 'fas fa-cheese',
            'Meals in the Bowl': 'fas fa-concierge-bell',
            'Main Course': 'fas fa-utensils',
            'Choice of Noodle': 'fas fa-wave-square',
            'Choice of Rice': 'fas fa-seedling',
            'Choice of Gravy': 'fas fa-tint',
            'Dim Sum Cart': 'fas fa-box-open',
            'Sushi Rolls': 'fas fa-fish',
            'Burgers & Sandwiches': 'fas fa-hamburger',
            'Sharing Boards': 'fas fa-pizza-slice',
            'Brick Oven Pizza': 'fas fa-pizza-slice',
            'Non-Veg Appetizer': 'fas fa-drumstick-bite',
            'Appetizers': 'fas fa-pepper-hot',
            'Pasta & Risotto Station': 'fas fa-plate-wheat',
            'Veg Appetizer': 'fas fa-leaf',
            'Veg Indian Main Course': 'fas fa-seedling',
            'Non-Veg Indian Main Course': 'fas fa-drumstick-bite',
            'Tandoori Starter': 'fas fa-fire'
        };

        /* ============================================================
           LOAD MENU FROM API
         ============================================================ */
        async function loadMenu() {
            try {
                const res = await fetch('api/get-menu.php');
                if (!res.ok) throw new Error('Network error');
                const result = await res.json();
                if (result.success && result.data && result.data.length > 0) {
                    allMenuItems = result.data;
                    allCategories = result.categories && result.categories.length > 0 ? result.categories : [...new Set(allMenuItems.map(i => i.category).filter(Boolean))];
                    renderCategories();
                    displayMenuItems();
                    updateCartCount();
                    return;
                }
                throw new Error('Empty menu');
            } catch (err) {
                console.warn('API failed, using fallback mock data.', err);
                allMenuItems = [
                    { id:1, name:'Margherita Pizza',  description:'Fresh mozzarella, tomato sauce, basil',           price:'299.00', image_url:'', category:'Brick Oven Pizza', customizations:[] },
                    { id:2, name:'Butter Chicken',    description:'Creamy tomato curry with tender chicken',          price:'349.00', image_url:'', category:'Main Course', customizations:[] },
                    { id:3, name:'Veg Biryani',        description:'Aromatic rice with vegetables and spices',         price:'249.00', image_url:'', category:'Main Course', customizations:[] },
                    { id:4, name:'Gulab Jamun',        description:'Sweet milk dumplings in warm cardamom syrup',      price:'129.00', image_url:'', category:'Sides', customizations:[] },
                    { id:5, name:'Paneer Tikka',       description:'Marinated cottage cheese grilled to perfection',   price:'279.00', image_url:'', category:'Non-Veg Appetizer', customizations:[] }
                ];
                allCategories = [...new Set(allMenuItems.map(i => i.category).filter(Boolean))];
                renderCategories();
                displayMenuItems();
                updateCartCount();
            }
        }

        /* ============================================================
           DISPLAY MENU ITEMS
        ============================================================ */
        function centerActivePill(index, instant = false) {
            const el = document.getElementById(`cat-pill-${index}`);
            const container = document.getElementById('categoryScroll');
            if (el && container) {
                const offset = el.offsetLeft - (container.offsetWidth / 2) + (el.offsetWidth / 2);
                if (instant) {
                    container.classList.remove('scroll-smooth');
                    container.scrollLeft = offset;
                    container.offsetHeight; // Force reflow
                    container.classList.add('scroll-smooth');
                } else {
                    container.classList.add('scroll-smooth');
                    container.scrollTo({ left: offset, behavior: 'smooth' });
                }
            }
        }

        function getCategoriesList() {
            const midIndex = Math.floor(allCategories.length / 2);
            return [
                ...allCategories.slice(0, midIndex),
                'All',
                ...allCategories.slice(midIndex)
            ];
        }

        function handleCategoryScroll() {
            const container = document.getElementById('categoryScroll');
            if (!container) return;

            const categories = getCategoriesList();
            const N = categories.length;
            if (N === 0) return;

            const items = container.querySelectorAll('.group');
            if (items.length < 3 * N) return;

            // Width of one full copy of the categories list
            const copyWidth = items[N].offsetLeft - items[0].offsetLeft;

            // Current scroll position
            const currentScroll = container.scrollLeft;

            // Bounds for the middle copy (copy 1)
            const leftBoundary = items[N].offsetLeft - (container.clientWidth / 2);
            const rightBoundary = items[2 * N].offsetLeft - (container.clientWidth / 2);

            if (currentScroll < leftBoundary) {
                container.classList.remove('scroll-smooth');
                container.scrollLeft += copyWidth;
                container.offsetHeight; // Force reflow
                container.classList.add('scroll-smooth');
            } else if (currentScroll > rightBoundary) {
                container.classList.remove('scroll-smooth');
                container.scrollLeft -= copyWidth;
                container.offsetHeight; // Force reflow
                container.classList.add('scroll-smooth');
            }

            let activeIdx = 0;
            let minDistance = Infinity;
            const containerCenter = container.scrollLeft + (container.offsetWidth / 2);

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const itemCenter = item.offsetLeft + (item.offsetWidth / 2);
                const dist = Math.abs(itemCenter - containerCenter);
                if (dist < minDistance) {
                    minDistance = dist;
                    activeIdx = i;
                }
            }

            const realIdx = activeIdx % N;
            const newActiveCategory = categories[realIdx];
            if (newActiveCategory && newActiveCategory !== activeCategory) {
                activeCategory = newActiveCategory;
                displayMenuItems();
            }

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const itemCenter = item.offsetLeft + (item.offsetWidth / 2);
                const dist = Math.abs(itemCenter - containerCenter);
                const distUnits = dist / 100;

                let scale = 1.0;
                let opacity = 1.0;
                if (distUnits <= 0.5) {
                    const ratio = distUnits / 0.5;
                    scale = 1.35 - (0.35 * ratio);
                    opacity = 1.0;
                } else if (distUnits <= 1.5) {
                    const ratio = (distUnits - 0.5) / 1.0;
                    scale = 1.0 - (0.18 * ratio);
                    opacity = 0.9 - (0.22 * ratio);
                } else if (distUnits <= 2.5) {
                    const ratio = (distUnits - 1.5) / 1.0;
                    scale = 0.82 - (0.14 * ratio);
                    opacity = 0.68 - (0.18 * ratio);
                } else {
                    scale = 0.68 - (0.13 * Math.min(distUnits - 2.5, 1));
                    opacity = 0.5 - (0.15 * Math.min(distUnits - 2.5, 1));
                }

                const circle = item.querySelector('.circle-wrap');
                if (circle) {
                    circle.style.transform = `scale(${scale})`;
                }
                item.style.opacity = opacity;

                const labelWrap = item.querySelector('.label-wrap');
                const isActive = (i === activeIdx);
                const cat = categories[i % N];

                if (isActive) {
                    if (circle && !circle.classList.contains('bg-[#193627]')) {
                        circle.className = "circle-wrap w-20 h-20 md:w-24 md:h-24 rounded-full border border-[#dfba86] p-1.5 bg-[#193627] flex items-center justify-center transition-all duration-300 shadow-md overflow-hidden";
                    }
                    if (labelWrap && !labelWrap.querySelector('.active-ornament')) {
                        labelWrap.innerHTML = `
                            <span class="text-[10px] md:text-[11px] font-bold text-[#193627] uppercase tracking-widest text-center mt-2">${cat}</span>
                            <div class="active-ornament flex items-center justify-center gap-1 mt-1">
                                <div class="w-5 h-[1px] bg-[#dfba86]/50"></div>
                                <span class="text-[7px] text-[#dfba86]">✦</span>
                                <div class="w-5 h-[1px] bg-[#dfba86]/50"></div>
                            </div>
                        `;
                    }
                } else {
                    if (circle && circle.classList.contains('bg-[#193627]')) {
                        circle.className = "circle-wrap w-20 h-20 md:w-24 md:h-24 rounded-full border border-[#dfba86]/30 overflow-hidden transition-all duration-300 shadow-md";
                    }
                    if (labelWrap && (labelWrap.querySelector('.active-ornament') || labelWrap.querySelector('span').classList.contains('text-[#193627]'))) {
                        labelWrap.innerHTML = `<span class="text-[9px] md:text-[10px] font-bold text-gray-500 group-hover:text-[#193627] uppercase tracking-widest text-center mt-2">${cat}</span>`;
                    }
                }
            }
        }

        function renderCategories() {
            const container = document.getElementById('categoryScroll');
            const categories = getCategoriesList();
            const N = categories.length;
            if (N === 0) return;

            let html = '';
            for (let copyIdx = 0; copyIdx < 3; copyIdx++) {
                html += categories.map((cat, index) => {
                    let imgSrc = '';
                    if (cat === 'All') {
                        imgSrc = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop';
                    } else {
                        const itemWithImg = allMenuItems.find(i => i.category === cat && i.image_url && i.image_url !== 'default.jpg' && i.image_url !== 'image.jpg');
                        imgSrc = itemWithImg ? itemWithImg.image_url : '';
                    }
                    
                    if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('//')) {
                        if (!imgSrc.startsWith('uploads/')) {
                            imgSrc = 'uploads/' + imgSrc;
                        }
                    }
                    if (!imgSrc) {
                        const PLACEHOLDERS = {
                            'All': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop',
                            'Soups': 'https://images.unsplash.com/photo-1547592165-e1d17fed6005?w=200&h=200&fit=crop',
                            'Salad': 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=200&h=200&fit=crop',
                            'Meals in the Bowl': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop',
                            'Dim Sum': 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=200&h=200&fit=crop',
                            'Sushi': 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=200&h=200&fit=crop',
                            'Chinese & Korean': 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=200&h=200&fit=crop',
                            'Burgers & Sandwiches': 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=200&h=200&fit=crop',
                            'Pasta & Risotto Station': 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=200&h=200&fit=crop',
                            'Brick Oven Pizza': 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=200&h=200&fit=crop',
                            'Main Course': 'https://images.unsplash.com/photo-1544025162-d76694265947?w=200&h=200&fit=crop',
                            'Beverages': 'https://images.unsplash.com/photo-1497534446932-c925b458314e?w=200&h=200&fit=crop',
                            'Sharing Boards': 'https://images.unsplash.com/photo-1544025162-d76694265947?w=200&h=200&fit=crop',
                            'Appetizer': 'https://images.unsplash.com/photo-1541532713592-79a0317b6b77?w=200&h=200&fit=crop',
                            'Indian': 'https://images.unsplash.com/photo-1585938338392-50a59970d8ee?w=200&h=200&fit=crop',
                            'Bread': 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=200&h=200&fit=crop',
                            'Bread Basket': 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=200&h=200&fit=crop',
                            'Sides': 'https://images.unsplash.com/photo-1608897013039-887f21d8c804?w=200&h=200&fit=crop',
                            'Choice of Noodle': 'https://images.unsplash.com/photo-1585032226651-759b368d7246?w=200&h=200&fit=crop',
                            'Choice of Rice': 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=200&h=200&fit=crop'
                        };
                        imgSrc = PLACEHOLDERS[cat] || PLACEHOLDERS['All'];
                    }

                    const globalIdx = copyIdx * N + index;

                    return `
                        <div onclick="filterCategory('${cat}', ${globalIdx})" id="cat-pill-${globalIdx}"
                             class="flex flex-col items-center gap-2 cursor-pointer group shrink-0 transition-all duration-300 py-3 select-none"
                             style="width: 100px;" data-category="${cat}" data-real-index="${index}" data-copy="${copyIdx}">
                            <div class="circle-wrap w-20 h-20 md:w-24 md:h-24 rounded-full border border-[#dfba86]/30 overflow-hidden transition-all duration-300 shadow-md">
                                <img src="${imgSrc}" alt="${cat}" class="w-full h-full rounded-full object-cover" onerror="this.onerror=null; this.src='uploads/default.jpg';">
                            </div>
                            <div class="label-wrap flex flex-col items-center min-h-[30px] justify-start w-full">
                                <span class="text-[9px] md:text-[10px] font-bold text-gray-500 group-hover:text-[#193627] uppercase tracking-widest text-center mt-2">${cat}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            container.innerHTML = html;

            if (!container.dataset.hasScrollListener) {
                container.addEventListener('scroll', handleCategoryScroll);
                container.dataset.hasScrollListener = 'true';
            }

            setTimeout(() => {
                const items = container.querySelectorAll('.group');
                if (items.length >= 2 * N) {
                    const activeIdx = categories.indexOf(activeCategory);
                    const middleGlobalIdx = N + (activeIdx !== -1 ? activeIdx : 0);
                    centerActivePill(middleGlobalIdx, true);
                }
                updateScrollButtons();
                handleCategoryScroll();
            }, 50);
        }

        function filterCategory(catName, index) {
            activeCategory = catName;
            const categories = getCategoriesList();
            const N = categories.length;
            
            let targetIdx = index;
            if (targetIdx === undefined) {
                const baseIdx = categories.indexOf(catName);
                targetIdx = baseIdx !== -1 ? N + baseIdx : -1;
            }
            
            displayMenuItems();
            
            if (targetIdx !== -1) {
                centerActivePill(targetIdx);
            }
        }


        // Drag scroll navigation helpers
        window.scrollCategories = function(amount) {
            const container = document.getElementById('categoryScroll');
            if (container) container.scrollBy({ left: amount, behavior: 'smooth' });
        };

        let isDown = false;
        let startX;
        let scrollLeft;

        window.startDrag = function(e) {
            isDown = true;
            const container = document.getElementById('categoryScroll');
            container.classList.remove('scroll-smooth');
            startX = e.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
        };

        window.stopDrag = function() {
            isDown = false;
            const container = document.getElementById('categoryScroll');
            if (container) container.classList.add('scroll-smooth');
        };

        window.doDrag = function(e) {
            if (!isDown) return;
            e.preventDefault();
            const container = document.getElementById('categoryScroll');
            const x = e.pageX - container.offsetLeft;
            const walk = (x - startX) * 2;
            container.scrollLeft = scrollLeft - walk;
        };

        window.updateScrollButtons = function() {
            const container = document.getElementById('categoryScroll');
            const leftBtn = document.getElementById('scrollLeftBtn');
            const rightBtn = document.getElementById('scrollRightBtn');
            if (!container || !leftBtn || !rightBtn) return;

            leftBtn.disabled = false;
            rightBtn.disabled = false;
        };

        // Explore Categories Banner generator
        window.renderExploreCategoriesBanner = function() {
            const bannerCategories = allCategories.map(cat => ({ name: cat, label: cat }));

            const itemsHtml = bannerCategories.map(cat => {
                const itemWithImg = allMenuItems.find(i => i.category === cat.name && i.image_url && i.image_url !== 'default.jpg' && i.image_url !== 'image.jpg');
                let imgSrc = itemWithImg ? itemWithImg.image_url : '';
                if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('//')) {
                    if (!imgSrc.startsWith('uploads/')) {
                        imgSrc = 'uploads/' + imgSrc;
                    }
                }
                if (!imgSrc) {
                    const PLACEHOLDERS = {
                        'All': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop',
                        'Soups': 'https://images.unsplash.com/photo-1547592165-e1d17fed6005?w=200&h=200&fit=crop',
                        'Salad': 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=200&h=200&fit=crop',
                        'Meals in the Bowl': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop',
                        'Dim Sum': 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=200&h=200&fit=crop',
                        'Sushi': 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=200&h=200&fit=crop',
                        'Chinese & Korean': 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=200&h=200&fit=crop',
                        'Burgers & Sandwiches': 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=200&h=200&fit=crop',
                        'Pasta & Risotto Station': 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=200&h=200&fit=crop',
                        'Brick Oven Pizza': 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=200&h=200&fit=crop',
                        'Main Course': 'https://images.unsplash.com/photo-1544025162-d76694265947?w=200&h=200&fit=crop',
                        'Beverages': 'https://images.unsplash.com/photo-1497534446932-c925b458314e?w=200&h=200&fit=crop',
                        'Sharing Boards': 'https://images.unsplash.com/photo-1544025162-d76694265947?w=200&h=200&fit=crop',
                        'Appetizer': 'https://images.unsplash.com/photo-1541532713592-79a0317b6b77?w=200&h=200&fit=crop',
                        'Indian': 'https://images.unsplash.com/photo-1585938338392-50a59970d8ee?w=200&h=200&fit=crop',
                        'Bread': 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=200&h=200&fit=crop',
                        'Bread Basket': 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=200&h=200&fit=crop',
                        'Sides': 'https://images.unsplash.com/photo-1608897013039-887f21d8c804?w=200&h=200&fit=crop',
                        'Choice of Noodle': 'https://images.unsplash.com/photo-1585032226651-759b368d7246?w=200&h=200&fit=crop',
                        'Choice of Rice': 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=200&h=200&fit=crop'
                    };
                    imgSrc = PLACEHOLDERS[cat.name] || PLACEHOLDERS['All'];
                }

                return `
                    <div onclick="filterCategory('${cat.name}'); window.scrollTo({top: document.getElementById('categoryScroll').offsetTop - 100, behavior: 'smooth'});" 
                         class="flex flex-col items-center gap-2 cursor-pointer group shrink-0">
                        <div class="w-16 h-16 md:w-20 md:h-20 rounded-full border-2 border-[#dfba86]/30 overflow-hidden group-hover:scale-105 group-hover:border-[#dfba86] transition-all duration-300 shadow-md">
                            <img src="${imgSrc}" alt="${cat.label}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='uploads/default.jpg';">
                        </div>
                        <span class="text-[9px] md:text-[10px] font-bold text-[#dfba86]/90 group-hover:text-[#dfba86] transition-colors uppercase tracking-widest text-center max-w-[80px]">${cat.label}</span>
                    </div>
                `;
            }).join('');

            return `
                <div class="w-full bg-[#193627] border border-[#dfba86]/30 rounded-3xl p-6 md:p-8 my-16 text-center shadow-lg flex flex-col items-center justify-center fade-up">
                    <div class="relative w-full flex items-center justify-center mb-6">
                        <div class="absolute left-0 right-0 h-[1px] bg-[#dfba86]/20 flex items-center justify-between pointer-events-none">
                            <div class="w-1/4 border-t border-[#dfba86]/20"></div>
                            <div class="w-1/4 border-t border-[#dfba86]/20"></div>
                        </div>
                        <span class="relative font-serif text-sm md:text-base font-bold text-[#dfba86] uppercase tracking-widest px-4 bg-[#193627] z-10 flex items-center gap-2">
                            <i class="fas fa-star text-[10px] text-[#dfba86]"></i> Explore Our Categories <i class="fas fa-star text-[10px] text-[#dfba86]"></i>
                        </span>
                    </div>
                    <div class="flex items-center justify-start gap-4 md:gap-8 w-full overflow-x-auto hide-scrollbar py-2 px-4">
                        ${itemsHtml}
                    </div>
                </div>
            `;
        };

        // Scroll helper for chevrons
        window.scrollCarousel = function(catId, direction) {
            const carousel = document.getElementById(`carousel-${catId}`);
            if (!carousel) return;
            carousel.scrollBy({ left: direction * carousel.clientWidth, behavior: 'smooth' });
        };

        // Scroll helper for page dots
        window.scrollToPage = function(catId, pageIdx) {
            const carousel = document.getElementById(`carousel-${catId}`);
            if (!carousel) return;
            carousel.scrollTo({ left: pageIdx * carousel.clientWidth, behavior: 'smooth' });
        };

        // Update indicators on scroll
        window.updateCarouselDots = function(catId) {
            const carousel = document.getElementById(`carousel-${catId}`);
            if (!carousel) return;
            const scrollIndex = Math.round(carousel.scrollLeft / carousel.clientWidth);
            const dotsContainer = document.getElementById(`dots-${catId}`);
            if (!dotsContainer) return;
            const dots = dotsContainer.querySelectorAll('.carousel-dot');
            dots.forEach((dot, idx) => {
                if (idx === scrollIndex) {
                    dot.classList.add('bg-[#193627]', 'w-4');
                    dot.classList.remove('bg-[#dfba86]/40', 'w-2');
                } else {
                    dot.classList.remove('bg-[#193627]', 'w-4');
                    dot.classList.add('bg-[#dfba86]/40', 'w-2');
                }
            });

            const prevBtn = document.getElementById(`prevBtn-${catId}`);
            const nextBtn = document.getElementById(`nextBtn-${catId}`);
            if (prevBtn) prevBtn.disabled = carousel.scrollLeft <= 5;
            if (nextBtn) nextBtn.disabled = Math.ceil(carousel.scrollLeft) >= carousel.scrollWidth - carousel.clientWidth - 5;
        };

        function generateCardHtml(item, index, isCarousel) {
            let imgSrc = item.image_url || '';
            if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('//')) {
                if (!imgSrc.startsWith('uploads/')) {
                    imgSrc = 'uploads/' + imgSrc;
                }
            }
            
            const PLACEHOLDERS = {
                'All': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&h=400&fit=crop',
                'Soups': 'https://images.unsplash.com/photo-1547592165-e1d17fed6005?w=500&h=400&fit=crop',
                'Salad': 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500&h=400&fit=crop',
                'Meals in the Bowl': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&h=400&fit=crop',
                'Dim Sum': 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=500&h=400&fit=crop',
                'Sushi': 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=500&h=400&fit=crop',
                'Chinese & Korean': 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=500&h=400&fit=crop',
                'Burgers & Sandwiches': 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500&h=400&fit=crop',
                'Pasta & Risotto Station': 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=500&h=400&fit=crop',
                'Brick Oven Pizza': 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=500&h=400&fit=crop',
                'Main Course': 'https://images.unsplash.com/photo-1544025162-d76694265947?w=500&h=400&fit=crop',
                'Beverages': 'https://images.unsplash.com/photo-1497534446932-c925b458314e?w=500&h=400&fit=crop',
                'Sharing Boards': 'https://images.unsplash.com/photo-1544025162-d76694265947?w=500&h=400&fit=crop',
                'Appetizer': 'https://images.unsplash.com/photo-1541532713592-79a0317b6b77?w=500&h=400&fit=crop',
                'Indian': 'https://images.unsplash.com/photo-1585938338392-50a59970d8ee?w=500&h=400&fit=crop',
                'Bread': 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500&h=400&fit=crop',
                'Bread Basket': 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500&h=400&fit=crop',
                'Sides': 'https://images.unsplash.com/photo-1608897013039-887f21d8c804?w=500&h=400&fit=crop'
            };
            const fallbackSrc = PLACEHOLDERS[item.category] || PLACEHOLDERS['All'];
            if (!imgSrc) imgSrc = fallbackSrc;

            const hasCust = item.customizations && item.customizations.length > 0;
            const delay = (index % 4) * 0.1;

            let hasNonVegOption = false;
            if (item.customizations && item.customizations.length > 0) {
                for (const cust of item.customizations) {
                    if (cust.options && cust.options.length > 0) {
                        for (const opt of cust.options) {
                            const label = (opt.label || '').toLowerCase();
                            if (label.includes('chicken') || label.includes('prawn') || label.includes('fish') || label.includes('mutton') || label.includes('lamb') || label.includes('non-veg') || label.includes('non veg')) {
                                hasNonVegOption = true;
                                break;
                            }
                        }
                    }
                    if (hasNonVegOption) break;
                }
            }

            const isVeg = (item.diet_type === 'veg');
            const vegSvg = `<span class="inline-flex shrink-0 align-middle ml-1.5" title="Vegetarian"><svg viewBox="0 0 24 24" width="16" height="16"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="#0f8a45" stroke-width="2.5"/><circle cx="12" cy="12" r="5" fill="#0f8a45"/></svg></span>`;
            const nonVegSvg = `<span class="inline-flex shrink-0 align-middle ml-1.5" title="Non-Vegetarian"><svg viewBox="0 0 24 24" width="16" height="16"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="#c82333" stroke-width="2.5"/><circle cx="12" cy="12" r="5" fill="#c82333"/></svg></span>`;

            let dietBadge = '';
            if (isVeg) {
                dietBadge += vegSvg;
                if (hasNonVegOption) {
                    dietBadge += nonVegSvg;
                }
            } else {
                dietBadge += nonVegSvg;
            }
            dietBadge = `<div class="flex items-center gap-1.5 shrink-0">${dietBadge}</div>`;

            const cardClasses = isCarousel 
                ? 'w-full sm:w-[calc(50%-12px)] xl:w-[calc(25%-18px)] shrink-0 snap-start'
                : 'w-full';

            return `
                <div class="menu-card bg-white rounded-3xl p-4 flex flex-col justify-between ${cardClasses} h-[440px] fade-up shadow-[0_8px_30px_rgb(0,0,0,0.03)] hover:shadow-[0_20px_50px_rgba(0,0,0,0.06)] border border-[#dfba86]/10 transition-all duration-300" style="animation-delay: ${delay}s">
                    <div class="relative h-48 w-full rounded-2xl overflow-hidden bg-gray-100 mb-4 shrink-0 shadow-inner">
                        <img src="${imgSrc}" alt="${item.name}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='uploads/default.jpg';">
                        ${hasCust ? '<div class="absolute top-3 left-3 bg-[#f9f6f0]/90 backdrop-blur-sm text-[#193627] text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm"><i class="fas fa-sliders-h mr-1 text-[#dfba86]"></i>Customizable</div>' : ''}
                    </div>
                    <div class="flex flex-col flex-1 px-1">
                        <div class="flex justify-between items-start gap-2 mb-1">
                            <h3 class="font-serif text-base font-bold text-gray-800 leading-tight line-clamp-2">${item.name}</h3>
                            ${dietBadge}
                        </div>
                        <div class="font-sans font-bold text-sm text-[#2f1317] mb-2">₹${Math.round(parseFloat(item.price || 0))}</div>
                        <p class="text-xs text-gray-500 leading-relaxed line-clamp-3 mb-4 flex-grow">${item.description || 'Premium dish crafted with quality ingredients.'}</p>
                        
                        <div class="mt-auto pt-2">
                            <button id="add-btn-${item.id}" onclick="handleAddToCart(${item.id})" class="flex items-center justify-between bg-[#193627] hover:bg-[#132c1e] text-white rounded-xl py-2.5 px-4 w-full transition-all text-xs font-bold uppercase tracking-wider border-0 cursor-pointer shadow-sm hover:shadow">
                                <span>ADD TO CART</span>
                                <span class="w-5 h-5 rounded-full bg-[#dfba86] text-[#193627] flex items-center justify-center font-bold text-xs"><i class="fas fa-plus text-[9px]"></i></span>
                            </button>
                            
                            <div id="qty-controller-${item.id}" class="hidden items-center justify-between bg-[#193627] text-white rounded-xl overflow-hidden h-[38px] px-2 shadow-sm">
                                <button onclick="changeQuantity(${item.id}, -1)" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center border-0 cursor-pointer transition-colors"><i class="fas fa-minus text-[9px]"></i></button>
                                <span id="qty-${item.id}" class="font-bold text-sm text-white px-2">1</span>
                                <button onclick="changeQuantity(${item.id}, 1)" class="w-8 h-8 rounded-full bg-[#dfba86] text-[#193627] flex items-center justify-center border-0 cursor-pointer transition-colors"><i class="fas fa-plus text-[9px]"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function displayMenuItems() {
            const container = document.getElementById('menuContainer');

            let itemsToRender = allMenuItems;
            if (activeCategory !== 'All') {
                itemsToRender = allMenuItems.filter(i => i.category === activeCategory);
            }

            if (itemsToRender.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-20 fade-up">
                        <i class="fas fa-utensils text-5xl text-gray-300 mb-4"></i>
                        <p class="font-serif text-xl text-gray-500">No dishes available.</p>
                    </div>`;
                return;
            }

            // Group by category for rendering
            const grouped = {};
            itemsToRender.forEach(item => {
                const c = item.category || 'Other';
                if (!grouped[c]) grouped[c] = [];
                grouped[c].push(item);
            });

            let html = '';
            let catIndex = 0;

            for (const [cat, items] of Object.entries(grouped)) {
                const catId = cat.replace(/[^a-zA-Z0-9]/g, '');
                const hasSubcategories = items.some(item => item.subcategory);

                // Render Carousel Header
                const headerHtml = `
                    <div class="relative w-full flex items-center justify-center my-12">
                        <div class="absolute left-0 right-0 h-[1px] bg-[#dfba86]/40 flex items-center justify-between pointer-events-none">
                            <div class="w-[38%] border-t border-[#dfba86]/30 relative">
                                <div class="absolute right-0 -top-1 w-2 h-2 rotate-45 border border-[#dfba86] bg-cream"></div>
                            </div>
                            <div class="w-[38%] border-t border-[#dfba86]/30 relative">
                                <div class="absolute left-0 -top-1 w-2 h-2 rotate-45 border border-[#dfba86] bg-cream"></div>
                            </div>
                        </div>
                        <span class="relative font-serif text-xl md:text-2xl font-bold text-[#193627] uppercase tracking-widest px-6 bg-cream z-10">${cat}</span>
                        ${activeCategory === 'All' ? `
                            <button onclick="filterCategory('${cat}')" class="absolute right-0 top-1/2 -translate-y-1/2 text-[10px] font-bold text-[#dfba86] hover:text-[#b8973a] transition-colors bg-transparent border-0 outline-none cursor-pointer uppercase tracking-widest flex items-center gap-1 font-serif">
                                View All <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                            </button>
                        ` : ''}
                    </div>
                `;

                if (hasSubcategories) {
                    const subgrouped = {};
                    items.forEach(item => {
                        const sub = item.subcategory || 'Other';
                        if (!subgrouped[sub]) subgrouped[sub] = [];
                        subgrouped[sub].push(item);
                    });

                    html += `
                        <div class="w-full fade-up">
                            ${headerHtml}
                    `;

                    for (const [subcat, subitems] of Object.entries(subgrouped)) {
                        const subcatId = (catId + subcat.replace(/[^a-zA-Z0-9]/g, ''));
                        const subcardsHtml = subitems.map((item, index) => generateCardHtml(item, index, activeCategory === 'All')).join('');

                        if (activeCategory === 'All') {
                            const numDots = Math.ceil(subitems.length / (window.innerWidth < 768 ? 1 : (window.innerWidth < 1200 ? 2 : 4)));
                            const dotsHtml = Array.from({ length: numDots }).map((_, idx) => `
                                <div class="carousel-dot h-2 rounded-full cursor-pointer transition-all duration-300 ${idx === 0 ? 'bg-[#193627] w-4' : 'bg-[#dfba86]/40 w-2'}" 
                                     onclick="scrollToPage('${subcatId}', ${idx})"></div>
                            `).join('');

                            html += `
                                <div class="w-full mb-8">
                                    <div class="flex items-center gap-4 mb-6">
                                        <h4 class="font-serif text-md font-semibold text-[#193627] uppercase tracking-wider">${subcat}</h4>
                                        <div class="flex-grow h-[1px] bg-gradient-to-r from-[#dfba86]/30 to-transparent"></div>
                                    </div>
                                    <div class="relative group w-full mb-10">
                                        <button onclick="scrollCarousel('${subcatId}', -1)" 
                                                class="absolute -left-5 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full border border-[#dfba86] bg-[#f9f6f0] text-[#dfba86] hover:bg-[#dfba86] hover:text-[#193627] transition-all flex items-center justify-center shadow-md opacity-0 group-hover:opacity-100 disabled:opacity-0 disabled:cursor-not-allowed"
                                                id="prevBtn-${subcatId}" disabled>
                                            <i class="fas fa-chevron-left text-xs"></i>
                                        </button>
                                        
                                        <div id="carousel-${subcatId}" 
                                             class="flex gap-6 overflow-x-auto hide-scrollbar scroll-smooth snap-x snap-mandatory py-4 px-2"
                                             onscroll="updateCarouselDots('${subcatId}')">
                                            ${subcardsHtml}
                                        </div>
                                        
                                        <button onclick="scrollCarousel('${subcatId}', 1)" 
                                                class="absolute -right-5 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full border border-[#dfba86] bg-[#f9f6f0] text-[#dfba86] hover:bg-[#dfba86] hover:text-[#193627] transition-all flex items-center justify-center shadow-md opacity-0 group-hover:opacity-100 disabled:opacity-0 disabled:cursor-not-allowed"
                                                id="nextBtn-${subcatId}" ${numDots <= 1 ? 'disabled' : ''}>
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        </button>
                                        
                                        <div class="flex justify-center items-center gap-2 mt-4" id="dots-${subcatId}">
                                            ${dotsHtml}
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            html += `
                                <div class="w-full mb-8">
                                    <div class="flex items-center gap-4 mb-6">
                                        <h4 class="font-serif text-md font-semibold text-[#193627] uppercase tracking-wider">${subcat}</h4>
                                        <div class="flex-grow h-[1px] bg-gradient-to-r from-[#dfba86]/30 to-transparent"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">
                                        ${subcardsHtml}
                                    </div>
                                </div>
                            `;
                        }
                    }

                    html += `
                        </div>
                    `;
                } else {
                    const cardsHtml = items.map((item, index) => generateCardHtml(item, index, activeCategory === 'All')).join('');

                    if (activeCategory === 'All') {
                        const numDots = Math.ceil(items.length / (window.innerWidth < 768 ? 1 : (window.innerWidth < 1200 ? 2 : 4)));
                        const dotsHtml = Array.from({ length: numDots }).map((_, idx) => `
                            <div class="carousel-dot h-2 rounded-full cursor-pointer transition-all duration-300 ${idx === 0 ? 'bg-[#193627] w-4' : 'bg-[#dfba86]/40 w-2'}" 
                                 onclick="scrollToPage('${catId}', ${idx})"></div>
                        `).join('');

                        html += `
                            <div class="w-full fade-up">
                                ${headerHtml}
                                <div class="relative group w-full mb-12">
                                    <button onclick="scrollCarousel('${catId}', -1)" 
                                            class="absolute -left-5 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full border border-[#dfba86] bg-[#f9f6f0] text-[#dfba86] hover:bg-[#dfba86] hover:text-[#193627] transition-all flex items-center justify-center shadow-md opacity-0 group-hover:opacity-100 disabled:opacity-0 disabled:cursor-not-allowed"
                                            id="prevBtn-${catId}" disabled>
                                        <i class="fas fa-chevron-left text-xs"></i>
                                    </button>
                                    
                                    <div id="carousel-${catId}" 
                                         class="flex gap-6 overflow-x-auto hide-scrollbar scroll-smooth snap-x snap-mandatory py-4 px-2"
                                         onscroll="updateCarouselDots('${catId}')">
                                        ${cardsHtml}
                                    </div>
                                    
                                    <button onclick="scrollCarousel('${catId}', 1)" 
                                            class="absolute -right-5 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full border border-[#dfba86] bg-[#f9f6f0] text-[#dfba86] hover:bg-[#dfba86] hover:text-[#193627] transition-all flex items-center justify-center shadow-md opacity-0 group-hover:opacity-100 disabled:opacity-0 disabled:cursor-not-allowed"
                                            id="nextBtn-${catId}" ${numDots <= 1 ? 'disabled' : ''}>
                                        <i class="fas fa-chevron-right text-xs"></i>
                                    </button>
                                    
                                    <div class="flex justify-center items-center gap-2 mt-4" id="dots-${catId}">
                                        ${dotsHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="w-full fade-up">
                                ${headerHtml}
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-16">
                                    ${cardsHtml}
                                </div>
                            </div>
                        `;
                    }
                }

                catIndex++;
            }

            container.innerHTML = html;
            syncCartUI();

            // Trigger scroll indicators check initially
            if (activeCategory === 'All') {
                for (const [cat, items] of Object.entries(grouped)) {
                    const hasSub = items.some(item => item.subcategory);
                    if (hasSub) {
                        const subCats = [...new Set(items.map(item => item.subcategory || 'Other'))];
                        subCats.forEach(sub => {
                            const subcatId = (cat.replace(/[^a-zA-Z0-9]/g, '') + sub.replace(/[^a-zA-Z0-9]/g, ''));
                            updateCarouselDots(subcatId);
                        });
                    } else {
                        const catId = cat.replace(/[^a-zA-Z0-9]/g, '');
                        updateCarouselDots(catId);
                    }
                }
            }
        }

        /* ============================================================
           HANDLE ADD TO CART CLICK
           — opens customization modal if dish has options, else adds directly
        ============================================================ */
        function handleAddToCart(id) {
            const item = allMenuItems.find(i => i.id == id);
            if (!item) return;

            if (item.customizations && item.customizations.length > 0) {
                openCustModal(item);
            } else {
                addToCart(id, {}, parseFloat(item.price));
            }
        }

        /* ============================================================
           OPEN CUSTOMIZATION MODAL
        ============================================================ */
        function openCustModal(item) {
            custCurrentItem = item;
            const basePrice = parseFloat(item.price);

            // Set header info
            let imgSrc = item.image_url || '';
            if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('//')) {
                if (!imgSrc.startsWith('uploads/')) {
                    imgSrc = 'uploads/' + imgSrc;
                }
            }
            if (!imgSrc) imgSrc = 'uploads/default.jpg';
            document.getElementById('custDishImg').src      = imgSrc;
            document.getElementById('custDishImg').alt      = item.name;
            document.getElementById('custDishName').textContent = item.name;
            document.getElementById('custBasePrice').textContent = basePrice.toFixed(0);
            document.getElementById('custTotalDisplay').textContent = basePrice.toFixed(0);
            document.getElementById('custErrorMsg').style.display = 'none';

            // Build customization groups
            const body = document.getElementById('custModalBody');
            body.innerHTML = '';

            item.customizations.forEach((group, gi) => {
                const options = Array.isArray(group.options) ? group.options : [];
                const isRequired = parseInt(group.is_required) === 1;
                const isMultiple = group.group_type === 'multiple';

                const groupEl = document.createElement('div');
                groupEl.className = 'cust-group';

                const reqBadge = isRequired
                    ? '<span class="badge-required">Required</span>'
                    : '<span class="badge-optional">Optional</span>';
                groupEl.innerHTML = `<div class="cust-group-label">${group.group_name} ${reqBadge}</div>`;

                const optionsWrap = document.createElement('div');
                options.forEach((opt, oi) => {
                    const inputType = isMultiple ? 'checkbox' : 'radio';
                    const inputName = `cust_g${gi}`;
                    const inputId   = `cust_g${gi}_o${oi}`;
                    const priceAdd  = parseFloat(opt.price_add) || 0;

                    let priceLabel = '', priceClass = 'price-free';
                    if (priceAdd > 0)      { priceLabel = `+₹${priceAdd}`; priceClass = 'price-plus'; }
                    else if (priceAdd < 0) { priceLabel = `-₹${Math.abs(priceAdd)}`; priceClass = 'price-minus'; }
                    else                   { priceLabel = 'Included'; }

                    const label = document.createElement('label');
                    label.className = 'cust-option';
                    label.setAttribute('for', inputId);
                    label.innerHTML = `
                        <input type="${inputType}" id="${inputId}" name="${inputName}"
                               value="${oi}" data-price="${priceAdd}" data-group="${gi}"
                               onchange="onOptionChange(this)">
                        <span class="cust-option-label">${opt.label}</span>
                        <span class="cust-option-price ${priceClass}">${priceLabel}</span>
                    `;
                    optionsWrap.appendChild(label);
                });

                groupEl.appendChild(optionsWrap);
                body.appendChild(groupEl);
            });

            // Show modal
            document.getElementById('custModalOverlay').classList.add('show');
            document.body.style.overflow = 'hidden';
            recalcTotal();
        }

        function onOptionChange(input) {
            // Update selected styling
            const name = input.name;
            const allInGroup = document.querySelectorAll(`input[name="${name}"]`);
            allInGroup.forEach(inp => {
                inp.closest('.cust-option').classList.toggle('selected', inp.checked);
            });
            recalcTotal();
        }

        function closeCustModal() {
            document.getElementById('custModalOverlay').classList.remove('show');
            document.body.style.overflow = '';
            custCurrentItem = null;
        }

        // Close on overlay background click
        document.getElementById('custModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeCustModal();
        });

        function recalcTotal() {
            if (!custCurrentItem) return;
            const base = parseFloat(custCurrentItem.price);
            let extra = 0;
            document.querySelectorAll('#custModalBody input:checked').forEach(inp => {
                extra += parseFloat(inp.dataset.price) || 0;
            });
            document.getElementById('custTotalDisplay').textContent = (base + extra).toFixed(0);

            // Dynamic image toggle for Thai Curry
            if (custCurrentItem.name && custCurrentItem.name.includes('Thai Curry')) {
                let chosenStyle = 'green';
                document.querySelectorAll('#custModalBody input:checked').forEach(inp => {
                    const labelEl = inp.closest('label').querySelector('span');
                    if (labelEl) {
                        const text = labelEl.textContent.trim().toLowerCase();
                        if (text === 'green') {
                            chosenStyle = 'green';
                        } else if (text === 'red') {
                            chosenStyle = 'red';
                        }
                    }
                });
                const imgEl = document.getElementById('custDishImg');
                if (imgEl) {
                    imgEl.src = `uploads/thai_curry_${chosenStyle}.jpg`;
                }
            }
        }

        /* ============================================================
           CONFIRM CUSTOMIZATIONS & ADD TO CART
        ============================================================ */
        function confirmCustomAddToCart() {
            if (!custCurrentItem) return;

            const groups = custCurrentItem.customizations || [];
            let valid = true;
            let firstMissing = '';
            const selectedChoices = {};

            groups.forEach((group, gi) => {
                const checked = document.querySelectorAll(`#custModalBody input[name="cust_g${gi}"]:checked`);
                if (parseInt(group.is_required) === 1 && checked.length === 0) {
                    if (valid) firstMissing = group.group_name;
                    valid = false;
                }
                if (checked.length > 0) {
                    const opts = Array.isArray(group.options) ? group.options : [];
                    const labels = Array.from(checked).map(inp => (opts[parseInt(inp.value)] || {}).label).filter(Boolean);
                    selectedChoices[group.group_name] = labels.join(', ');
                }
            });

            if (!valid) {
                const errEl = document.getElementById('custErrorMsg');
                document.getElementById('custErrorText').textContent = `Please select an option for "${firstMissing}"`;
                errEl.style.display = 'block';
                return;
            }

            // Calculate final price
            const base = parseFloat(custCurrentItem.price);
            let extra = 0;
            document.querySelectorAll('#custModalBody input:checked').forEach(inp => {
                extra += parseFloat(inp.dataset.price) || 0;
            });
            const finalPrice = base + extra;
            const itemId = custCurrentItem.id;

            closeCustModal();
            addToCart(itemId, selectedChoices, finalPrice);
        }

        /* ============================================================
           CART UI HELPERS
        ============================================================ */
        function toggleCartUI(id, isAdded) {
            const addBtn = document.getElementById(`add-btn-${id}`);
            const qtyCtrl = document.getElementById(`qty-controller-${id}`);
            if (addBtn && qtyCtrl) {
                if (isAdded) {
                    addBtn.classList.add('hidden');
                    qtyCtrl.classList.remove('hidden');
                    qtyCtrl.classList.add('flex');
                } else {
                    addBtn.classList.remove('hidden');
                    qtyCtrl.classList.add('hidden');
                    qtyCtrl.classList.remove('flex');
                }
            }
        }

        function syncCartUI() {
            allMenuItems.forEach(i => toggleCartUI(i.id, false));
            for (const [id, qty] of Object.entries(localCart)) {
                if (qty > 0) {
                    const el = document.getElementById(`qty-${id}`);
                    if (el) el.textContent = qty;
                    toggleCartUI(id, true);
                }
            }
        }

        function renderCartUI(items) {
            const count = items.reduce((s, i) => s + i.quantity, 0);
            const cartCountEl = document.getElementById('cartCount');
            if (cartCountEl) cartCountEl.textContent = count;
            const floatCartCountEl = document.getElementById('floatingCartCount');
            if (floatCartCountEl) floatCartCountEl.textContent = `${count} Items`;
            const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
            const floatPriceEl = document.getElementById('floatingCartPrice');
            if (floatPriceEl) floatPriceEl.textContent = total.toFixed(0);

            syncCartUI();
        }

        /* ============================================================
           ADD TO CART API CALL
        ============================================================ */
        async function addToCart(id, customizations, finalPrice) {
            try {
                const res = await fetch('api/add-to-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ food_item_id: id, quantity: 1, customizations, custom_price: finalPrice })
                });
                if (res.status === 401) {
                    throw new Error('Unauthorized');
                }
                const result = await res.json();
                if (result.success) {
                    localCart[id] = 1;
                    updateCartCount();
                } else throw new Error(result.message);
            } catch (err) {
                console.warn('add-to-cart API failed, using local fallback.', err);
                localCart[id] = 1;
                updateCartCount();
            }
        }

        /* ============================================================
           CHANGE QUANTITY
        ============================================================ */
        async function changeQuantity(id, change) {
            const el = document.getElementById(`qty-${id}`);
            let qty  = parseInt(el.textContent) || 1;
            qty += change;

            if (qty <= 0) {
                // ── CRITICAL: remove from localStorage BEFORE calling updateCartCount
                // Otherwise the fallback in updateCartCount reloads the stale item
                try {
                    let saved = JSON.parse(localStorage.getItem('foodie_cart') || '[]');
                    saved = saved.filter(i => i.id != id);
                    localStorage.setItem('foodie_cart', JSON.stringify(saved));
                } catch (e) {}

                // Remove from in-memory cart
                delete localCart[id];

                // Immediately hide the qty controller and show Add to Cart button
                // (don't wait for async updateCartCount to reflect the change)
                toggleCartUI(id, false);

                // Update counts
                updateCartCount();

                // Also attempt to sync with server (fire-and-forget)
                fetch('api/remove-from-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ food_item_id: id })
                }).catch(() => {});
                return;
            }

            el.textContent = qty;
            try {
                const res = await fetch('api/update-cart-qty.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ food_item_id: id, quantity: qty })
                });
                if (!res.ok && change > 0) {
                    await fetch('api/add-to-cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ food_item_id: id, quantity: 1 })
                    });
                }
            } catch (e) { console.warn(e); }
            localCart[id] = qty;
            updateCartCount();
        }

        /* ============================================================
           UPDATE CART COUNT FROM SERVER
        ============================================================ */
        async function updateCartCount() {
            try {
                const res = await fetch('api/get-cart.php');
                if (res.status === 401) {
                    document.getElementById('cartCount').textContent = '0';
                    return;
                }
                const result = await res.json();
                if (result.success && result.items) {
                    localCart = {};
                    result.items.forEach(i => { localCart[i.id] = i.quantity; });
                    const formatted = result.items.map(i => ({
                        id: i.food_item_id || i.id,
                        name: i.name,
                        price: parseFloat(i.price),
                        image_url: i.image_url,
                        quantity: i.quantity
                    }));
                    renderCartUI(formatted);
                    localStorage.setItem('foodie_cart', JSON.stringify(formatted));
                    return;
                }
                throw new Error('Bad cart data');
            } catch (err) {
                console.warn('get-cart API failed, using local fallback.', err);
                if (Object.keys(localCart).length === 0) {
                    try {
                        const saved = JSON.parse(localStorage.getItem('foodie_cart') || '[]');
                        saved.forEach(i => { localCart[i.id] = i.quantity; });
                    } catch (e) {}
                }
                const formatted = Object.keys(localCart).map(k => {
                    const itemId   = parseInt(k);
                    const itemData = allMenuItems.find(i => i.id == itemId);
                    return {
                        id: itemId,
                        name:      itemData ? itemData.name      : 'Item',
                        price:     itemData ? parseFloat(itemData.price) : 0,
                        image_url: itemData ? itemData.image_url : '',
                        quantity:  localCart[k]
                    };
                }).filter(i => i.quantity > 0);
                renderCartUI(formatted);
                localStorage.setItem('foodie_cart', JSON.stringify(formatted));
            }
        }

        function filterCategory(catName, index) {
            activeCategory = catName;
            const midIndex = Math.floor(allCategories.length / 2);
            const categories = [
                ...allCategories.slice(0, midIndex),
                'All',
                ...allCategories.slice(midIndex)
            ];
            const targetIdx = index !== undefined ? index : categories.indexOf(catName);
            if (targetIdx !== -1) {
                centerActivePill(targetIdx);
            } else {
                displayMenuItems();
            }
        }

        /* ============================================================
           INIT
        ============================================================ */
        const urlParams = new URLSearchParams(window.location.search);
        const tableNum  = urlParams.get('table');
        if (tableNum) localStorage.setItem('table_number', tableNum);
        loadMenu().then(() => {
            const cat = urlParams.get('category');
            if (cat) {
                filterCategory(cat);
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once __DIR__ . '/api/config.php'; ?>
<?php require_once __DIR__ . '/includes/active_order_bar.php'; ?>
<?php require_once __DIR__ . '/includes/order_toast.php'; ?>
<?php include __DIR__ . '/promo-popup.php'; ?>
</body>
</html>
