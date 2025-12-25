// Load theme immediately - BEFORE page renders
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();

// Dark Mode Toggle Function
function toggleDarkMode() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Set theme on HTML element
    html.setAttribute('data-theme', newTheme);
    
    // Save to localStorage
    localStorage.setItem('theme', newTheme);
    
    // Add fade animation
    document.body.style.transition = 'opacity 0.15s ease';
    document.body.style.opacity = '0.95';
    
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 150);
}

// Apply transitions after page load
document.addEventListener('DOMContentLoaded', function() {
    // All elements should transition smoothly
    const style = document.createElement('style');
    style.textContent = `
        html, body, [data-theme] {
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease !important;
        }
    `;
    document.head.appendChild(style);
});
