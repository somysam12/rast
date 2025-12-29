(function() {
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img[data-lazy]').forEach(img => {
            img.src = img.dataset.lazy;
        });
    } else {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.lazy;
                    imageObserver.unobserve(img);
                }
            });
        });
        document.querySelectorAll('img[data-lazy]').forEach(img => imageObserver.observe(img));
    }
})();
