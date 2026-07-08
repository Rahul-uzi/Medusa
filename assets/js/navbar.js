(function() {
    // Inject navbar.css dynamically if not already loaded
    try {
        if (!document.querySelector('link[href*="navbar.css"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'assets/css/navbar.css';
            document.head.appendChild(link);
        }
    } catch (e) {
        console.error('Error injecting navbar.css:', e);
    }

    // Inject visual standard fonts dynamically if not already loaded
    try {
        if (!document.querySelector('link[href*="fonts.googleapis.com/css2?family=Playfair+Display"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap';
            document.head.appendChild(link);
        }
    } catch (e) {
        console.error('Error injecting visual standard fonts:', e);
    }

    // Intercept localStorage calls to detect changes in the same window/tab
    try {
        const originalSetItem = localStorage.setItem;
        localStorage.setItem = function(key, value) {
            originalSetItem.apply(this, arguments);
            if (key === 'foodie_cart' || key === 'foodie_cart_timestamp') {
                window.dispatchEvent(new Event('cartUpdated'));
            }
        };

        const originalRemoveItem = localStorage.removeItem;
        localStorage.removeItem = function(key) {
            originalRemoveItem.apply(this, arguments);
            if (key === 'foodie_cart' || key === 'foodie_cart_timestamp') {
                window.dispatchEvent(new Event('cartUpdated'));
            }
        };

        const originalClear = localStorage.clear;
        localStorage.clear = function() {
            originalClear.apply(this, arguments);
            window.dispatchEvent(new Event('cartUpdated'));
        };
    } catch (e) {
        console.error('Error overriding localStorage operations:', e);
    }

    // Dynamic Tailwind CSS Injector
    if (typeof tailwind === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.tailwindcss.com';
        
        // Suppress Tailwind Play CDN warning for production
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com should not be used in production')) {
                return;
            }
            originalWarn.apply(console, args);
        };

        script.onload = () => {
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
        };
        document.head.appendChild(script);
    }

    // Function to initialize all event listeners and dynamic content in navbar
    function initNavbar() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        
        // Active page highlighting
        const navLinks = document.querySelectorAll('.nav-link-item');
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage || (currentPage === 'index.php' && href === 'index.html')) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // Dine-In Table Parameter Propagation
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const tableCode = urlParams.get('table') || localStorage.getItem('table_number');
            if (tableCode) {
                const links = document.querySelectorAll('#main-navbar a, #nav-mobile-drawer a');
                links.forEach(l => {
                    let href = l.getAttribute('href');
                    if (href && href !== '#' && !href.startsWith('javascript:') && !href.startsWith('api/')) {
                        // Avoid double appending
                        if (!href.includes('table=')) {
                            const separator = href.includes('?') ? '&' : '?';
                            l.setAttribute('href', href + separator + 'table=' + tableCode);
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Error propagating table parameter:', e);
        }

        // Hide spacer for specific pages with built-in fixed spacing
        const fixedPages = ['register.php', 'login.html', 'verify_otp.php', 'track.php'];
        if (fixedPages.includes(currentPage)) {
            const spacer = document.getElementById('navbar-spacer');
            if (spacer) spacer.style.display = 'none';
        }

        // Cart badge logic
        function updateCartBadges() {
            try {
                const savedCart = JSON.parse(localStorage.getItem('foodie_cart') || '[]');
                const count = savedCart.reduce((acc, item) => acc + (item.quantity || 0), 0);
                
                const badge = document.getElementById('cartCount');
                if (badge) {
                    badge.textContent = count;
                    if (count > 0) {
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }

                const badgeMob = document.getElementById('cartCountMobile');
                if (badgeMob) {
                    badgeMob.textContent = count;
                    if (count > 0) {
                        badgeMob.classList.remove('hidden');
                    } else {
                        badgeMob.classList.add('hidden');
                    }
                }

                const badgeDrawer = document.getElementById('cartCountMobileDrawer');
                if (badgeDrawer) {
                    badgeDrawer.textContent = count;
                    if (count > 0) {
                        badgeDrawer.classList.remove('hidden');
                    } else {
                        badgeDrawer.classList.add('hidden');
                    }
                }
            } catch (e) {
                console.error('Error parsing cart:', e);
            }
        }
        updateCartBadges();
        window.addEventListener('storage', updateCartBadges);
        window.addEventListener('cartUpdated', updateCartBadges);

        // Toggle mobile drawer
        const toggleBtn = document.getElementById('nav-mobile-toggle');
        const drawer = document.getElementById('nav-mobile-drawer');
        if (toggleBtn && drawer) {
            toggleBtn.addEventListener('click', (e) => {
                drawer.classList.toggle('hidden');
                e.stopPropagation();
            });
        }

        // Toggle notifications dropdown
        const notifBellContainer = document.getElementById('nav-notif-bell');
        if (notifBellContainer) {
            const notifBtn = notifBellContainer.querySelector('button');
            const notifDropdown = document.getElementById('nav-notif-dropdown');
            if (notifBtn && notifDropdown) {
                notifBtn.addEventListener('click', (e) => {
                    notifDropdown.classList.toggle('hidden');
                    
                    // Hide profile dropdown
                    const profileDropdown = document.getElementById('nav-profile-dropdown');
                    if (profileDropdown) profileDropdown.classList.add('hidden');

                    // Mark read logic
                    const redDot = document.getElementById('navNotifRedDot');
                    const bellIcon = document.getElementById('navNotifBellIcon');
                    if (bellIcon && bellIcon.classList.contains('bell-ringing')) {
                        bellIcon.classList.remove('bell-ringing');
                        if (redDot) redDot.style.display = 'none';
                        
                        fetch('api/mark-notifications-read.php', {
                            method: 'POST'
                        }).catch(err => console.error('Error marking notifications read:', err));
                    }
                    e.stopPropagation();
                });
            }
        }

        // Toggle profile dropdown (Hover on Desktop, Click on Mobile)
        const profileMenuContainer = document.getElementById('nav-profile-menu');
        if (profileMenuContainer) {
            const profileBtn = profileMenuContainer.querySelector('button');
            const profileDropdown = document.getElementById('nav-profile-dropdown');
            if (profileBtn && profileDropdown) {
                let leaveTimeout;

                // Mouse enter: open dropdown immediately on desktop
                profileMenuContainer.addEventListener('mouseenter', () => {
                    if (window.innerWidth >= 1024) {
                        clearTimeout(leaveTimeout);
                        profileDropdown.classList.remove('hidden');
                        
                        // Hide other dropdowns
                        const notifDropdown = document.getElementById('nav-notif-dropdown');
                        if (notifDropdown) notifDropdown.classList.add('hidden');
                    }
                });

                // Mouse leave: close dropdown with 200ms delay on desktop
                profileMenuContainer.addEventListener('mouseleave', () => {
                    if (window.innerWidth >= 1024) {
                        leaveTimeout = setTimeout(() => {
                            profileDropdown.classList.add('hidden');
                        }, 200);
                    }
                });

                // Click event: handles mobile touch trigger
                profileBtn.addEventListener('click', (e) => {
                    if (window.innerWidth < 1024) {
                        profileDropdown.classList.toggle('hidden');
                        
                        // Hide notifications dropdown
                        const notifDropdown = document.getElementById('nav-notif-dropdown');
                        if (notifDropdown) notifDropdown.classList.add('hidden');
                        
                        e.stopPropagation();
                    }
                });
            }
        }

        // Click outside to close dropdowns
        document.addEventListener('click', (e) => {
            const notifDropdown = document.getElementById('nav-notif-dropdown');
            if (notifDropdown && !e.target.closest('#nav-notif-bell')) {
                notifDropdown.classList.add('hidden');
            }
            const profileDropdown = document.getElementById('nav-profile-dropdown');
            if (profileDropdown && !e.target.closest('#nav-profile-menu')) {
                profileDropdown.classList.add('hidden');
            }
            if (drawer && !e.target.closest('#nav-mobile-toggle') && !e.target.closest('#nav-mobile-drawer')) {
                drawer.classList.add('hidden');
            }
        });

        // ── ADDITIVE ENHANCEMENTS ────────────────────────────────────────────

        // A. Sticky navbar: add/remove .navbar-scrolled on scroll
        (function initScrolledClass() {
            const navbar = document.getElementById('main-navbar');
            if (!navbar) return;
            function _updateScrolled() {
                if (window.scrollY > 10) {
                    navbar.classList.add('navbar-scrolled');
                } else {
                    navbar.classList.remove('navbar-scrolled');
                }
            }
            _updateScrolled();
            window.addEventListener('scroll', _updateScrolled, { passive: true });
        })();

        // B. Page-transition overlay: dark curtain fade out → navigate → fade in on new page
        (function initPageTransition() {
            let overlay = document.getElementById('nav-page-transition');
            if (!overlay) return;

            // New page just loaded (or loaded from bfcache) — fade the curtain away
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    overlay.classList.add('nav-pt-fadeout');
                });
            });

            // On nav-link click: fade curtain in, then hard-navigate
            document.addEventListener('click', function _navTransClick(e) {
                var link = e.target.closest('a');
                if (!link) return;
                var href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('#')
                    || href.startsWith('javascript:')
                    || href.startsWith('mailto:')
                    || href.startsWith('tel:')
                    || link.target === '_blank') return;
                try {
                    var url = new URL(link.href, window.location.origin);
                    if (url.origin !== window.location.origin) return;
                    if (url.pathname.includes('/api/') || url.pathname.includes('/admin/')) return;
                } catch (_) { return; }

                e.preventDefault();
                var dest = link.href;
                
                // Remove fadeout class so it returns to opacity: 1
                overlay.classList.remove('nav-pt-fadeout');
                
                overlay.addEventListener('transitionend', function _go() {
                    overlay.removeEventListener('transitionend', _go);
                    window.location.href = dest;
                }, { once: true });
                
                // Fallback in case transitionend doesn't fire
                setTimeout(function() { window.location.href = dest; }, 200);
            });
            
            // Handle back/forward bfcache
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    overlay.classList.add('nav-pt-fadeout');
                }
            });
        })();

        // C. Section entrance animations via IntersectionObserver
        (function initSectionReveal() {
            var candidates = document.querySelectorAll('section, article, .reveal');
            if (!candidates.length || !window.IntersectionObserver) return;
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('section-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08 });
            candidates.forEach(function(el) {
                if (el.closest && el.closest('#main-navbar, #nav-mobile-drawer')) return;
                var rect = el.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    el.classList.add('section-visible');
                } else {
                    el.classList.add('nav-reveal-section');
                    observer.observe(el);
                }
            });
        })();

    }

    // Main logic runner
    document.addEventListener('DOMContentLoaded', () => {
        const placeholder = document.getElementById('navbar-placeholder');
        if (placeholder) {
            // Fetch dynamically for static pages
            fetch('includes/navbar.php')
                .then(res => {
                    if (!res.ok) throw new Error('Failed to load navbar');
                    return res.text();
                })
                .then(html => {
                    placeholder.innerHTML = html;
                    initNavbar();
                })
                .catch(err => console.error('Error loading shared navbar:', err));
        } else {
            // Already pre-rendered in server-side PHP include
            initNavbar();
        }
    });
})();
