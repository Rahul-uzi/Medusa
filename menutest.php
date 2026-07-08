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
    <title>Premium Menu - Medusa</title>
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
        .category-selector-container {
            width: 100%;
            overflow: hidden;
            padding: 10px 0;
            margin-bottom: 2.5rem;
            position: relative;
        }
        .category-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            white-space: nowrap;
            padding: 10px 20px;
            justify-content: flex-start;
            scrollbar-width: thin;
            scrollbar-color: rgba(223, 186, 134, 0.2) transparent;
        }
        .category-scroll::-webkit-scrollbar {
            height: 5px;
        }
        .category-scroll::-webkit-scrollbar-thumb {
            background: rgba(223, 186, 134, 0.25);
            border-radius: 10px;
        }
        .category-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(223, 186, 134, 0.45);
        }
        .category-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
        }
        .category-pill {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(223, 186, 134, 0.18);
            color: var(--gray);
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            user-select: none;
            backdrop-filter: blur(5px);
        }
        .category-pill:hover {
            border-color: var(--gold);
            color: #ffffff;
            background: rgba(223, 186, 134, 0.05);
            transform: translateY(-2px);
        }
        .category-pill.active {
            background: linear-gradient(135deg, var(--gold) 0%, #c89640 100%);
            color: #0c0a0a;
            border-color: var(--gold);
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(223, 186, 134, 0.25);
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
                    titleEl.innerHTML = `Welcome to Medusa <br><small class="text-gold" style="font-size:0.5em;">Table ${tableCode} Menu</small>`;
                }
            }
        });
    </script>


    <!-- PAGE TITLE -->
    <section class="py-5">
        <div class="container">
            <h1 class="section-title fade-up">Explore Our Premium Menu</h1>

            <!-- CATEGORY FILTER -->
            <div class="category-selector-container fade-up">
                <div class="category-scroll">
                    <button class="category-pill active" onclick="filterCategory('All')">All</button>
                    <button class="category-pill" onclick="filterCategory('Liquor')">Liquor</button>
                    <button class="category-pill" onclick="filterCategory('Soups')">Soups</button>
                    <button class="category-pill" onclick="filterCategory('Salad')">Salad</button>
                    <button class="category-pill" onclick="filterCategory('Bread Basket')">Bread Basket</button>
                    <button class="category-pill" onclick="filterCategory('Sides')">Sides</button>
                    <button class="category-pill" onclick="filterCategory('Meals in the Bowl')">Meals in the Bowl</button>
                    <button class="category-pill" onclick="filterCategory('Main Course')">Main Course</button>
                    <button class="category-pill" onclick="filterCategory('Choice of Noodle')">Choice of Noodle</button>
                    <button class="category-pill" onclick="filterCategory('Choice of Rice')">Choice of Rice</button>
                    <button class="category-pill" onclick="filterCategory('Choice of Gravy')">Choice of Gravy</button>
                    <button class="category-pill" onclick="filterCategory('Dim Sum Cart')">Dim Sum Cart</button>
                    <button class="category-pill" onclick="filterCategory('Sushi Rolls')">Sushi Rolls</button>
                    <button class="category-pill" onclick="filterCategory('Burgers & Sandwiches')">Burgers & Sandwiches</button>
                    <button class="category-pill" onclick="filterCategory('Sharing Boards')">Sharing Boards</button>
                    <button class="category-pill" onclick="filterCategory('Brick Oven Pizza')">Brick Oven Pizza</button>
                    <button class="category-pill" onclick="filterCategory('Non-Veg Appetizer')">Non-Veg Appetizer</button>
                    <button class="category-pill" onclick="filterCategory('Pasta & Risotto Station')">Pasta & Risotto Station</button>
                    <button class="category-pill" onclick="filterCategory('Veg Appetizer')">Veg Appetizer</button>
                    <button class="category-pill" onclick="filterCategory('Veg Indian Main Course')">Veg Indian Main Course</button>
                    <button class="category-pill" onclick="filterCategory('Non-Veg Indian Main Course')">Non-Veg Indian Main Course</button>
                    <button class="category-pill" onclick="filterCategory('Tandoori Starter')">Tandoori Starter</button>
                </div>
            </div>

            <div class="row" id="menuContainer">
                <div class="col-12 text-center">
                    <div class="spinner-border text-light"></div>
                    <p class="mt-3">Loading menu...</p>
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
        let localCart    = {};
        let custCurrentItem = null; // item currently open in customization modal

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
                    displayMenuItems(allMenuItems);
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
                displayMenuItems(allMenuItems);
                updateCartCount();
            }
        }

        /* ============================================================
           DISPLAY MENU ITEMS
        ============================================================ */
        function displayMenuItems(items) {
            const container = document.getElementById('menuContainer');
            if (!items || items.length === 0) {
                container.innerHTML = `
                <div class="col-12 text-center py-5 fade-up">
                    <i class="fas fa-utensils text-muted mb-3" style="font-size: 3rem; color: var(--gold) !important; opacity: 0.6;"></i>
                    <p class="text-muted" style="font-size: 1.1rem; font-family: 'Playfair Display', serif;">No dishes available in this category.</p>
                </div>`;
                return;
            }
            container.innerHTML = items.map(item => {
                let imgSrc = item.image_url || '';
                if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('//')) {
                    if (!imgSrc.startsWith('uploads/')) {
                        imgSrc = 'uploads/' + imgSrc;
                    }
                }
                if (!imgSrc) imgSrc = 'uploads/default.jpg';

                const hasCust = item.customizations && item.customizations.length > 0;
                const custBadge = hasCust
                    ? `<span class="cust-badge"><i class="fas fa-sliders-h" style="font-size:0.65rem;margin-right:4px;"></i>Customizable</span>`
                    : '';

                return `
                <div class="col-lg-4 col-md-6 mb-5 fade-up">
                    <div class="menu-card">
                        <div class="menu-card-img">
                            <img src="${imgSrc}" alt="${item.name}" loading="lazy" onerror="this.src='uploads/default.jpg'">
                        </div>
                        <div class="menu-card-body">
                            <h3 class="menu-title">${item.name}</h3>
                            <p class="menu-description">${item.description || 'Fresh premium dish crafted with quality ingredients.'}</p>
                            ${custBadge}
                            <div class="menu-price">₹${parseFloat(item.price).toFixed(0)}</div>
                            <div class="cart-controls">
                                <button class="btn-premium w-100 add-btn" id="add-btn-${item.id}" onclick="handleAddToCart(${item.id})">Add To Cart</button>
                                <div class="quantity-controller" id="qty-controller-${item.id}" style="display:none;">
                                    <button class="qty-btn" onclick="changeQuantity(${item.id}, -1)">-</button>
                                    <span class="qty-number" id="qty-${item.id}">1</span>
                                    <button class="qty-btn" onclick="changeQuantity(${item.id}, 1)">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
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
            const addBtn  = document.getElementById(`add-btn-${id}`);
            const qtyCtrl = document.getElementById(`qty-controller-${id}`);
            if (addBtn && qtyCtrl) {
                addBtn.style.display  = isAdded ? 'none' : 'block';
                qtyCtrl.style.display = isAdded ? 'flex'  : 'none';
            }
        }

        function renderCartUI(items) {
            const count = items.reduce((s, i) => s + i.quantity, 0);
            const cartCountEl = document.getElementById('cartCount');
            if (cartCountEl) cartCountEl.textContent = count;
            const floatCartCountEl = document.getElementById('floatingCartCount');
            if (floatCartCountEl) floatCartCountEl.textContent = `${count} Items`;
            const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
            document.getElementById('floatingCartPrice').textContent = total.toFixed(0);

            allMenuItems.forEach(i => toggleCartUI(i.id, false));
            items.forEach(item => {
                const el = document.getElementById(`qty-${item.id}`);
                if (el) el.textContent = item.quantity;
                toggleCartUI(item.id, item.quantity > 0);
            });
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
                    const params = new URLSearchParams(window.location.search);
                    if (params.get('table')) {
                        // In QR Code Mode, bypass login and throw an error to use local cart fallback
                        throw new Error('Guest mode, using local cart');
                    } else {
                        alert('Please login first to add items to cart.');
                        window.location.href = 'login.html';
                        return;
                    }
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

        /* ============================================================
           FILTER BY CATEGORY
        ============================================================ */
        let activeCategory = 'All';
        function filterCategory(categoryName) {
            activeCategory = categoryName;
            
            // Update active pill styling
            const pills = document.querySelectorAll('.category-pill');
            pills.forEach(pill => {
                const text = pill.textContent.trim();
                if (text.toLowerCase() === categoryName.toLowerCase()) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });

            // Filter menu items
            if (categoryName === 'All') {
                displayMenuItems(allMenuItems);
            } else {
                const filtered = allMenuItems.filter(item => {
                    const itemCat = item.category ? item.category.trim().toLowerCase() : '';
                    const filterCat = categoryName.trim().toLowerCase();
                    return itemCat === filterCat;
                });
                displayMenuItems(filtered);
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
