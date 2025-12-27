<?php if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.'); ?>

<!-- Global Lightbox -->
<div id="globalLightbox" class="lightbox-overlay" style="display: none;">
    <button class="lightbox-close" aria-label="Close">&times;</button>
    <div class="lightbox-container">
        <img id="lightboxImage" src="" alt="Full view">
    </div>
</div>

<style>
.lightbox-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
    transition: opacity 0.3s ease;
}

.lightbox-container {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.lightbox-container img {
    max-width: 100%;
    max-height: 90vh;
    display: block;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    background: #fff;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 30px;
    background: none;
    border: none;
    color: white;
    font-size: 50px;
    cursor: pointer;
    line-height: 1;
    transition: transform 0.2s;
    user-select: none;
    z-index: 100000;
}

.lightbox-close:hover {
    transform: scale(1.1);
    color: #ffc107;
}

body.lightbox-open {
    overflow: hidden;
}
</style>

<script>
(function() {
    const lightbox = document.getElementById('globalLightbox');
    const lightboxImg = document.getElementById('lightboxImage');
    const closeBtn = lightbox.querySelector('.lightbox-close');

    function openLightbox(src) {
        lightboxImg.src = src;
        lightbox.style.display = 'flex';
        document.body.classList.add('lightbox-open');
    }

    function closeLightbox() {
        lightbox.style.display = 'none';
        lightboxImg.src = '';
        document.body.classList.remove('lightbox-open');
    }

    // Use event delegation to handle all images, including dynamic ones
    document.addEventListener('click', function(e) {
        const target = e.target;
        
        // Target images that aren't inside the lightbox already
        if (target.tagName === 'IMG' && !target.closest('.lightbox-overlay')) {
            // Optional: Exclude very small images (icons/avatars in nav)
            if (target.naturalWidth < 50 && target.naturalHeight < 50) return;
            
            openLightbox(target.src);
        }
    });

    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightbox.style.display === 'flex') {
            closeLightbox();
        }
    });
})();
</script>
