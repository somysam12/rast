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
                side.style.overflowY = 'auto';
                side.style.height = '100vh';
                side.style.position = 'fixed';
                side.style.top = '0';
                side.style.left = '0';
                side.style.zIndex = '1050';
            } else {
                document.body.style.overflow = '';
            }
        }
    };

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
