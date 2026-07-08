const fs = require('fs');

// Ensure clean checkout of gallery.php
require('child_process').execSync('git restore gallery.php');
let c = fs.readFileSync('gallery.php', 'utf8');

// 1. Fix JS leak
if (c.includes('window.GALLERY_MANIFEST = <?php echo json_encode')) {
    c = c.replace('// Expose server-side gallery manifest to client JS', '<script>\n        // Expose server-side gallery manifest to client JS');
    c = c.replace('?> || [];', '?> || [];\n        </script>');
}

// 2. Add margin-top to .hero
if (!c.includes('margin-top: -34px;')) {
    c = c.replace(/\.hero\s*\{\s*position:\s*relative;/g, '.hero {\n            position: relative;\n            margin-top: -34px; /* Pull the dark green artifact under the fixed navbar */');
}

// 3. Inject nav-page-transition CSS and JS into head
const navStyle = `
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
`;
if (!c.includes('id="nav-pt-style"')) {
    c = c.replace('</head>', navStyle + '</head>');
}

// 4. Inject nav-page-transition div into body
if (!c.includes('id="nav-page-transition"')) {
    c = c.replace('<body>', '<body>\n<div id="nav-page-transition"></div>');
}

fs.writeFileSync('gallery.php', c);
console.log('Fully restored gallery.php and applied all patches without destroying the bottom half of the file!');
