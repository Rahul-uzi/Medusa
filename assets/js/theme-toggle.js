(function () {
    // Force dark mode permanently
    localStorage.setItem('medusa_admin_theme', 'dark');
    document.documentElement.classList.remove('light-mode');
    if (!window.location.pathname.includes('book-table')) {
        document.documentElement.classList.add('dark');
    } // For Tailwind compatibility
    
    // Load CSS
    const isShared = window.location.pathname.includes('/admintest/');
    const cssPath = isShared ? '../assets/css/theme-toggle.css' : 'assets/css/theme-toggle.css';
    const faPath = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';

    if (!document.querySelector(`link[href$="theme-toggle.css"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = cssPath;
        document.head.appendChild(link);
    }

    if (!document.querySelector(`link[href*="font-awesome"]`) && !document.querySelector(`link[href*="all.min.css"]`)) {
        const fa = document.createElement('link');
        fa.rel = 'stylesheet';
        fa.href = faPath;
        document.head.appendChild(fa);
    }
})();

function updateThemeUI() {
    // UI toggle removed
}

function toggleTheme() {
    // Disabled
}

// Bind load event
document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.remove('light-mode');
    document.documentElement.classList.add('dark');
    if (!window.location.pathname.includes('book-table')) {
        document.body.classList.add('dark-mode'); // For legacy pages
    }
    
    // Remove the toggle button if it exists in HTML
    const btn = document.getElementById('themeToggleBtn');
    if (btn && btn.parentElement) {
        btn.parentElement.remove();
    }
});

// Global Premium Theme Alert Override
(function () {
    window.alert = function (message, callback) {
        // Discard any existing alert modal
        const existing = document.getElementById('customAlertModal');
        if (existing) {
            existing.remove();
        }

        // Create overlay backdrop
        const overlay = document.createElement('div');
        overlay.id = 'customAlertModal';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.22s ease-out;
            padding: 1.5rem;
        `;

        // Check if light mode is active
        const isLight = document.documentElement.classList.contains('light-mode');

        // Create modal content card
        const box = document.createElement('div');
        box.style.cssText = `
            background: ${isLight ? 'rgba(255, 255, 255, 0.96)' : 'linear-gradient(135deg, #1c1a17 0%, #0d0c0a 100%)'};
            border: 1px solid ${isLight ? 'rgba(223, 186, 134, 0.35)' : 'rgba(223, 186, 134, 0.25)'};
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            padding: 2.2rem 2rem;
            box-shadow: ${isLight ? '0 20px 50px rgba(0,0,0,0.08)' : '0 30px 70px rgba(0,0,0,0.8)'};
            transform: scale(0.85);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
            position: relative;
        `;

        // Dynamic icon detection based on message keyword
        let iconHtml = '';
        const msgLower = message.toLowerCase();
        if (msgLower.includes('success') || msgLower.includes('booked') || msgLower.includes('✅') || msgLower.includes('sent')) {
            iconHtml = `<div style="width: 58px; height: 58px; border-radius: 50%; background: rgba(46, 196, 182, 0.1); border: 2px solid #2ec4b6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.2rem; color: #2ec4b6; font-size: 1.6rem;"><i class="fas fa-check"></i></div>`;
        } else if (msgLower.includes('error') || msgLower.includes('fail') || msgLower.includes('denied') || msgLower.includes('invalid') || msgLower.includes('please')) {
            iconHtml = `<div style="width: 58px; height: 58px; border-radius: 50%; background: rgba(239, 68, 68, 0.08); border: 2px solid #ef4444; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.2rem; color: #ef4444; font-size: 1.6rem;"><i class="fas fa-exclamation-triangle"></i></div>`;
        } else {
            iconHtml = `<div style="width: 58px; height: 58px; border-radius: 50%; background: rgba(223, 186, 134, 0.08); border: 2px solid #dfba86; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.2rem; color: #dfba86; font-size: 1.6rem;"><i class="fas fa-info-circle"></i></div>`;
        }

        // Clean emojis from text
        const cleanMessage = message.replace('✅', '').replace('❌', '').trim();

        box.innerHTML = `
            ${iconHtml}
            <div style="font-size: 0.95rem; line-height: 1.6; color: ${isLight ? '#1e293b' : '#f0ece4'}; margin-bottom: 0.5rem; font-weight: 500; font-family: 'Plus Jakarta Sans', sans-serif;">
                ${cleanMessage}
            </div>
        `;

        overlay.appendChild(box);
        document.body.appendChild(overlay);

        // Force browser layout reflow
        overlay.offsetHeight;

        // Transition in
        overlay.style.opacity = '1';
        box.style.transform = 'scale(1)';

        const closeAlert = () => {
            overlay.style.opacity = '0';
            box.style.transform = 'scale(0.85)';
            setTimeout(() => {
                overlay.remove();
                if (typeof callback === 'function') {
                    callback();
                }
            }, 220);
            window.removeEventListener('keydown', handleKeydown);
        };

        const handleKeydown = (e) => {
            if (e.key === 'Enter' || e.key === 'Escape') {
                e.preventDefault();
                closeAlert();
            }
        };

        // Event listeners
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeAlert();
            }
        });

        window.addEventListener('keydown', handleKeydown);

        // Auto-close after 2 seconds
        setTimeout(closeAlert, 2000);
    };
})();
