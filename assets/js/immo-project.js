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
        // Status-Filter: Stat-Pillen klickbar. Scoped pro Block, damit auf
        // einer Seite mit mehreren Filtersets jeder Filter nur sein eigenes
        // Set einschränkt. Ein "Block" ist:
        //   - eine Bauprojekt-Detailseite (single-project.php)
        //   - oder ein [immo_units]-Container (.immo-block-units)
        //   - oder die immo-units-Sektion auf der Bauprojekt-Detailseite.
        // ----------------------------------------------------------------
        function findFilterBlock(el) {
            // Nächster Container, der filterbare Items enthält.
            var candidates = [
                '.immo-block-units',
                '.immo-units-list',
                '.immo-project-detail',
                '.immo-detail'
            ];
            for (var i = 0; i < candidates.length; i++) {
                var hit = el.closest(candidates[i]);
                if (hit) return hit;
            }
            return document.body;
        }

        // Selektoren für filterbare Wohneinheiten in allen Layouts:
        // - .immo-unit-row    → Tabelle (auch in single-project.php verwendet)
        // - .immo-units-card  → Grid-Layout im [immo_units]-Shortcode
        // - .immo-units-listitem → Listen-Layout im [immo_units]-Shortcode
        var FILTERABLE = '.immo-unit-row[data-status], .immo-units-card[data-status], .immo-units-listitem[data-status]';

        var filterButtons = document.querySelectorAll('[data-immo-filter-status]');

        // Pro Block: { active: { [status]: true|false }, emptyMsg: <p>|null }
        var blockState = new WeakMap();

        function getBlockState(block) {
            var s = blockState.get(block);
            if (!s) {
                s = { active: {}, emptyMsg: null };
                blockState.set(block, s);
            }
            return s;
        }

        function syncButtons(block) {
            var s = getBlockState(block);
            block.querySelectorAll('[data-immo-filter-status]').forEach(function (b) {
                var key = b.getAttribute('data-immo-filter-status');
                var on  = !!s.active[key];
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function applyFilter(block) {
            var s = getBlockState(block);
            var active = Object.keys(s.active).filter(function (k) { return s.active[k]; });

            var visibleCount = 0;
            var rows = block.querySelectorAll(FILTERABLE);
            rows.forEach(function (row) {
                var rowStatus = row.getAttribute('data-status') || '';
                var visible   = active.length === 0 || active.indexOf(rowStatus) !== -1;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            // Leere-Liste-Hinweis dynamisch einfügen.
            var anchor = block.querySelector('.immo-unit-table, .immo-units-grid, .immo-units-flatlist');
            if (anchor) {
                if (!s.emptyMsg) {
                    s.emptyMsg = document.createElement('p');
                    s.emptyMsg.className = 'immo-unit-filter-empty';
                    s.emptyMsg.textContent = 'Keine Einheiten in der gewählten Auswahl.';
                    s.emptyMsg.style.display = 'none';
                    anchor.parentNode.insertBefore(s.emptyMsg, anchor.nextSibling);
                }
                s.emptyMsg.style.display = (visibleCount === 0 && active.length > 0) ? 'block' : 'none';
            }
        }

        filterButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-immo-filter-status');
                if (!key) return;
                var block = findFilterBlock(btn);
                var s = getBlockState(block);
                s.active[key] = !s.active[key];
                syncButtons(block);
                applyFilter(block);
            });
        });
    });
})();
