// Simple Dark Mode Toggle - No Auto-Detection
function toggleDarkMode() {
    const body = document.body;
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || body.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const icons = document.querySelectorAll('#darkModeIcon, .fa-moon, .fa-sun');
    icons.forEach(icon => {
        if (newTheme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });

    // Tap Animation
    const btn = document.querySelector('.theme-toggle');
    if (btn) {
        btn.style.transform = 'scale(0.8) rotate(45deg)';
        setTimeout(() => {
            btn.style.transform = '';
        }, 200);
    }
}

function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    const html = document.documentElement;
    const body = document.body;
    const icons = document.querySelectorAll('#darkModeIcon, .fa-moon, .fa-sun');
    
    html.setAttribute('data-theme', savedTheme);
    body.setAttribute('data-theme', savedTheme);
    
    icons.forEach(icon => {
        if (savedTheme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });
}

document.addEventListener('DOMContentLoaded', applySavedTheme);
// Immediate execution to prevent flash
applySavedTheme();
