/**
 * Bauprojekt-Detailseite: Lightbox für Wohneinheiten-Quick-Info.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function () {
        var lightbox = document.getElementById('immo-unit-lightbox');
        if (!lightbox) return;

        var content      = lightbox.querySelector('.immo-unit-lightbox-content');
        var triggers     = document.querySelectorAll('[data-immo-unit-id]');
        // Über alle Datenpools auf der Seite suchen — single-project.php nutzt
        // weiterhin die ID, [immo_units]-Shortcodes ergänzen die Klasse, damit
        // mehrere Pools nebeneinander funktionieren.
        var legacyPool   = document.getElementById('immo-unit-lightbox-data');
        var dataPools    = Array.prototype.slice.call(
            document.querySelectorAll('.immo-unit-lightbox-data')
        );
        if (legacyPool && dataPools.indexOf(legacyPool) === -1) {
            dataPools.push(legacyPool);
        }
        var actionsBox   = document.getElementById('immo-unit-lightbox-actions');
        var detailsBtn   = document.getElementById('immo-unit-lightbox-details-btn');

        function findUnitNode(unitId) {
            for (var i = 0; i < dataPools.length; i++) {
                var node = dataPools[i].querySelector('[data-unit-id="' + unitId + '"]');
                if (node) return node;
            }
            return null;
        }

        function openLightbox(triggerEl) {
            if (!content) return;
            var unitId = triggerEl.getAttribute('data-immo-unit-id');
            var src = findUnitNode(unitId);
            if (!src) return;
            content.innerHTML = src.innerHTML;

            // URL aus Tabellenzeile übernehmen (ist robuster als aus Pool zu lesen).
            var rowUrl = triggerEl.getAttribute('data-immo-unit-url') || '';
            if (detailsBtn && actionsBox) {
                if (rowUrl) {
                    detailsBtn.setAttribute('href', rowUrl);
                    actionsBox.style.display = '';
                } else {
                    detailsBtn.setAttribute('href', '#');
                    actionsBox.style.display = 'none';
                }
            }

            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (content) content.innerHTML = '';
        }

        // Event-Delegation: schließt egal ob X, Backdrop oder dynamisch
        // eingefügte Schließen-Buttons im geklonten Inhalt geklickt werden.
        lightbox.addEventListener('click', function (e) {
            if (e.target.closest('[data-immo-lightbox-close]')) {
                e.preventDefault();
                closeLightbox();
            }
        });

        triggers.forEach(function (el) {
            el.addEventListener('click', function (e) {
                // Klicks auf interaktive Kinder durchlassen (z.B. Submit-Buttons),
                // aber NICHT auf reguläre <a>-Tags innerhalb der Card — sonst lässt
                // sich die Lightbox nie aus dem Card-Body öffnen.
                if (e.target.closest('button[type="submit"]')) return;
                // Wenn Element selbst ein Link ist und nicht als Lightbox-Trigger
                // gemeint, das Default-Verhalten zulassen.
                if (el.tagName === 'A' && !el.hasAttribute('data-immo-unit-id')) return;
                e.preventDefault();
                if (el.getAttribute('data-immo-unit-id')) openLightbox(el);
            });

            // Tastatur-Zugänglichkeit: Enter/Space öffnet Lightbox auf TR und
            // generischen (nicht nativ fokussierbaren) Elementen mit Trigger.
            if (el.tagName !== 'A' && el.tagName !== 'BUTTON') {
                if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');
                if (!el.hasAttribute('role'))    el.setAttribute('role', 'button');
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        if (el.getAttribute('data-immo-unit-id')) openLightbox(el);
                    }
                });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && lightbox.classList.contains('is-open')) {
                closeLightbox();
            }
        });

        // ----------------------------------------------------------------
        // Status-Filter: Stat-Pillen klickbar (oben + Sidebar synchron),
        // filtert die Wohneinheiten-Tabelle.
        // ----------------------------------------------------------------
        var filterButtons = document.querySelectorAll('[data-immo-filter-status]');
        var unitRows      = document.querySelectorAll('.immo-unit-row[data-status]');
        var activeStates  = {};
        var emptyMsg      = null;

        function syncButtons() {
            filterButtons.forEach(function (b) {
                var key = b.getAttribute('data-immo-filter-status');
                var on  = !!activeStates[key];
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function applyFilter() {
            var active = Object.keys(activeStates).filter(function (k) { return activeStates[k]; });

            var visibleCount = 0;
            unitRows.forEach(function (row) {
                var rowStatus = row.getAttribute('data-status') || '';
                var visible   = active.length === 0 || active.indexOf(rowStatus) !== -1;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            var table = document.querySelector('.immo-unit-table');
            if (table) {
                if (!emptyMsg) {
                    emptyMsg = document.createElement('p');
                    emptyMsg.className = 'immo-unit-filter-empty';
                    emptyMsg.textContent = 'Keine Einheiten in der gewählten Auswahl.';
                    emptyMsg.style.display = 'none';
                    table.parentNode.insertBefore(emptyMsg, table.nextSibling);
                }
                emptyMsg.style.display = (visibleCount === 0 && active.length > 0) ? 'block' : 'none';
            }
        }

        filterButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-immo-filter-status');
                if (!key) return;
                activeStates[key] = !activeStates[key];
                syncButtons();
                applyFilter();
            });
        });
    });
})();
