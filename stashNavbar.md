# Navbar Blueprint and Audit

This document is the single source of truth and blueprint for the restaurant website's navigation bar system. It stores the visual design standard, structural assets, responsiveness rules, and functional behaviors parsed from the reference page `menutest.html`.

---

## 1. Navigation Links & Authentication Rules

| Link Item | Target URL | Display Condition | Active Highlight Rule |
| :--- | :--- | :--- | :--- |
| **Home** | `index.html` | Always | Path matches `index.html` or `/` |
| **About** | `about.html` | Always | Path matches `about.html` |
| **Menu** | `menutest.html` | Always | Path matches `menutest.html` |
| **Gallery** | `gallery.php` | Always | Path matches `gallery.php` |
| **Book Table** | `book-table-test.html` | Always | Path matches `book-table-test.html` |
| **Careers** | `career.html` | Always | Path matches `career.html` |
| **Contact** | `contact.html` | Guest only (no active session) | Path matches `contact.html` |
| **Login** | `login.html` | Guest only (no active session) | Path matches `login.html` |
| **My Orders** | `my-orders.php` | Authenticated only (active session) | Path matches `my-orders.php` |
| **My Profile** | `profile.php` | Authenticated only (active session) | Path matches `profile.php` |
| **Logout** | `api/logout.php` | Authenticated only (active session) | N/A |

---

## 2. Logo Branding
- **Logo Icon Image**: `assets/images/medusaa2(onlylogo).png`
  - Classes: `w-10 h-10 object-contain brightness-110` (40px dimensions)
- **Brand Title text**: `LA-MEDUSAA`
  - Font: `Cormorant Garamond` (serif)
  - Specs: `text-[1.15rem]` (18.4px), `font-semibold` (weight 600), `text-[#C8A25A]`, letter-spacing `2px`, uppercase, leading-tight
- **Brand Subtitle**: `Bar & Lounge`
  - Specs: `text-[0.58rem]` (9.28px), letter-spacing `4px`, `font-normal` (weight 400), `text-[#C8A25A]/65` (65% opacity)
- **Anchor Link**: Wraps both logo and text, pointing to `index.html` (`no-underline`).

---

## 3. Right-side Controls & Dropdowns

- **Notification Bell (Authenticated)**:
  - Trigger element: `<button>` containing FontAwesome icon `<i class="fa-regular fa-bell"></i>`.
  - Shake class: `.bell-ringing` animation active if unread notifications > 0.
  - Notification Dropdown (`#nav-notif-dropdown`): Absolute positioning `right-0 mt-[12px] w-80`, background `#F8F3EB`, border `1px solid border-[#3B111B]/10`. Shows a scrollable list of recent alerts.
- **Cart Button**:
  - Target: Links to `carttest.html`.
  - Icon: SVG icon with dimensions `w-[18px] h-[18px]`, stroke-width 1.8.
  - Count Badge (`#cartCount`): Absolute position `-top-2 -right-2 bg-[#C8A25A] text-[#3B111B] text-[0.55rem] font-bold rounded-full w-4 h-4 flex items-center justify-center`. Class `hidden` removed if cart items > 0.
- **Profile Icon (Authenticated)**:
  - Icon: `<i class="fa-regular fa-user"></i>`.
  - Profile Dropdown (`#nav-profile-dropdown`): Absolute positioning `right-0 mt-[12px] w-max min-w-[150px] bg-[#F8F3EB] border border-[#3B111B]/10 rounded-2xl`. Contains quick links to My Profile, My Orders, Reservations, Rewards, and Logout.
- **Reserve Table Button**:
  - Selector: `a[href*="book-table"]`
  - Display: Hidden on mobile, visible on desktop/tablet from screen width >= 768px (`hidden md:block`).
  - Styles: `text-[0.72rem]` (11.5px), `font-medium` (weight 500), letter-spacing `2px`, uppercase, color `#C8A25A`, border `1px solid rgba(200, 162, 90, 0.6)`. Hover: background `rgba(200, 162, 90, 0.1)`.
- **Mobile Hamburger Toggle**:
  - Selector: `#nav-mobile-toggle` (visible on screen widths < 1024px `lg:hidden`).
  - Icon: FontAwesome `fa-solid fa-bars` (`text-[#F8EACE]`).

---

## 4. JavaScript Interactions & Features
- **Active Navigation Toggling**:
  - Script checks location path and applies the `.active` class to matching links.
- **Dynamic Cart Badges**:
  - Pulls items from local storage and injects total quantity into badge elements (`#cartCount`, `#cartCountMobileDrawer`). Removes class `hidden` when count > 0.
- **Dropdown Open/Close Toggling**:
  - Handles dropdown visibility by removing/adding class `.hidden`.
- **Outside Click Dismissal**:
  - Closes any active dropdown menu or mobile drawer if the user clicks outside their bounding elements.
- **Mobile Menu Overlay**:
  - Displays the full-height vertical list drawer `#nav-mobile-drawer` when clicking the hamburger toggle.

---

## 5. Responsive Layout Structure

```
- Screen Width >= 1024px (Desktop):
  - Shows: Logo brand, Desktop links, Notification Bell, Cart Icon, Profile Icon, Reserve Table button.
  - Hides: Mobile hamburger toggle, Mobile drawer overlay.

- Screen Width < 1024px (Mobile/Tablet):
  - Shows: Logo brand, Mobile hamburger toggle, Cart Icon (if logged in).
  - Hides: Desktop links, Profile Icon, notification bells, Reserve Table button (unless screen >= 768px where Reserve Table button is shown).
  - Drawer Action: Displays vertical nav overlay (#nav-mobile-drawer) with 95% opacity background backdrop-blur.
```

---

## 6. Visual Design Specifications
- **Navbar container height**: `80px` (`h-20`).
- **Navbar background**: `#3B111B` (deep maroon/burgundy).
- **Divider border**: `1px solid rgba(200, 162, 90, 0.1)` (`border-[#C8A25A]/10`).
- **Standard Link Font**: `Jost`, sans-serif (medium weight 500, size `0.75rem`, letter-spacing `2px`, uppercase, text color `rgba(248, 234, 206, 0.75)`). Hover color `#C8A25A`.
- **Active Link Indicator**: bottom border `2px solid #C8A25A`, padding-bottom `4px`, color `#C8A25A`.
- **Z-Index**: `#main-navbar` runs `z-[100]`, mobile drawer runs `z-[90]`, dropdowns run `z-[110]`.

---

## 7. Current Project Problems Resolved by Reimplementation
- Disparate preflight settings (`preflight: true` vs `preflight: false`) leading to invisible borders and vertical offset displacements.
- Duplicated style files (`index.html` inline styles) causing conflicting font-weights and margin gaps.
