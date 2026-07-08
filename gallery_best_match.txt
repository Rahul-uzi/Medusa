<?php
/**
 * gallery.php — Premium Photo & Video Gallery
 * Pulls images and videos directly from Google Drive public folders.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
// Load gallery manifest if present (admin-managed uploads)
$gallery_manifest = [];
$manifest_path = __DIR__ . '/uploads/gallery/gallery.json';
if (file_exists($manifest_path)) {
    $gallery_manifest = json_decode(file_get_contents($manifest_path), true) ?: [];
}

// Scan local shoot images
$local_images = [];
$shoot_dir = __DIR__ . '/assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot';
if (is_dir($shoot_dir)) {
    $files = glob($shoot_dir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
    if ($files) {
        sort($files);
        foreach ($files as $file) {
            $filename = basename($file);
            $num = intval(preg_replace('/[^0-9]/', '', $filename));
            
            // Programmatically categorize images based on filename index ranges
            if ($num < 960) {
                $category = 'food';
            } elseif ($num < 1010) {
                $category = 'drinks';
            } elseif ($num < 1060) {
                $category = 'ambiance';
            } else {
                $category = 'events';
            }

            $local_images[] = [
                'id' => 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/' . $filename,
                'category' => $category
            ];
        }
    }
}
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
        transition: opacity 0.32s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: all;
    }
    #nav-page-transition.nav-pt-fadeout {
        opacity: 0 !important;
        pointer-events: none !important;
    }
</style>

<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery — LA-MEDUSAA Bar & Lounge</title>
    <meta name="description" content="Moments to Savor — A curated collection of our finest moments at La-Medusaa, crafted with passion and served with love.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --bg-dark:      #0D2016;
            --bg-secondary: #132F20;
            --card-bg:      #0f1f14;
            --gold:         #dfba86;
            --gold-light:   #f3dfc1;
            --gold-dim:     rgba(223,186,134,0.35);
            --gold-glow:    rgba(223,186,134,0.12);
            --ivory:        #FAF7F0;
            --muted:        rgba(250,247,240,0.55);
            --accent:       #5A1827;
            --border:       rgba(223,186,134,0.15);
            --serif:        'Playfair Display', Georgia, serif;
            --sans:         'Plus Jakarta Sans', sans-serif;
            --radius:       16px;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--sans);
            background: var(--bg-dark);
            color: var(--ivory);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ═══════════════ NAV ═══════════════ */
        .top-nav {
            position: sticky; top: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 48px; height: 72px;
            background: rgba(13,32,22,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .nav-logo { display: flex; align-items: center; gap: 14px; text-decoration: none; }
        .nav-logo img { width: 40px; height: 40px; object-fit: contain; border-radius: 50%; border: 1px solid var(--gold-dim); }
        .nav-brand { font-family: var(--serif); font-size: 1rem; font-weight: 600; color: var(--gold); letter-spacing: 3px; text-transform: uppercase; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-link { font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); text-decoration: none; transition: color 0.2s; font-weight: 500; }
        .nav-link:hover, .nav-link.active { color: var(--gold); }
        .nav-link.active { border-bottom: 1px solid var(--gold); padding-bottom: 2px; }
        .nav-reserve {
            background: var(--accent); color: var(--gold-light); border: none;
            font-family: var(--sans); font-size: 0.68rem; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; text-decoration: none;
            padding: 10px 22px; border-radius: 6px; transition: all 0.2s;
        }
        .nav-reserve:hover { background: #7a2035; transform: translateY(-1px); }

        /* ═══════════════ HERO ═══════════════ */
        .hero {
            position: relative;
            background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-dark) 100%);
            padding: 90px 48px 60px;
            text-align: center;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(223,186,134,0.08) 0%, transparent 65%);
            pointer-events: none;
        }
        .hero-eyebrow {
            font-size: 0.72rem; letter-spacing: 5px; text-transform: uppercase;
            color: var(--gold); margin-bottom: 16px; font-weight: 600;
        }
        .hero-title {
            font-family: var(--serif); font-size: clamp(2.8rem, 6vw, 5rem);
            font-weight: 600; color: var(--ivory); line-height: 1.1; margin-bottom: 20px;
        }
        .hero-divider {
            display: flex; align-items: center; justify-content: center;
            gap: 16px; margin-bottom: 20px; opacity: 0.6;
        }
        .hero-divider span { width: 60px; height: 1px; background: var(--gold); display: block; }
        .hero-divider i { color: var(--gold); font-size: 0.6rem; }
        .hero-subtitle {
            font-size: 0.95rem; color: var(--muted); max-width: 500px;
            margin: 0 auto 36px; line-height: 1.7;
        }

        /* ═══════════════ TAB SWITCHER ═══════════════ */
        .tab-switcher {
            display: inline-flex; background: rgba(255,255,255,0.04);
            border: 1px solid var(--border); border-radius: 50px;
            padding: 5px; gap: 4px;
        }
        .tab-btn {
            padding: 10px 28px; border-radius: 50px; border: none;
            font-family: var(--sans); font-size: 0.78rem; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase; cursor: pointer;
            transition: all 0.25s; color: var(--muted); background: transparent;
            display: flex; align-items: center; gap: 8px;
        }
        .tab-btn.active {
            background: var(--gold); color: #0D2016;
        }
        .tab-btn:not(.active):hover { color: var(--gold); }

        /* ═══════════════ FILTER BAR ═══════════════ */
        .filter-bar {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; padding: 28px 48px 0; flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 22px; border-radius: 50px;
            border: 1px solid var(--border); background: transparent;
            font-family: var(--sans); font-size: 0.7rem; font-weight: 600;
            letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer;
            color: var(--muted); transition: all 0.2s;
        }
        .filter-btn.active, .filter-btn:hover {
            background: var(--accent); border-color: var(--accent); color: var(--gold-light);
        }

        /* ═══════════════ SECTION TITLE ═══════════════ */
        .section-label {
            text-align: center; padding: 48px 48px 28px;
        }
        .section-label h2 {
            font-family: var(--serif); font-size: 1.1rem; font-weight: 400;
            color: var(--gold); letter-spacing: 4px; text-transform: uppercase;
        }

        /* ═══════════════ PHOTO GRID ═══════════════ */
        .photos-section { padding: 0 48px 64px; }

        .masonry-grid {
            columns: 4; column-gap: 12px;
        }

        .photo-item {
            break-inside: avoid;
            position: relative; overflow: hidden;
            border-radius: 10px;
            margin-bottom: 12px;
            cursor: pointer;
            background: var(--card-bg);
        }

        .photo-item img {
            width: 100%; display: block;
            transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            border-radius: 10px;
        }

        .photo-item:hover img { transform: scale(1.05); }

        .photo-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(5,15,8,0.85) 100%);
            opacity: 0; transition: opacity 0.35s;
            display: flex; align-items: flex-end; padding: 16px;
            border-radius: 10px;
        }
        .photo-item:hover .photo-overlay { opacity: 1; }

        .photo-overlay-inner {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%;
        }
        .photo-expand-icon {
            width: 34px; height: 34px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 0.8rem;
            backdrop-filter: blur(8px);
        }

        /* ═══════════════ VIDEO GRID ═══════════════ */
        .videos-section { padding: 0 48px 64px; display: none; }
        .videos-section.active-tab { display: block; }

        .video-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .video-card {
            position: relative; border-radius: var(--radius);
            overflow: hidden; background: var(--card-bg);
            border: 1px solid var(--border);
            cursor: pointer; transition: transform 0.3s, box-shadow 0.3s;
        }
        .video-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.4); }

        .video-thumb {
            position: relative; aspect-ratio: 16/9; overflow: hidden;
            background: #0a1a0f;
        }
        .video-thumb iframe {
            width: 100%; height: 100%; border: none;
            pointer-events: none; display: block;
        }

        .video-play-overlay {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: rgba(5,15,8,0.4); transition: background 0.3s;
        }
        .video-card:hover .video-play-overlay { background: rgba(5,15,8,0.2); }

        .play-circle {
            width: 58px; height: 58px; border-radius: 50%;
            background: rgba(223,186,134,0.9); backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            transition: transform 0.25s, background 0.25s;
        }
        .video-card:hover .play-circle { transform: scale(1.1); background: var(--gold); }
        .play-circle i { color: #0D2016; font-size: 1.1rem; margin-left: 3px; }

        .video-info { padding: 16px 18px; }
        .video-name {
            font-family: var(--serif); font-size: 1rem;
            color: var(--ivory); font-weight: 600; margin-bottom: 4px;
        }
        .video-desc { font-size: 0.8rem; color: var(--muted); line-height: 1.5; }

        /* ═══════════════ LIGHTBOX ═══════════════ */
        .lightbox {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(5,12,7,0.97); backdrop-filter: blur(20px);
            display: none; align-items: center; justify-content: center;
        }
        .lightbox.open { display: flex; animation: lbFadeIn 0.3s ease; }
        @keyframes lbFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .lightbox-inner {
            position: relative; max-width: 90vw; max-height: 90vh;
            display: flex; align-items: center; justify-content: center;
        }
        .lightbox-img {
            max-width: 90vw; max-height: 88vh;
            border-radius: 10px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.8);
            object-fit: contain; display: block;
        }
        .lb-close {
            position: fixed; top: 24px; right: 28px;
            width: 44px; height: 44px; border-radius: 50%;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: white; font-size: 1.1rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; backdrop-filter: blur(8px);
        }
        .lb-close:hover { background: rgba(255,255,255,0.2); transform: scale(1.05); }

        .lb-arrow {
            position: fixed; top: 50%; transform: translateY(-50%);
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18);
            color: white; font-size: 1rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; backdrop-filter: blur(8px); z-index: 10000;
        }
        .lb-arrow:hover { background: rgba(223,186,134,0.25); border-color: var(--gold); color: var(--gold); }
        .lb-prev { left: 20px; }
        .lb-next { right: 20px; }
        .lb-counter {
            position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
            font-size: 0.75rem; color: rgba(255,255,255,0.5); letter-spacing: 2px;
        }

        /* Video Lightbox */
        .video-lightbox {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(5,12,7,0.97); backdrop-filter: blur(20px);
            display: none; align-items: center; justify-content: center;
        }
        .video-lightbox.open { display: flex; animation: lbFadeIn 0.3s ease; }
        .vlb-inner {
            position: relative; width: min(900px, 92vw);
        }
        .vlb-inner iframe {
            width: 100%; aspect-ratio: 16/9; border-radius: 12px;
            border: none; display: block;
            box-shadow: 0 40px 80px rgba(0,0,0,0.8);
        }

        /* ═══════════════ INSTAGRAM BANNER ═══════════════ */
        .insta-banner {
            background: var(--accent);
            padding: 52px 48px;
            display: flex; align-items: center; justify-content: space-between; gap: 24px;
            flex-wrap: wrap;
        }
        .insta-left { display: flex; align-items: center; gap: 24px; }
        .insta-icon {
            width: 64px; height: 64px; border: 2px solid rgba(255,255,255,0.25);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: rgba(255,255,255,0.9); flex-shrink: 0;
        }
        .insta-text h3 {
            font-family: var(--serif); font-size: 1.3rem; color: var(--gold-light); margin-bottom: 6px;
        }
        .insta-text p { font-size: 0.85rem; color: rgba(255,255,255,0.7); line-height: 1.5; }
        .insta-btn {
            display: inline-flex; align-items: center; gap: 10px;
            border: 1px solid rgba(255,255,255,0.4); color: white;
            font-family: var(--sans); font-size: 0.72rem; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none;
            padding: 12px 28px; border-radius: 50px; transition: all 0.2s;
        }
        .insta-btn:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.6); }

        /* ═══════════════ FOOTER ═══════════════ */
        footer {
            background: #080f09; padding: 52px 48px 28px;
        }
        .footer-grid {
            display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px; margin-bottom: 40px;
        }
        .footer-brand { }
        .footer-brand img { width: 44px; height: 44px; object-fit: contain; margin-bottom: 12px; filter: brightness(0.9); }
        .footer-brand-name { font-family: var(--serif); font-size: 1.05rem; color: var(--gold); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; }
        .footer-brand p { font-size: 0.82rem; color: var(--muted); line-height: 1.7; }
        .footer-col h4 { font-size: 0.65rem; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); margin-bottom: 16px; }
        .footer-col p, .footer-col a {
            display: block; font-size: 0.82rem; color: var(--muted); line-height: 2; text-decoration: none; transition: color 0.2s;
        }
        .footer-col a:hover { color: var(--gold); }
        .footer-bottom {
            border-top: 1px solid var(--border); padding-top: 24px;
            text-align: center; font-size: 0.75rem; color: rgba(250,247,240,0.3);
        }

        /* ═══════════════ LOADING SPINNER ═══════════════ */
        .load-more-row {
            text-align: center; padding: 20px 0 48px;
        }
        .btn-load-more {
            display: inline-flex; align-items: center; gap: 10px;
            border: 1px solid var(--border); background: transparent;
            color: var(--gold); font-family: var(--sans); font-size: 0.75rem;
            font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            padding: 12px 32px; border-radius: 50px; cursor: pointer;
            transition: all 0.25s;
        }
        .btn-load-more:hover { background: var(--gold-glow); border-color: var(--gold); }

        /* ═══════════════ RESPONSIVE ═══════════════ */
        @media (max-width: 1100px) { .masonry-grid { columns: 3; } }
        @media (max-width: 768px) {
            .top-nav { padding: 0 20px; }
            .hero { padding: 60px 24px 40px; }
            .photos-section, .videos-section { padding: 0 20px 48px; }
            .filter-bar { padding: 20px 20px 0; }
            .masonry-grid { columns: 2; }
            .video-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .insta-banner { padding: 36px 24px; }
            .nav-links { display: none; }
        }
        @media (max-width: 480px) { .masonry-grid { columns: 1; } }
    </style>

    <!-- Navbar Performance Optimization Links -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        // Expose server-side gallery manifest to client JS (if present)
        window.GALLERY_MANIFEST = <?php echo json_encode($gallery_manifest, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?> || [];
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

<!-- ══════════ NAV ══════════ -->
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<script src="assets/js/navbar.js" defer></script>

<!-- ══════════ HERO ══════════ -->
<section class="hero">
    <p class="hero-eyebrow">Gallery</p>
    <h1 class="hero-title">Moments to Savor</h1>
    <div class="hero-divider">
        <span></span>
        <i class="fas fa-fan"></i>
        <span></span>
    </div>
    <p class="hero-subtitle">A collection of our finest moments, crafted with passion and served with love.</p>
    <div class="tab-switcher" id="tabSwitcher">
        <button class="tab-btn active" id="photosTabBtn" onclick="switchTab('photos')">
            <i class="far fa-image"></i> Photos
        </button>
        <button class="tab-btn" id="videosTabBtn" onclick="switchTab('videos')">
            <i class="fas fa-play-circle"></i> Videos
        </button>
    </div>
</section>

<!-- ══════════ FILTER BAR ══════════ -->
<div class="filter-bar" id="filterBar">
    <button class="filter-btn active" onclick="filterPhotos('all', this)">All</button>
    <button class="filter-btn" onclick="filterPhotos('food', this)">Food</button>
    <button class="filter-btn" onclick="filterPhotos('drinks', this)">Drinks</button>
    <button class="filter-btn" onclick="filterPhotos('ambiance', this)">Ambiance</button>
    <button class="filter-btn" onclick="filterPhotos('events', this)">Events</button>
</div>

<!-- ══════════ PHOTOS SECTION ══════════ -->
<div class="section-label" id="photosSectionLabel">
    <h2>Photos</h2>
</div>

<section class="photos-section" id="photosSection">
    <div class="masonry-grid" id="photosGrid">
        <!-- Injected by JS -->
    </div>
    <div class="load-more-row" id="photoLoadMore">
        <button class="btn-load-more" onclick="loadMorePhotos()">
            <i class="fas fa-images"></i> Load More Photos
        </button>
    </div>
</section>

<!-- ══════════ VIDEOS SECTION ══════════ -->
<div class="section-label" id="videosSectionLabel" style="display:none;">
    <h2>Videos</h2>
</div>

<section class="videos-section" id="videosSection">
    <div class="video-grid" id="videoGrid">
        <!-- Injected by JS -->
    </div>
    <div class="load-more-row" id="videoLoadMore">
        <button class="btn-load-more" onclick="loadMoreVideos()">
            <i class="fas fa-play-circle"></i> Load More Videos
        </button>
    </div>
</section>

<!-- ══════════ INSTAGRAM BANNER ══════════ -->
<div class="insta-banner">
    <div class="insta-left">
        <div class="insta-icon"><i class="fab fa-instagram"></i></div>
        <div class="insta-text">
            <h3>Share Your Moments With Us</h3>
            <p>Tag us on Instagram @la.medusaa<br>We'd love to feature your experience.</p>
        </div>
    </div>
    <a href="https://www.instagram.com/la_medusaa_mohali?igsh=MXVwcHA3Nm9wbXV1dQ==" target="_blank" class="insta-btn">
        Follow Us <i class="fab fa-instagram"></i>
    </a>
</div>

<!-- ══════════ FOOTER ══════════ -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <img src="assets/images/medusaa2(onlylogo).png" alt="La-Medusaa" onerror="this.src='assets/images/versace_logo.png'">
            <div class="footer-brand-name">La-Medusaa</div>
            <p>A premium bar & lounge experience in Sector 67, Mohali. Crafted cocktails, live music, and unforgettable evenings.</p>
        </div>
        <div class="footer-col">
            <h4>Open Hours</h4>
            <p>Mon – Fri: 11:00 AM – 11:00 PM</p>
            <p>Sat – Sun: 10:00 AM – 12:00 AM</p>
        </div>
        <div class="footer-col">
            <h4>Contact Us</h4>
            <p>SCO 44-45, District One Market</p>
            <p>Sector 67, Mohali</p>
            <a href="tel:+911234567890">+91 12345 67890</a>
            <a href="mailto:info@lamedusaa.com">info@lamedusaa.com</a>
        </div>
        <div class="footer-col">
            <h4>Follow Us</h4>
            <a href="https://www.instagram.com/la_medusaa_mohali?igsh=MXVwcHA3Nm9wbXV1dQ%3D%3D"><i class="fab fa-instagram me-2"></i> Instagram</a>
            <a href="#"><i class="fab fa-facebook me-2"></i> Facebook</a>
            <a href="#"><i class="fab fa-pinterest me-2"></i> Pinterest</a>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date('Y'); ?> LA-MEDUSAA Bar &amp; Lounge. All rights reserved.
    </div>
</footer>

<!-- ══════════ PHOTO LIGHTBOX ══════════ -->
<div class="lightbox" id="photoLightbox" onclick="closeLightbox(event)">
    <button class="lb-arrow lb-prev" onclick="lbNavigate(-1)"><i class="fas fa-chevron-left"></i></button>
    <button class="lb-arrow lb-next" onclick="lbNavigate(1)"><i class="fas fa-chevron-right"></i></button>
    <button class="lb-close" onclick="closeLightboxDirect()"><i class="fas fa-times"></i></button>
    <div class="lightbox-inner">
        <img class="lightbox-img" id="lbImg" src="" alt="Gallery photo">
    </div>
    <div class="lb-counter" id="lbCounter"></div>
</div>

<!-- ══════════ VIDEO LIGHTBOX ══════════ -->
<div class="video-lightbox" id="videoLightbox" onclick="closeVideoLb(event)">
    <button class="lb-close" onclick="closeVideoLbDirect()"><i class="fas fa-times"></i></button>
    <div class="vlb-inner">
        <iframe id="vlbFrame" src="" allowfullscreen allow="autoplay"></iframe>
    </div>
</div>

<script>
// ═══════════════════ DATA ═══════════════════
const PHOTO_IDS = <?php echo json_encode($local_images); ?>;

const VIDEO_IDS = [
    { id: '1LDRgiPlYAqmliRkFdLakKtqaWALZDu9M', name: 'Kitchen Vibes', desc: 'Behind the scenes energy from our kitchen.' },
    { id: '1NIbSzZqYwbFolu7XtUC9eCoD3CnBnyJU', name: 'Evening Ambiance', desc: 'The warmth of our dining floor at dusk.' },
    { id: '1kWlQBIkEBakfsjeddtA6SP-5BznRt3mD', name: 'Signature Cocktails', desc: 'Crafted with precision and passion.' },
    { id: '1yDSzcUI4N9dUCfcJSM7kENp5IKvrUkSu', name: 'Chef at Work', desc: 'A glimpse into the artistry of our chefs.' },
    { id: '1OWpTtDj6Pe93BZuuLyMresLuK5Ljh1fV', name: 'Live Music Night', desc: 'Unforgettable performances at La-Medusaa.' },
    { id: '1F4k7SJtZ8JuAf-N7dCcaP2z1sJn3vpey', name: 'Plating Mastery', desc: 'Every dish crafted like a work of art.' },
    { id: '1HYEqtVEzg8e7wg-Ahi78uSs7GnwOlSGL', name: 'Weekend Rush', desc: 'The energy of a full-house Saturday night.' },
    { id: '1AuTXXVFc4vJ7QWyVf0IwdgKz2M4u8BqT', name: 'Dining Experience', desc: 'An evening to remember.' },
    { id: '1s5rSwd-Yoj7RH-NcfnTKYZxUGWCSvCkq', name: 'Bar Service', desc: 'Our expert bartenders in action.' },
    { id: '1i-USwo7UmLAWdHcvcyksJgNyBzkjOd_s', name: 'Outdoor Garden', desc: 'Dining under the stars in our garden terrace.' },
    { id: '1q6KEFuCfFyz0DKBERh2nBXhToQrPFZJ6', name: 'Special Event', desc: 'A special celebration at La-Medusaa.' },
    { id: '14OL5-D_lDU3H75GlAlPMdjXuJYCgiqRy', name: 'Morning Prep', desc: 'The dedication that starts before dawn.' },
    { id: '1YK3fgfnsajiZz7o4qBvbYK4ozJuhV62h', name: 'Fresh Ingredients', desc: 'Only the finest, sourced with care.' },
    { id: '1u03aSsdgYPVlYrKgiRXxB5bmeyjagO38', name: 'Table Setting', desc: 'Every detail matters at La-Medusaa.' },
    { id: '1f-8YuLYunrrEt1g-BV5RikWDz4IRsVsY', name: 'Family Feast', desc: 'Where every meal feels like home.' },
    { id: '1FK97XqagLaDBB-HCM-VBaB0g7k9HnCc1', name: 'Sunset Lounge', desc: 'Golden hour at the lounge.' },
    { id: '1-rXqhvo9IZZaOu5Il9_LOXKmuh_EObH2', name: 'Seasonal Menu', desc: 'Fresh flavors for every season.' },
    { id: '1A4YH4YA3zACY6EAkgX9D5XXnChJTsXGA', name: 'Dessert Showcase', desc: 'Sweet endings crafted with love.' },
    { id: '1cXouGF92y39ziK1dg1SbB122pveJACyj', name: 'Wine Selection', desc: 'Curated wines from around the world.' },
    { id: '1_ZFqeeDZB0rLcxc0vZrgW4ptXTGfkSNT', name: 'Private Dining', desc: 'Exclusive spaces for intimate evenings.' },
    { id: '1XtWQrZHWRFcqj48aquJNgouR6xRtjBvm', name: 'Sushi Night', desc: 'Japanese-inspired creations.' },
    { id: '1BGnOHcrY_34AWoiMtaWCSffAolgP74J3', name: "Chef's Table", desc: 'An immersive culinary journey.' },
    { id: '1SPrwMuI4n6DkdDf_uitEFBuQ4op-g1X-', name: 'Craft Beer', desc: 'Artisanal pours for every taste.' },
    { id: '163DPv3J9_zzLWjo2l9mh99Zu6k4XVyEm', name: 'Brunch Vibes', desc: 'Lazy mornings elevated.' },
    { id: '1mw1bxa_Po0PZDlR773lUYiTo17XBE1_6', name: 'Cocktail Making', desc: 'The art of the perfect mix.' },
    { id: '18z7yT7enSKG6_uOOwXzaAfE222xOGaJP', name: 'Anniversary Night', desc: 'Celebrating love and milestones.' },
    { id: '1hHxPYHMNLdEQAlWTd2v_pHGFVGgohjpM', name: 'Festival Season', desc: 'Festive menus and special setups.' },
    { id: '1zEXfFY4qLee8BGibXqMvHI1aDHO5ugu6', name: 'Staff Stories', desc: 'The people who make it all happen.' },
    { id: '1A2d7fW-NKFBAYVb7BuLEv2GH5HIYnWBt', name: 'Fire Cooking', desc: 'Flames and flavors in perfect harmony.' },
    { id: '109To4UOlYGtYNVr-sjIS6ofZpN8kVW7Q', name: 'Lounge Moments', desc: 'Relaxed evenings in our lounge.' },
    { id: '1Wc14Esg2iyMJsao2bDY4AcQjYQv5yGTC', name: 'Night Service', desc: 'Late evening service in full swing.' },
    { id: '1IewQcjSOynZRLwhz4yAz1WyrCmU_p_H', name: 'Taste of Mohali', desc: 'Local flavours, elevated to new heights.' },
    { id: '1R_AJcCe42k2zZ7fD9BlGJGymFxWdBPcg', name: 'Rooftop Evenings', desc: 'Breezy nights with city views.' },
    { id: '1y0sSsCOzM5hWNeFLrbwr8BL3HZFZn0AQ', name: 'Mixology Lab', desc: 'Our bartenders at their creative best.' },
    { id: '1GaVXkBd5qh84xYZooL7Ig02xKQF-o0tr', name: 'Full House Friday', desc: 'The electric buzz of a packed Friday night.' },
    { id: '1YICyTpM3kAt1wwiQSThUkBh5i-4hDGgO', name: 'Smoke & Grill', desc: 'Char-grilled specialties fresh off the fire.' },
    { id: '1BOavgyrEiRkmAwT5qNt9mJ7TErEy6W-a', name: 'VIP Lounge', desc: 'Elevated privacy for special guests.' },
    { id: '1LKEl5jZ_N-OoaLLR0ZOj5gkBqIIVCIzl', name: 'Harvest Table', desc: 'Seasonal produce showcased beautifully.' },
    { id: '1C4eSaI6y25IlUDv2_13mjvaUZlzmMaYu', name: 'Espresso Bar', desc: 'Perfectly pulled shots, every time.' },
    { id: '1Y5j5KQXx9xN8zq-TpgVK_Mj83qgqDEH2', name: 'Sunday Brunch', desc: 'A leisurely afternoon with great food.' },
    { id: '1KMml9q-GGMPHwt5sBP8BC1orx0N6jC2s', name: 'Bread & Butter', desc: 'House-made breads baked fresh daily.' },
    { id: '1UhwBGnbgh9WS-WRMvjONyfnuSUkvqkWr', name: 'Artisan Ice Cream', desc: 'Handcrafted scoops in every colour.' },
    { id: '1_aiNP05DX0Rg_i_JNfjLgnkOXb3zqke0', name: 'Chef\'s Garden', desc: 'Fresh herbs from our own kitchen garden.' },
    { id: '1pob9QKkOrKR0scFEp3cg4fjUKq5_yhA3', name: 'Teppanyaki', desc: 'Live cooking theatre at its finest.' },
    { id: '1laGxGZuCjtIN5Txc8kCEtVPP9x1cPihm', name: 'Candlelit Dinner', desc: 'Romance in every flicker of light.' },
    { id: '1c_Fc438hwZTfIYpzP5aMjsofCvdjL-Wc', name: 'The Grand Open', desc: 'Reliving the magic of our opening night.' },
    { id: '15UwbDMElIUKCl4I-NqNjigIZsdzWu71B', name: 'Pasta Station', desc: 'Hand-rolled pasta made with love.' },
    { id: '1G_ehPwgFGvryKs1nVvZvA2VL1r5izf4a', name: 'Dim Sum Trolley', desc: 'Traditional dim sum with a modern twist.' },
    { id: '1m5ZC4yssatlevtsusx5w7R7ZC7kX86MQ', name: 'Saffron Risotto', desc: 'Golden, creamy perfection in every spoon.' },
    { id: '1KNA7sztdjsT48rtLymZ8kKx7SQaX7bwK', name: 'Live DJ Night', desc: 'Music that makes the night come alive.' },
    { id: '1FUVHd8xF9G_PEGizVymJY-Nhn-l7LVRo', name: 'Patio Dining', desc: 'Open-air dining with ambient lighting.' },
    { id: '1qFpWWKPLsSQ9Ejswv3TpLzb60mYxDnSu', name: 'Cheese Board', desc: 'Curated selection for the discerning palate.' },
    { id: '1xhPSQ0Ni9if-8pOBFGCJ_viVB5huizXl', name: 'Tasting Menu', desc: 'A journey through our finest creations.' },
    { id: '1ixAHrLbIPs7DOKoEfDlOVIGGpvDkmKZb', name: 'Terrace Sunsets', desc: 'Magical golden hour on the terrace.' },
    { id: '1vaGNC3_yh67wus4FpCv7VyojsJURrEPg', name: 'Heritage Recipe', desc: 'A century-old recipe, reimagined.' },
    { id: '1vrVK928ZFDlUd869X8oa2E3CV56Op0rL', name: 'Fresh Catch', desc: 'Seafood at its ocean-fresh finest.' },
    { id: '18G2r7AclRy7g_jMZBnq6_QLzRL309q4X', name: 'Stir Fry Magic', desc: 'High-heat wok cooking full of drama.' },
    { id: '1E6H3oEWoplglQESl1yp0UTwSB1gREFpT', name: 'Slow Cooked', desc: '48-hour braises, worth every minute.' },
    { id: '1GnBXnA-aM9hPPu9XeFd0QMZTZ1HmruxV', name: 'Garden Salads', desc: 'Farm-to-table freshness on every plate.' },
    { id: '1yo2LGVpPPN2axQI59AmiduIifCaaRtIx', name: 'Signature Burger', desc: 'Our famous gourmet burger, up close.' },
    { id: '1IBM0Q1kAbIDxYrcpwlvILJiJksFYsvyP', name: 'Noodle Night', desc: 'Asian-inspired noodles, tossed to perfection.' },
    { id: '128dVJysXkbMbPqgTfK0Graic7_EPlm4f', name: 'Stone Oven Pizza', desc: 'Wood-fired perfection, slice by slice.' },
    { id: '1jfWkMNHsQ1CRJr83ZxiWVt60TD1aJp1Y', name: 'Craft Lemonade', desc: 'Housemade lemonades in vibrant flavours.' },
    { id: '14-wREA7ZBvcRTuFLVmAaB_2eH7vSXj7z', name: 'Fondue Night', desc: 'Melted cheese and great company.' },
    { id: '1N2HcQMSTsT9ciebqZ4Sjsyl6fQS1bVWM', name: 'Sushi Roll Art', desc: 'Each roll a miniature masterpiece.' },
    { id: '14BgLkL_VOeONqIIXA7Jo_47yHdxX7BGI', name: 'Tapas Evening', desc: 'Small bites, big flavours, great conversations.' },
    { id: '1k9GW21WkG3zsRzJMmQG0d8YHpQQDHLZo', name: 'Birthday Bash', desc: 'Celebrating milestones at La-Medusaa.' },
    { id: '1DbMtqgqaI-GyW3JupNJk7Sq4iaA5gYV3', name: 'Staff Awards', desc: 'Honouring the team behind every great meal.' },
    { id: '1hJTIUwaCS-8eIwlKnXlBOCpnSCiNznlO', name: 'Jazz Evening', desc: 'Smooth jazz and fine dining, perfectly paired.' },
    { id: '1QB-kYg3Z0ncBEtNCDqUIXUedN6ZeF2bL', name: 'Mango Season', desc: 'A tribute to the king of fruits.' },
    { id: '1L8vDyRjuOVZpwidD1Rg6c7KB5NbHGkiJ', name: 'Pottery & Plates', desc: 'Handcrafted crockery, made for our kitchen.' },
    { id: '1T4ZvcieXApAbo_F736y-IPHXRR0Fz0oS', name: 'Truffle Season', desc: 'Rare, earthy luxury on your plate.' },
    { id: '1E-Rapt0gFn81dQ3oWjfAuletaUTi53Y4', name: 'New Year Gala', desc: 'Ringing in the new year in grand style.' },
    { id: '1xGnPV6-BqvtcPuBZapaona-Ew2hi99UV', name: 'Grand Finale', desc: 'The perfect close to a perfect evening.' },
];

// ═══════════════════ STATE ═══════════════════
let currentTab    = 'photos';
let lbCurrentIdx  = 0;
let visiblePhotos = 16;
let visibleVideos = 9;
const PHOTO_BATCH = 12;
const VIDEO_BATCH = 6;

function thumbUrl(id)  { 
    if (id.startsWith('assets/')) return id;
    return `https://drive.google.com/thumbnail?id=${id}&sz=w800`; 
}
function highResUrl(id){ 
    if (id.startsWith('assets/')) return id;
    return `https://drive.google.com/thumbnail?id=${id}&sz=w2000`; 
}
function driveViewUrl(id){ return `https://drive.google.com/file/d/${id}/preview`; }

// ═══════════════════ PHOTOS ═══════════════════
let activeCategory = 'all';

function renderPhotos() {
    const grid = document.getElementById('photosGrid');
    // Use server-managed manifest if available
    const manifestImages = (window.GALLERY_MANIFEST || []).filter(i => i.type === 'image');
    if (manifestImages.length > 0) {
        const shown = manifestImages.slice(0, visiblePhotos);
        grid.innerHTML = shown.map((it, i) => `
            <div class="photo-item" data-idx="${i}" onclick="openLightbox(${i})">
                <img src="/${it.file}" alt="Gallery photo ${i+1}" loading="lazy" onerror="this.parentElement.style.display='none'">
                <div class="photo-overlay">
                    <div class="photo-overlay-inner">
                        <div class="photo-expand-icon"><i class="fas fa-expand-alt"></i></div>
                    </div>
                </div>
            </div>
        `).join('');

        document.getElementById('photoLoadMore').style.display =
            visiblePhotos >= manifestImages.length ? 'none' : 'block';
        return;
    }

    // Fallback to Zomato shoot photos
    let filtered = PHOTO_IDS;
    if (activeCategory !== 'all') {
        filtered = PHOTO_IDS.filter(p => p.category === activeCategory);
    }

    const shown = filtered.slice(0, visiblePhotos);
    grid.innerHTML = shown.map((photo, i) => {
        const id = photo.id;
        return `
            <div class="photo-item" data-idx="${i}" onclick="openLightbox(${i})">
                <img
                    src="${thumbUrl(id)}"
                    alt="Gallery photo ${i+1}"
                    loading="lazy"
                    onerror="this.parentElement.style.display='none'"
                >
                <div class="photo-overlay">
                    <div class="photo-overlay-inner">
                        <div class="photo-expand-icon"><i class="fas fa-expand-alt"></i></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    document.getElementById('photoLoadMore').style.display =
        visiblePhotos >= filtered.length ? 'none' : 'block';
}

function loadMorePhotos() {
    let filtered = PHOTO_IDS;
    if (activeCategory !== 'all') {
        filtered = PHOTO_IDS.filter(p => p.category === activeCategory);
    }
    visiblePhotos = Math.min(visiblePhotos + PHOTO_BATCH, filtered.length);
    renderPhotos();
}

function filterPhotos(cat, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeCategory = cat.toLowerCase();
    visiblePhotos = 16;
    renderPhotos();