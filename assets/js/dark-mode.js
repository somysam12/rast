// Simple Dark Mode Toggle - No Auto-Detection
function toggleDarkMode() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update icons on all relevant pages
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
}

// Load saved theme on page load only
function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme');
    const html = document.documentElement;
    const icons = document.querySelectorAll('#darkModeIcon, .fa-moon, .fa-sun');
    
    if (savedTheme === 'dark') {
        html.setAttribute('data-theme', 'dark');
        icons.forEach(icon => {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        });
    } else {
        html.setAttribute('data-theme', 'light');
        icons.forEach(icon => {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        });
    }
}

document.addEventListener('DOMContentLoaded', applySavedTheme);
// Immediate execution to prevent flash
applySavedTheme();
