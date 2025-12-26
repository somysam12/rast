// Global Scroll Position Manager
(function() {
    'use strict';
    
    // Save scroll position before page unload or navigation
    window.addEventListener('beforeunload', function() {
        localStorage.setItem('scrollPos_' + window.location.pathname, window.scrollY);
    });
    
    // Restore scroll position on page load
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const savedPos = localStorage.getItem('scrollPos_' + currentPath);
        
        if (savedPos !== null && savedPos !== 'undefined') {
            // Use requestAnimationFrame for smooth restore
            requestAnimationFrame(() => {
                window.scrollTo(0, parseInt(savedPos));
            });
            
            // Clean up after restore
            setTimeout(() => {
                localStorage.removeItem('scrollPos_' + currentPath);
            }, 500);
        }
    });
    
    // Also restore immediately on direct navigation
    const currentPath = window.location.pathname;
    const savedPos = localStorage.getItem('scrollPos_' + currentPath);
    if (savedPos !== null && savedPos !== 'undefined' && document.readyState === 'loading') {
        window.addEventListener('load', function() {
            window.scrollTo(0, parseInt(savedPos));
            localStorage.removeItem('scrollPos_' + currentPath);
        });
    }
})();
