document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    // Create overlay if it doesn't exist
    if (!overlay) {
        const newOverlay = document.createElement('div');
        newOverlay.className = 'mobile-overlay';
        newOverlay.id = 'mobile-overlay';
        document.body.appendChild(newOverlay);
    }

    const currentOverlay = document.querySelector('.mobile-overlay');

    window.toggleSidebar = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        const side = document.querySelector('.sidebar');
        const over = document.querySelector('.mobile-overlay');
        
        if (side && over) {
            side.classList.toggle('show');
            over.classList.toggle('show');
            
            if (side.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
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
                const side = document.querySelector('.sidebar');
                const over = document.querySelector('.mobile-overlay');
                if (side) side.classList.remove('show');
                if (over) over.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
});
