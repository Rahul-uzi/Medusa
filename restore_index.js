const fs = require('fs');

require('child_process').execSync('git restore index.html');
let c = fs.readFileSync('index.html', 'utf8');

// Inject nav-page-transition CSS and JS into head
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

// Inject nav-page-transition div into body
if (!c.includes('id="nav-page-transition"')) {
    c = c.replace('<body>', '<body>\n<div id="nav-page-transition"></div>');
}

fs.writeFileSync('index.html', c);
console.log('Restored index.html and reapplied page transition code.');
