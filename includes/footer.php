<?php
// Shared Footer Component - LA-MEDUSAA Bar & Lounge
// Include this file at the bottom of every page: include_once __DIR__ . '/includes/footer.php';
?>
<style>
    /* ── Luxury Footer ── */
    .lux-footer {
        background: linear-gradient(160deg, #1e0a10 0%, #2d0f18 60%, #1e0a10 100%);
        color: white;
        padding: 64px 40px 0;
        margin-top: 0;
        border-top: 1px solid rgba(200, 162, 90, 0.15);
        font-family: 'Jost', 'Plus Jakarta Sans', sans-serif;
    }

    .lux-footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 48px;
        max-width: 1200px;
        margin: 0 auto;
        border-bottom: 1px solid rgba(200, 162, 90, 0.12);
        padding-bottom: 48px;
    }

    .lux-footer h4 {
        color: #C8A25A;
        margin-bottom: 18px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        font-family: 'Jost', sans-serif;
    }

    .lux-footer p,
    .lux-footer address {
        color: rgba(248, 234, 206, 0.55);
        font-size: 0.85rem;
        line-height: 1.8;
        margin-bottom: 8px;
        font-style: normal;
    }

    .lux-footer a {
        color: rgba(248, 234, 206, 0.55);
        font-size: 0.85rem;
        line-height: 1.8;
        text-decoration: none;
        display: block;
        transition: color 0.2s;
        margin-bottom: 6px;
    }

    .lux-footer a:hover {
        color: #C8A25A;
    }

    .lux-footer-socials {
        display: flex;
        gap: 12px;
        margin-top: 22px;
    }

    .lux-footer-socials a {
        color: rgba(248, 234, 206, 0.7);
        background: rgba(200, 162, 90, 0.08);
        border: 1px solid rgba(200, 162, 90, 0.2);
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        text-decoration: none;
        transition: all 0.25s;
        font-size: 0.85rem;
        margin-bottom: 0;
    }

    .lux-footer-socials a:hover {
        background: #C8A25A;
        border-color: #C8A25A;
        color: #1e0a10;
        transform: translateY(-2px);
    }

    .lux-footer-newsletter-form {
        display: flex;
        margin-top: 14px;
        border: 1px solid rgba(200, 162, 90, 0.25);
        border-radius: 4px;
        overflow: hidden;
    }

    .lux-footer-newsletter-form input {
        background: transparent;
        border: none;
        padding: 10px 14px;
        color: white;
        width: 100%;
        outline: none;
        font-size: 0.82rem;
        font-family: 'Jost', sans-serif;
    }

    .lux-footer-newsletter-form input::placeholder {
        color: rgba(248, 234, 206, 0.3);
    }

    .lux-footer-newsletter-form button {
        background: #C8A25A;
        border: none;
        color: #1e0a10;
        padding: 10px 16px;
        cursor: pointer;
        transition: background 0.2s;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .lux-footer-newsletter-form button:hover {
        background: #ddb96b;
    }

    .lux-footer-bottom {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
        padding: 22px 0;
        color: rgba(248, 234, 206, 0.25);
        font-size: 0.78rem;
        letter-spacing: 0.5px;
    }
</style>

<footer class="lux-footer">
    <div class="lux-footer-grid">

        <!-- Brand Column -->
        <div>
            <img src="assets/images/logo_right.png" alt="Medusa Logo" style="width: 200px; height: auto; margin: 0 0 16px 0; display: block;">
            <p>Experience culinary excellence, handcrafted cocktails, and unforgettable moments.</p>
            <div class="lux-footer-socials">
                <a href="https://www.instagram.com/la_medusaa_mohali" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                <a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>

        <!-- Navigation Column -->
        <div>
            <h4>Navigation</h4>
            <a href="index.html">Home</a>
            <a href="menutest.html">Menu</a>
            <a href="book-table-test.html">Book Table</a>
            <a href="about.html">About Us</a>
            <a href="career.html">Careers</a>
            <a href="contact.html">Contact Us</a>
        </div>

        <!-- Legal Column -->
        <div>
            <h4>Legal</h4>
            <a href="privacy-policy.html">Privacy Policy</a>
            <a href="terms-and-conditions.html">Terms &amp; Conditions</a>
        </div>

        <!-- Contact Column -->
        <div>
            <h4>Contact</h4>
            <p><i class="fa-solid fa-location-dot" style="margin-right:8px; color:#C8A25A;"></i>SCO 44-45, Sector 68, SAS Nagar<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Mohali, Punjab 160308</p>
            <p><i class="fa-solid fa-phone" style="margin-right:8px; color:#C8A25A;"></i>+91 84272 27398</p>
            <p><i class="fa-regular fa-envelope" style="margin-right:8px; color:#C8A25A;"></i>contact@medusa.com</p>
            <p style="margin-top: 10px;"><a href="#" style="color:#C8A25A; display:inline;"><i class="fa-solid fa-location-dot me-2"></i>View on Map &rarr;</a></p>
        </div>

        <!-- Newsletter Column -->
        <div>
            <h4>Newsletter</h4>
            <p>Stay updated with our latest events and exclusive offers.</p>
            <div class="lux-footer-newsletter-form">
                <input type="email" placeholder="Enter your email" aria-label="Email for newsletter">
                <button type="button" aria-label="Subscribe"><i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

    </div>
    <div class="lux-footer-bottom">
        &copy; 2026 Medusa Restaurant, Bar &amp; Lounge. All rights reserved.
    </div>
</footer>
