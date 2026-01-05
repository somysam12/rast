document.addEventListener('DOMContentLoaded', function() {
    // Create overlay if it doesn't exist
    if (!document.querySelector('.mobile-overlay')) {
        const newOverlay = document.createElement('div');
        newOverlay.className = 'mobile-overlay';
        newOverlay.id = 'mobile-overlay';
        document.body.appendChild(newOverlay);
    }

    const currentOverlay = document.querySelector('.mobile-overlay');

    window.toggleSidebar = function(e) {
        if (e) {
            if (typeof e.preventDefault === 'function') e.preventDefault();
            if (typeof e.stopPropagation === 'function') e.stopPropagation();
        }
        
        const side = document.querySelector('.sidebar') || document.getElementById('sidebar');
        const over = document.querySelector('.mobile-overlay') || document.getElementById('overlay') || document.getElementById('mobileOverlay');
        
        if (side && over) {
            side.classList.toggle('show');
            over.classList.toggle('show');
            
            if (side.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
                side.style.cssText += 'overflow-y: auto !important; overflow-x: hidden !important; height: 100vh !important; max-height: 100vh !important; position: fixed !important; top: 0 !important; left: 0 !important; z-index: 9999 !important; display: block !important; visibility: visible !important;';
            } else {
                document.body.style.overflow = '';
            }
        }
    };

    // Use global delegation for menu-btn
    document.addEventListener('click', function(e) {
        if (e.target.closest('.menu-btn') || e.target.closest('.mobile-toggle') || e.target.closest('.hamburger-menu')) {
            window.toggleSidebar(e);
        }
    });

    // Global listener for overlay click
    if (currentOverlay) {
        currentOverlay.addEventListener('click', window.toggleSidebar);
    }

    // Close on link click
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 991) {
                const side = document.querySelector('.sidebar') || document.getElementById('sidebar');
                const over = document.querySelector('.mobile-overlay') || document.getElementById('overlay') || document.getElementById('mobileOverlay');
                if (side) side.classList.remove('show');
                if (over) over.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
});
