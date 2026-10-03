/**
 * Sikkim Gaming Platform - Plain JavaScript
 * STRICT RULE: No framework, no sticky/fixed behavior, no simulated fake logic.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Banner Slider Logic
    initBannerSlider();

    // Auto-dismiss alerts after 5 seconds if present
    setTimeout(function () {
        document.querySelectorAll('.flash-message').forEach(function (el) {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        });
    }, 5000);
});

function initBannerSlider() {
    const slides = document.querySelectorAll('.banner-slide');
    const dots = document.querySelectorAll('.banner-dot');
    if (!slides.length) return;

    let currentIndex = 0;
    let autoSlideInterval = null;

    function showSlide(index) {
        if (index >= slides.length) index = 0;
        if (index < 0) index = slides.length - 1;

        slides.forEach(function (slide, idx) {
            if (idx === index) {
                slide.classList.remove('hidden');
                slide.classList.add('block');
            } else {
                slide.classList.remove('block');
                slide.classList.add('hidden');
            }
        });

        dots.forEach(function (dot, idx) {
            if (idx === index) {
                dot.classList.add('bg-blue-600', 'w-6');
                dot.classList.remove('bg-slate-300', 'w-2');
            } else {
                dot.classList.remove('bg-blue-600', 'w-6');
                dot.classList.add('bg-slate-300', 'w-2');
            }
        });

        currentIndex = index;
    }

    function startAutoSlide() {
        stopAutoSlide();
        autoSlideInterval = setInterval(function () {
            showSlide(currentIndex + 1);
        }, 5000);
    }

    function stopAutoSlide() {
        if (autoSlideInterval) clearInterval(autoSlideInterval);
    }

    dots.forEach(function (dot, idx) {
        dot.addEventListener('click', function () {
            showSlide(idx);
            startAutoSlide();
        });
    });

    const bannerContainer = document.getElementById('banner-container');
    if (bannerContainer) {
        bannerContainer.addEventListener('mouseenter', stopAutoSlide);
        bannerContainer.addEventListener('mouseleave', startAutoSlide);
    }

    // Initialize first slide
    showSlide(0);
    startAutoSlide();
}

function copyToClipboard(text, successBtn) {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(text).then(function () {
        if (successBtn) {
            const originalText = successBtn.innerText;
            successBtn.innerText = 'Copied!';
            setTimeout(function () {
                successBtn.innerText = originalText;
            }, 1500);
        }
    });
}
