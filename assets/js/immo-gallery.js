(function() {
    'use strict';

    /* =========================================================================
       Slider mit Thumbnails, Pfeilen, Counter
       ========================================================================= */
    function initGallery(root) {
        var slides    = root.querySelectorAll('.immo-slide');
        var thumbs    = root.querySelectorAll('.immo-thumb');
        var prev      = root.querySelector('.immo-nav-prev');
        var next      = root.querySelector('.immo-nav-next');
        var counter   = root.querySelector('.immo-slide-counter');
        var current   = 0;
        var total     = slides.length;
        if (!total) return;

        function go(idx) {
            current = (idx + total) % total;
            slides.forEach(function(s, i) {
                s.classList.toggle('is-active', i === current);
            });
            thumbs.forEach(function(t, i) {
                t.classList.toggle('is-active', i === current);
            });
            if (counter) counter.textContent = (current + 1) + ' / ' + total;
        }

        if (prev) prev.addEventListener('click', function(e) { e.preventDefault(); go(current - 1); });
        if (next) next.addEventListener('click', function(e) { e.preventDefault(); go(current + 1); });

        thumbs.forEach(function(t, i) {
            t.addEventListener('click', function(e) {
                e.preventDefault();
                go(i);
            });
        });

        // Bild-Klick öffnet Lightbox (verwendet die url-Daten der Slides).
        slides.forEach(function(s, i) {
            s.addEventListener('click', function() {
                openLightbox(root, current);
            });
        });

        // Tastatur-Navigation, wenn Galerie fokussiert ist.
        root.tabIndex = 0;
        root.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); go(current - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); go(current + 1); }
        });

        go(0);

        // Aktuellen Index für Lightbox-Sync außen verfügbar machen.
        root._immoGetIndex = function() { return current; };
        root._immoSetIndex = function(idx) { go(idx); };
    }

    /* =========================================================================
       Lightbox
       ========================================================================= */
    var lightboxEl, lbImg, lbCounter, lbCurrent = 0, lbItems = [], lbSourceRoot = null;

    function ensureLightbox() {
        if (lightboxEl) return;
        lightboxEl = document.createElement('div');
        lightboxEl.className = 'immo-lightbox';
        lightboxEl.setAttribute('role', 'dialog');
        lightboxEl.setAttribute('aria-modal', 'true');
        lightboxEl.innerHTML =
            '<button type="button" class="immo-lb-close" aria-label="Schließen">&times;</button>' +
            '<button type="button" class="immo-lb-prev" aria-label="Vorheriges Bild">&#8249;</button>' +
            '<button type="button" class="immo-lb-next" aria-label="Nächstes Bild">&#8250;</button>' +
            '<figure class="immo-lb-figure"><img class="immo-lb-img" alt=""></figure>' +
            '<div class="immo-lb-counter"></div>';
        document.body.appendChild(lightboxEl);
        lbImg     = lightboxEl.querySelector('.immo-lb-img');
        lbCounter = lightboxEl.querySelector('.immo-lb-counter');

        lightboxEl.querySelector('.immo-lb-close').addEventListener('click', closeLightbox);
        lightboxEl.querySelector('.immo-lb-prev').addEventListener('click', function() { lbGo(lbCurrent - 1); });
        lightboxEl.querySelector('.immo-lb-next').addEventListener('click', function() { lbGo(lbCurrent + 1); });

        lightboxEl.addEventListener('click', function(e) {
            if (e.target === lightboxEl) closeLightbox();
        });

        document.addEventListener('keydown', function(e) {
            if (!lightboxEl.classList.contains('is-open')) return;
            if (e.key === 'Escape')      closeLightbox();
            if (e.key === 'ArrowLeft')  lbGo(lbCurrent - 1);
            if (e.key === 'ArrowRight') lbGo(lbCurrent + 1);
        });
    }

    function openLightbox(root, startIndex) {
        ensureLightbox();
        lbSourceRoot = root;
        lbItems = Array.prototype.map.call(root.querySelectorAll('.immo-slide'), function(s) {
            return {
                src: s.getAttribute('data-large') || s.querySelector('img').src,
                alt: s.querySelector('img').alt || ''
            };
        });
        lbGo(startIndex || 0);
        lightboxEl.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function lbGo(idx) {
        if (!lbItems.length) return;
        lbCurrent = (idx + lbItems.length) % lbItems.length;
        lbImg.src = lbItems[lbCurrent].src;
        lbImg.alt = lbItems[lbCurrent].alt;
        lbCounter.textContent = (lbCurrent + 1) + ' / ' + lbItems.length;
        if (lbSourceRoot && lbSourceRoot._immoSetIndex) {
            lbSourceRoot._immoSetIndex(lbCurrent);
        }
    }

    function closeLightbox() {
        if (!lightboxEl) return;
        lightboxEl.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    /* =========================================================================
       Init
       ========================================================================= */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.immo-gallery').forEach(initGallery);
    });
})();
