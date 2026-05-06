(function () {
    if (typeof window.Splide !== 'function') {
        return;
    }

    function readNumber(el, attr, fallback) {
        var v = parseInt(el.getAttribute(attr) || '', 10);
        return isNaN(v) || v < 1 ? fallback : v;
    }

    function init(root) {
        if (root.dataset.immoSliderReady === '1') {
            return;
        }
        root.dataset.immoSliderReady = '1';

        var perPage    = readNumber(root, 'data-per-page', 3);
        var perPageMd  = readNumber(root, 'data-per-page-md', Math.max(1, Math.min(2, perPage)));
        var perPageSm  = readNumber(root, 'data-per-page-sm', 1);
        var gap        = root.getAttribute('data-gap') || '1.5rem';
        var autoplay   = root.getAttribute('data-autoplay') === 'yes';
        var loop       = root.getAttribute('data-loop') !== 'no';

        new Splide(root, {
            type: loop ? 'loop' : 'slide',
            perPage: perPage,
            perMove: 1,
            gap: gap,
            arrows: true,
            pagination: true,
            autoplay: autoplay,
            interval: 5000,
            pauseOnHover: true,
            breakpoints: {
                900: { perPage: perPageMd },
                600: { perPage: perPageSm }
            }
        }).mount();
    }

    function bootstrap() {
        var sliders = document.querySelectorAll('.immo-list-slider');
        for (var i = 0; i < sliders.length; i++) {
            init(sliders[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
