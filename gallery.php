<?php
/**
 * gallery.php — Premium Photo & Video Gallery
 * Pulls images and videos directly from Google Drive public folders.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/api/config.php';

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moments & Memories — LA-MEDUSAA</title>
    <meta name="description" content="Captured experiences from our kitchen, bar and beyond at La-Medusaa Lounge.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --bg-dark:      #faf7f2;
            --bg-secondary: #faf7f2;
            --card-bg:      #ffffff;
            --gold:         #d9b882;
            --gold-light:   #C8A25A;
            --gold-dim:     rgba(217, 184, 130, 0.35);
            --gold-glow:    rgba(217, 184, 130, 0.08);
            --ivory:        #0b1a13;
            --muted:        rgba(11, 26, 19, 0.65);
            --accent:       #2e1518;
            --border:       rgba(217, 184, 130, 0.25);
            --serif:        'Playfair Display', 'Cormorant Garamond', 'Garamond', 'Palatino Linotype', Georgia, serif;
            --sans:         'Jost', sans-serif;
            --radius:       12px;
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

        /* ═══════════════ HERO ═══════════════ */
        .hero {
            position: relative;
            background: var(--bg-dark);
            padding: 120px 48px 40px;
            text-align: center;
            overflow: hidden;
            border-bottom: 1px solid rgba(217, 184, 130, 0.15);
        }
        .hero-botanical-img {
            position: absolute;
            top: -25px;
            width: 320px;
            height: 380px;
            opacity: 0.35;
            pointer-events: none;
            z-index: 1;
            background-size: contain;
            background-repeat: no-repeat;
            mix-blend-mode: multiply;
            filter: contrast(2.5) brightness(1.05);
            clip-path: inset(0 0 30% 0);
        }
        .hero-botanical-img.left {
            left: -40px;
            background-position: left top;
            transform: rotate(-15deg);
        }
        .hero-botanical-img.right {
            right: -40px;
            background-position: right top;
            transform: scaleX(-1) rotate(-15deg);
        }
        .hero-eyebrow {
            font-size: 0.75rem;
            letter-spacing: 6px;
            text-transform: uppercase;
            color: var(--gold-light);
            margin-bottom: 12px;
            font-weight: 700;
        }
        .hero-title {
            font-family: var(--serif);
            font-size: clamp(3.2rem, 6vw, 5rem);
            font-weight: 700;
            color: var(--ivory);
            line-height: 1.1;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        .gold-divider-ornament {
            max-width: 200px;
            margin: 0 auto 20px;
        }
        .gold-divider-ornament svg {
            width: 100%;
            height: auto;
            display: block;
        }
        .hero-subtitle {
            font-size: 0.92rem;
            font-weight: 500;
            color: rgba(11, 26, 19, 0.85);
            max-width: 500px;
            margin: 0 auto 36px;
            line-height: 1.6;
        }

        /* ═══════════════ FILTER BAR ═══════════════ */
        .filter-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 30px 48px;
            flex-wrap: wrap;
            background: transparent;
        }
        .filter-btn {
            padding: 8px 24px;
            border-radius: 50px;
            border: 1px solid var(--border);
            background: #ffffff;
            font-family: var(--sans);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            color: var(--muted);
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .filter-btn:hover {
            color: var(--gold-light);
            border-color: var(--gold-light);
            transform: translateY(-2px);
        }
        .filter-btn.active {
            background: var(--ivory);
            border-color: var(--ivory);
            color: #ffffff !important;
            font-weight: 700;
            box-shadow: 0 6px 15px rgba(11, 26, 19, 0.15);
        }

        /* ═══════════════ PHOTOS SECTION ═══════════════ */
        .photos-section {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 48px 64px;
        }
        .masonry-grid {
            columns: 4;
            column-gap: 16px;
        }
        .photo-item {
            break-inside: avoid;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            margin-bottom: 16px;
            cursor: pointer;
            background: var(--card-bg);
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px rgba(0,0,0,0.02);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .photo-item:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
            box-shadow: 0 15px 30px rgba(217, 184, 130, 0.08);
        }
        .photo-item img {
            width: 100%;
            display: block;
            object-fit: cover;
            border-radius: 11px;
            transition: transform 0.5s ease;
        }
        .photo-item:hover img {
            transform: scale(1.04);
        }
        .photo-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(11, 26, 19, 0.45) 100%);
            opacity: 0;
            transition: opacity 0.35s ease;
            display: flex;
            align-items: flex-end;
            padding: 16px;
            border-radius: 11px;
        }
        .photo-item:hover .photo-overlay {
            opacity: 1;
        }
        .photo-overlay-inner {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            width: 100%;
        }
        .photo-expand-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ivory);
            font-size: 0.75rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Video specific inside masonry */
        .video-play-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(11, 26, 19, 0.35);
            transition: background 0.3s;
            border-radius: 11px;
        }
        .photo-item:hover .video-play-overlay {
            background: rgba(11, 26, 19, 0.2);
        }
        .play-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.25s, background 0.25s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .photo-item:hover .play-circle {
            transform: scale(1.1);
            background: #ffffff;
        }
        .play-circle i {
            color: #0b1a13;
            font-size: 1rem;
            margin-left: 2px;
        }
        .video-duration-pill {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.65);
            color: #ffffff;
            font-family: var(--sans);
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            z-index: 2;
        }

        /* ═══════════════ LIGHTBOX ═══════════════ */
        .lightbox {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(11, 26, 19, 0.98);
            backdrop-filter: blur(15px);
            display: none; align-items: center; justify-content: center;
        }
        .lightbox.open { display: flex; animation: lbFadeIn 0.3s ease; }
        @keyframes lbFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .lightbox-inner {
            position: relative; max-width: 90vw; max-height: 90vh;
            display: flex; align-items: center; justify-content: center;
        }
        .lightbox-img {
            max-width: 90vw; max-height: 85vh;
            border-radius: 10px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
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
        .lb-arrow:hover { background: rgba(217, 184, 130, 0.25); border-color: var(--gold); color: var(--gold); }
        .lb-prev { left: 20px; }
        .lb-next { right: 20px; }
        .lb-counter {
            position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
            font-size: 0.75rem; color: rgba(255,255,255,0.5); letter-spacing: 2px;
        }

        /* Video Lightbox */
        .video-lightbox {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(11, 26, 19, 0.98);
            backdrop-filter: blur(15px);
            display: none; align-items: center; justify-content: center;
        }
        .video-lightbox.open { display: flex; animation: lbFadeIn 0.3s ease; }
        .vlb-inner {
            position: relative; width: min(850px, 90vw);
        }

        /* ═══════════════ INSTAGRAM BANNER ═══════════════ */
        .insta-banner {
            background: #2e1518;
            padding: 40px 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            flex-wrap: wrap;
            border-top: 1px solid rgba(217, 184, 130, 0.1);
        }
        .insta-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            min-width: 300px;
        }
        .insta-icon {
            width: 58px;
            height: 58px;
            border: 1px solid rgba(217, 184, 130, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--gold);
            flex-shrink: 0;
        }
        .insta-text h3 {
            font-family: var(--serif);
            font-size: 1.4rem;
            color: var(--gold);
            margin-bottom: 4px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        .insta-text p {
            font-family: var(--sans);
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.5;
            margin: 0;
        }
        .insta-text p a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
        }
        .insta-right-tiles {
            display: flex;
            gap: 12px;
            flex-wrap: nowrap;
            overflow-x: auto;
        }
        .insta-tile {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            border: 1.5px solid var(--gold);
            overflow: hidden;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .insta-tile:hover {
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(217, 184, 130, 0.2);
        }
        .insta-tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ═══════════════ FEATURE STRIP ═══════════════ */
        .feature-strip {
            background-color: var(--bg-dark);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 50px 0;
        }
        .feature-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            padding: 0 40px;
        }
        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .feature-icon {
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .feature-icon svg {
            stroke: var(--gold);
        }
        .feature-text h4 {
            font-family: var(--sans);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--ivory);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0 0 6px 0;
        }
        .feature-text p {
            font-family: var(--sans);
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.5;
            margin: 0;
        }

        /* ═══════════════ FOOTER ═══════════════ */
        footer {
            background: #0b1a13;
            padding: 60px 0 30px;
            color: #ffffff;
            font-family: var(--sans);
            position: relative;
            z-index: 2;
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-brand img {
            width: 45px;
            height: auto;
            margin-bottom: 15px;
        }
        .footer-brand-name {
            font-family: var(--serif);
            font-size: 1.1rem;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .footer-brand p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 20px;
            max-width: 280px;
        }
        .footer-social-icons {
            display: flex;
            gap: 12px;
        }
        .footer-social-icons a {
            color: var(--gold);
            font-size: 1rem;
            transition: color 0.3s;
        }
        .footer-social-icons a:hover {
            color: #ffffff;
        }
        .footer-col h4 {
            font-family: var(--sans);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
        }
        .footer-col a, .footer-col p {
            display: block;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            line-height: 1.8;
            margin-bottom: 10px;
            transition: color 0.3s;
        }
        .footer-col a:hover {
            color: var(--gold);
        }
        .footer-col p {
            margin-bottom: 6px;
        }
        .footer-bottom {
            border-top: 1px solid rgba(217, 184, 130, 0.15);
            padding-top: 25px;
            text-align: center;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.4);
            letter-spacing: 0.5px;
        }

        /* ═══════════════ LOADING SPINNER ═══════════════ */
        .load-more-row {
            text-align: center; padding: 20px 0 48px;
        }
        .btn-load-more {
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid var(--gold); background: #ffffff;
            color: var(--gold-light); font-family: var(--sans); font-size: 0.72rem;
            font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            padding: 12px 36px; border-radius: 50px; cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(217, 184, 130, 0.05);
        }
        .btn-load-more:hover {
            background: var(--ivory);
            border-color: var(--ivory);
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(11, 26, 19, 0.12);
        }

        /* ═══════════════ RESPONSIVE ═══════════════ */
        @media (max-width: 1100px) {
            .masonry-grid { columns: 3; }
            .feature-container { grid-template-columns: repeat(2, 1fr); gap: 24px; }
        }
        @media (max-width: 768px) {
            .hero { padding: 90px 24px 40px; }
            .photos-section { padding: 0 20px 48px; }
            .filter-bar { padding: 20px 20px 0; }
            .masonry-grid { columns: 2; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .insta-banner { padding: 30px 24px; }
            .feature-container { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) { .masonry-grid { columns: 1; } }
    </style>

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
    <div class="hero-botanical-img left" style="background-image: url('assets/images/leaf_watermark_gold.png');"></div>
    <div class="hero-botanical-img right" style="background-image: url('assets/images/leaf_watermark_gold.png');"></div>
    
    <div class="hero-container" style="position: relative; z-index: 2;">
        <p class="hero-eyebrow">Gallery</p>
        <h1 class="hero-title">Moments & Memories</h1>
        
        <div class="gold-divider-ornament">
            <svg viewBox="0 0 200 20" xmlns="http://www.w3.org/2000/svg">
                <line x1="0" y1="10" x2="80" y2="10" stroke="var(--gold)" stroke-width="1.5"/>
                <path d="M100,10 C92,3 90,17 100,10 C110,3 108,17 100,10 Z" fill="none" stroke="var(--gold)" stroke-width="1.5"/>
                <circle cx="100" cy="10" r="2" fill="var(--gold)"/>
                <line x1="120" y1="10" x2="200" y2="10" stroke="var(--gold)" stroke-width="1.5"/>
            </svg>
        </div>
        
        <p class="hero-subtitle">Captured experiences from our kitchen, bar and beyond.</p>
    </div>
</section>

<!-- ══════════ FILTER BAR ══════════ -->
<div class="filter-bar" id="filterBar">
    <button class="filter-btn active" onclick="filterGallery('all', this)">All</button>
    <button class="filter-btn" onclick="filterGallery('food', this)">Food</button>
    <button class="filter-btn" onclick="filterGallery('drinks', this)">Drinks</button>
    <button class="filter-btn" onclick="filterGallery('ambience', this)">Ambience</button>
    <button class="filter-btn" onclick="filterGallery('events', this)">Events</button>
    <button class="filter-btn" onclick="filterGallery('videos', this)">Videos</button>
</div>

<!-- ══════════ GALLERY SECTION ══════════ -->
<section class="photos-section" id="gallerySection">
    <div class="masonry-grid" id="galleryGrid">
        <!-- Injected dynamically by JS -->
    </div>
    <div class="load-more-row" id="galleryLoadMore">
        <button class="btn-load-more" onclick="loadMoreItems()">
            <i class="far fa-image" style="margin-right: 8px;"></i> Load More Photos
        </button>
    </div>
</section>

<!-- ══════════ INSTAGRAM BANNER ══════════ -->
<div class="insta-banner">
    <div class="insta-left">
        <div class="insta-icon"><i class="fab fa-instagram"></i></div>
        <div class="insta-text">
            <h3>Share Your Moments With Us</h3>
            <p>Tag us on Instagram <a href="https://www.instagram.com/la_medusaa_mohali?igsh=MXVwcHA3Nm9wbXV1dQ==" target="_blank">@la.medusaa</a>. We'd love to feature your experience.</p>
        </div>
    </div>
    <div class="insta-right-tiles" id="instaTiles">
        <!-- Dynamic Instagram preview tiles loaded in JS -->
    </div>
</div>

<!-- ══════════ FEATURE STRIP ══════════ -->
<section class="feature-strip">
    <div class="feature-container">
        <div class="feature-item">
            <div class="feature-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 3.5 1 9.2a7 7 0 0 1-9 8.8z"/>
                    <path d="M19 2c-2.26 4.33-5.27 7.14-8 10"/>
                </svg>
            </div>
            <div class="feature-text">
                <h4>Fresh Ingredients</h4>
                <p>Sourced daily for the finest taste.</p>
            </div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 18a4 4 0 0 0-1.85-3.27C16.37 13.84 17 12.5 17 11c0-2.76-2.24-5-5-5S7 8.24 7 11c0 1.5.63 2.84 1.85 3.73A4 4 0 0 0 7 18h10z" />
                    <path d="M5 21h14v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1z" />
                </svg>
            </div>
            <div class="feature-text">
                <h4>Expert Chefs</h4>
                <p>Crafting dishes with passion and precision.</p>
            </div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="m9 11 2 2 4-4"/>
                </svg>
            </div>
            <div class="feature-text">
                <h4>Hygienic Kitchen</h4>
                <p>Maintaining the highest standards of hygiene.</p>
            </div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            </div>
            <div class="feature-text">
                <h4>Memorable Experience</h4>
                <p>Creating moments you'll cherish forever.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ FOOTER ══════════ -->
<footer>
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-brand">
                <img src="assets/images/medusaa2(onlylogo).png" alt="La-Medusaa" onerror="this.src='assets/images/medusaa2(onlylogo).png'">
                <div class="footer-brand-name">La-Medusaa</div>
                <p>A premium bar & lounge experience in Sector 67, Mohali. Crafted cocktails, live music, and unforgettable evenings.</p>
                <div class="footer-social-icons">
                    <a href="https://www.instagram.com/la_medusaa_mohali?igsh=MXVwcHA3Nm9wbXV1dQ%3D%3D" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-pinterest"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Navigation</h4>
                <a href="index.html">Home</a>
                <a href="about.html">About Us</a>
                <a href="menutest.php">Menu</a>
                <a href="gallery.php">Gallery</a>
                <a href="book-table-test.html">Book Table</a>
                <a href="career.html">Careers</a>
                <a href="contact.html">Contact Us</a>
            </div>
            <div class="footer-col">
                <h4>Contact Us</h4>
                <p>SCO 44-45, District One Market</p>
                <p>Sector 67, Mohali</p>
                <a href="tel:+911234567890" style="display:inline;margin:0;">+91 12345 67890</a>
                <a href="mailto:info@lamedusaa.com" style="margin-top:10px;">info@lamedusaa.com</a>
                <p style="margin-top:10px; font-size:0.75rem; opacity:0.8;">Mon - Sun : 11:00 AM - 12:00 AM</p>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <a href="privacy-policy.html">Privacy Policy</a>
                <a href="terms-and-conditions.html">Terms & Conditions</a>
                <a href="#">Refund & Cancellation</a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2025 LA-MEDUSAA. All Rights Reserved.
        </div>
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
    { id: '1LDRgiPlYAqmliRkFdLakKtqaWALZDu9M', name: 'Kitchen Vibes', desc: 'Behind the scenes energy from our kitchen.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP00928.jpg' },
    { id: '1NIbSzZqYwbFolu7XtUC9eCoD3CnBnyJU', name: 'Evening Ambiance', desc: 'The warmth of our dining floor at dusk.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP01015.jpg' },
    { id: '1kWlQBIkEBakfsjeddtA6SP-5BznRt3mD', name: 'Signature Cocktails', desc: 'Crafted with precision and passion.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP00966.jpg' },
    { id: '1yDSzcUI4N9dUCfcJSM7kENp5IKvrUkSu', name: 'Chef at Work', desc: 'A glimpse into the artistry of our chefs.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP00930.jpg' },
    { id: '1OWpTtDj6Pe93BZuuLyMresLuK5Ljh1fV', name: 'Live Music Night', desc: 'Unforgettable performances at La-Medusaa.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP01066.jpg' },
    { id: '1F4k7SJtZ8JuAf-N7dCcaP2z1sJn3vpey', name: 'Plating Mastery', desc: 'Every dish crafted like a work of art.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP00940.jpg' },
    { id: '1HYEqtVEzg8e7wg-Ahi78uSs7GnwOlSGL', name: 'Weekend Rush', desc: 'The energy of a full-house Saturday night.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP01060.jpg' },
    { id: '1AuTXXVFc4vJ7QWyVf0IwdgKz2M4u8BqT', name: 'Dining Experience', desc: 'An evening to remember.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP01050.jpg' },
    { id: '1s5rSwd-Yoj7RH-NcfnTKYZxUGWCSvCkq', name: 'Bar Service', desc: 'Our expert bartenders in action.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP00976.jpg' },
    { id: '1i-USwo7UmLAWdHcvcyksJgNyBzkjOd_s', name: 'Outdoor Garden', desc: 'Dining under the stars in our garden terrace.', localThumb: 'assets/Medusa Zomato shoot-20260630T084504Z-3-001/Medusa Zomato shoot/CFP01045.jpg' }
];

// ═══════════════════ STATE ═══════════════════
let galleryItems = [];
let activeCategory = 'all';
let visibleItems = 16;
const ITEMS_BATCH = 12;
let lbCurrentIdx = 0;

function thumbUrl(id)  { 
    if (id.startsWith('assets/')) return id;
    return `https://drive.google.com/thumbnail?id=${id}&sz=w800`; 
}
function highResUrl(id){ 
    if (id.startsWith('assets/')) return id;
    return `https://drive.google.com/thumbnail?id=${id}&sz=w2000`; 
}
function driveViewUrl(id){ return `https://drive.google.com/file/d/${id}/preview`; }

// Merge photos and videos matching the exact reference layout
function buildCombinedItems() {
    let photos = [];
    const manifestImages = (window.GALLERY_MANIFEST || []).filter(i => i.type === 'image');
    if (manifestImages.length > 0) {
        photos = manifestImages.map(it => ({
            type: 'photo',
            id: it.file,
            src: '/' + it.file,
            highRes: '/' + it.file,
            category: it.category || 'food'
        }));
    } else {
        photos = PHOTO_IDS.map(p => ({
            type: 'photo',
            id: p.id,
            src: thumbUrl(p.id),
            highRes: highResUrl(p.id),
            category: p.category
        }));
    }

    let videos = [];
    const manifestVideos = (window.GALLERY_MANIFEST || []).filter(i => i.type === 'video');
    if (manifestVideos.length > 0) {
        videos = manifestVideos.map((it, i) => ({
            type: 'video',
            id: it.file,
            src: '/' + it.file,
            name: it.caption || 'Video',
            desc: '',
            duration: it.duration || '00:30',
            category: 'videos'
        }));
    } else {
        videos = VIDEO_IDS.map((v, i) => {
            let duration = '00:30';
            if (i === 0) duration = '00:28';
            else if (i === 1) duration = '00:45';
            else if (i === 2) duration = '00:31';
            else if (i === 3) duration = '00:22';
            
            return {
                type: 'video',
                id: v.id,
                src: v.localThumb,
                name: v.name,
                desc: v.desc,
                duration: duration,
                category: 'videos'
            };
        });
    }

    let combined = [];
    let photoIdx = 0;
    let videoIdx = 0;
    const totalLength = photos.length + videos.length;
    
    // We loop and weave videos at specific positions (2, 9, 12, 15) to match Reference Design
    for (let i = 0; i < totalLength; i++) {
        if ((i === 2 || i === 9 || i === 12 || i === 15) && videoIdx < videos.length) {
            combined.push(videos[videoIdx++]);
        } else {
            if (photoIdx < photos.length) {
                combined.push(photos[photoIdx++]);
            } else if (videoIdx < videos.length) {
                combined.push(videos[videoIdx++]);
            }
        }
    }
    
    while (photoIdx < photos.length) combined.push(photos[photoIdx++]);
    while (videoIdx < videos.length) combined.push(videos[videoIdx++]);

    galleryItems = combined;
}

// ═══════════════════ RENDER ═══════════════════
function getFilteredItems() {
    if (activeCategory === 'all') {
        return galleryItems;
    } else if (activeCategory === 'videos') {
        return galleryItems.filter(item => item.type === 'video');
    } else {
        return galleryItems.filter(item => item.type === 'photo' && item.category === activeCategory);
    }
}

function renderGallery() {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;

    const filtered = getFilteredItems();
    const shown = filtered.slice(0, visibleItems);

    grid.innerHTML = shown.map((item, i) => {
        if (item.type === 'video') {
            return `
                <div class="photo-item" onclick="openVideoLb('${item.id}')">
                    <img src="${item.src}" alt="${item.name}" loading="lazy" onerror="this.src='assets/images/restaurant_interior.png'">
                    <div class="video-play-overlay">
                        <div class="play-circle"><i class="fas fa-play"></i></div>
                    </div>
                    <div class="video-duration-pill">${item.duration}</div>
                </div>
            `;
        } else {
            // Find global index in photos for lightbox navigation
            const globalPhotoIdx = PHOTO_IDS.findIndex(p => p.id === item.id);
            return `
                <div class="photo-item" onclick="openLightbox(${globalPhotoIdx !== -1 ? globalPhotoIdx : 0})">
                    <img src="${item.src}" alt="Gallery photo" loading="lazy" onerror="this.style.display='none'">
                    <div class="photo-overlay">
                        <div class="photo-overlay-inner">
                            <div class="photo-expand-icon"><i class="fas fa-expand-alt"></i></div>
                        </div>
                    </div>
                </div>
            `;
        }
    }).join('');

    const loadMoreBtn = document.getElementById('galleryLoadMore');
    if (loadMoreBtn) {
        loadMoreBtn.style.display = visibleItems >= filtered.length ? 'none' : 'block';
    }
}

// Fixed loadMoreItems to load ITEMS_BATCH
function loadMoreItems() {
    const filtered = getFilteredItems();
    visibleItems = Math.min(visibleItems + ITEMS_BATCH, filtered.length);
    renderGallery();
}

function filterGallery(cat, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeCategory = cat.toLowerCase();
    visibleItems = 16;
    renderGallery();
}

function renderInstaTiles() {
    const container = document.getElementById('instaTiles');
    if (!container) return;
    const tiles = PHOTO_IDS.slice(0, 5);
    container.innerHTML = tiles.map(t => `
        <div class="insta-tile">
            <img src="${thumbUrl(t.id)}" alt="Instagram preview" loading="lazy">
        </div>
    `).join('');
}

// ═══════════════════ LIGHTBOX ═══════════════════
function openLightbox(idx) {
    lbCurrentIdx = idx;
    const photoList = PHOTO_IDS;
    if (idx < 0 || idx >= photoList.length) return;
    document.getElementById('lbImg').src = highResUrl(photoList[idx].id);
    document.getElementById('lbCounter').textContent = `${idx + 1} / ${photoList.length}`;
    document.getElementById('photoLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeLightbox(e) {
    if (e.target === e.currentTarget) closeLightboxDirect();
}
function closeLightboxDirect() {
    document.getElementById('photoLightbox').classList.remove('open');
    document.body.style.overflow = '';
}
function lbNavigate(dir) {
    const photoList = PHOTO_IDS;
    if (photoList.length === 0) return;
    lbCurrentIdx = (lbCurrentIdx + dir + photoList.length) % photoList.length;
    openLightbox(lbCurrentIdx);
}

document.addEventListener('keydown', e => {
    if (document.getElementById('photoLightbox').classList.contains('open')) {
        if (e.key === 'ArrowLeft')  lbNavigate(-1);
        if (e.key === 'ArrowRight') lbNavigate(1);
        if (e.key === 'Escape')     closeLightboxDirect();
    }
    if (document.getElementById('videoLightbox').classList.contains('open')) {
        if (e.key === 'Escape') closeVideoLbDirect();
    }
});

// ═══════════════════ VIDEO LIGHTBOX ═══════════════════
function openVideoLb(fileId) {
    const vlb = document.getElementById('videoLightbox');
    const inner = vlb.querySelector('.vlb-inner');
    
    if (fileId.startsWith('/') || fileId.includes('.mp4') || fileId.includes('.mov') || fileId.includes('.webm')) {
        inner.innerHTML = `<button class="lb-close" onclick="closeVideoLbDirect()"><i class="fas fa-times"></i></button>
                           <video id="vlbFrame" src="${fileId}" controls autoplay style="width:100%; aspect-ratio: 16/9; border-radius: 12px; border:none; box-shadow: 0 40px 80px rgba(0,0,0,0.8);"></video>`;
    } else {
        inner.innerHTML = `<button class="lb-close" onclick="closeVideoLbDirect()"><i class="fas fa-times"></i></button>
                           <iframe id="vlbFrame" src="${driveViewUrl(fileId)}" allowfullscreen allow="autoplay" style="width:100%; aspect-ratio: 16/9; border-radius: 12px; border:none; box-shadow: 0 40px 80px rgba(0,0,0,0.8);"></iframe>`;
    }
    vlb.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeVideoLb(e) {
    if (e.target === e.currentTarget) closeVideoLbDirect();
}
function closeVideoLbDirect() {
    document.getElementById('videoLightbox').classList.remove('open');
    const inner = document.querySelector('.vlb-inner');
    inner.innerHTML = '';
    document.body.style.overflow = '';
}

// ═══════════════════ INIT ═══════════════════
buildCombinedItems();
renderGallery();
renderInstaTiles();

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('tab') === 'videos') {
    const videoBtn = document.querySelector('.filter-btn[onclick*="videos"]');
    if (videoBtn) filterGallery('videos', videoBtn);
}
</script>
</body>
</html>
