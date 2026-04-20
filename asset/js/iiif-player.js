(function () {
    'use strict';

    function loadScript(src, onload) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = onload;
        document.head.appendChild(s);
    }

    function runScripts(container) {
        container.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) {
                s.setAttribute(old.attributes[i].name, old.attributes[i].value);
            }
            s.text = old.text;
            old.parentNode.replaceChild(s, old);
        });
    }

    function initCore(stage) {
        var player = stage.getAttribute('data-player');
        var embedId = stage.getAttribute('data-embed-id');
        var assetJs = stage.getAttribute('data-asset-js');
        var inner = document.createElement('div');
        inner.id = embedId + '-inner';
        inner.style.cssText = 'width:100%;height:100%;';
        stage.appendChild(inner);

        if (player === 'mirador_core') {
            var manifestId = stage.getAttribute('data-manifest');
            var start = function () {
                window.Mirador.player({ id: inner.id, windows: [{ manifestId: manifestId }] });
            };
            if (window.Mirador) start(); else loadScript(assetJs, start);
        } else if (player === 'openseadragon') {
            var firstTile = JSON.parse(stage.getAttribute('data-tile-source'));
            var prefix = stage.getAttribute('data-asset-prefix');
            var tilesJson = stage.getAttribute('data-tiles');
            var tiles = tilesJson ? JSON.parse(tilesJson) : null;

            function toTileSource(t) {
                return t.type === 'iiif' ? t.url : { type: 'image', url: t.url };
            }

            if (tiles && tiles.length > 1) {
                var pos = stage.getAttribute('data-sidebar-position') || 'bottom';
                stage.classList.add('iiif-player-sidebar-' + pos);
                stage.removeChild(inner);
                var osdArea = document.createElement('div');
                osdArea.className = 'iiif-player-osd-area';
                osdArea.appendChild(inner);
                var sidebar = document.createElement('div');
                sidebar.className = 'iiif-player-sidebar';
                if (pos === 'top' || pos === 'left') {
                    stage.appendChild(sidebar);
                    stage.appendChild(osdArea);
                } else {
                    stage.appendChild(osdArea);
                    stage.appendChild(sidebar);
                }

                tiles.forEach(function (t, i) {
                    var a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'iiif-player-thumb';
                    a.title = t.title || '';
                    a.innerHTML = '<img src="' + t.thumb + '" alt="">';
                    a.addEventListener('click', function () {
                        if (window._iiifPlayerOsd && window._iiifPlayerOsd[inner.id]) {
                            window._iiifPlayerOsd[inner.id].open(toTileSource(t));
                        }
                        sidebar.querySelectorAll('.iiif-player-thumb.active').forEach(function (el) {
                            el.classList.remove('active');
                        });
                        a.classList.add('active');
                    });
                    if (i === 0) a.classList.add('active');
                    sidebar.appendChild(a);
                });

                // Translate vertical wheel to horizontal scroll on horizontal
                // sidebars; without this the wheel event bubbles up to the
                // OpenSeadragon canvas and zooms the viewer instead.
                if (pos === 'top' || pos === 'bottom') {
                    sidebar.addEventListener('wheel', function (e) {
                        var delta = e.deltaX || e.deltaY;
                        if (!delta) return;
                        e.preventDefault();
                        sidebar.scrollLeft += delta;
                    }, { passive: false });
                }
            }

            var optsJson = stage.getAttribute('data-osd-options');
            var stringsJson = stage.getAttribute('data-osd-strings');
            var extraOpts = optsJson ? JSON.parse(optsJson) : {};
            var strings = stringsJson ? JSON.parse(stringsJson) : {};

            var start2 = function () {
                Object.keys(strings).forEach(function (k) {
                    window.OpenSeadragon.setString(k, strings[k]);
                });
                var opts = Object.assign({}, extraOpts);
                opts.id = inner.id;
                opts.prefixUrl = prefix;
                opts.tileSources = [toTileSource(firstTile)];
                window._iiifPlayerOsd = window._iiifPlayerOsd || {};
                window._iiifPlayerOsd[inner.id] = window.OpenSeadragon(opts);
            };
            if (window.OpenSeadragon) start2(); else loadScript(assetJs, start2);
        }
    }

    function setup(root) {
        var stage = root.querySelector('.iiif-player-stage');
        if (!stage) return;

        var player = stage.getAttribute('data-player');
        var isCore = player === 'mirador_core' || player === 'openseadragon';

        // Inline mode: init player immediately, no button/overlay.
        if (root.classList.contains('iiif-player-inline')) {
            if (isCore) initCore(stage);
            return;
        }

        var btn = root.querySelector('.iiif-player-toggle');
        var overlay = root.querySelector('.iiif-player-overlay');
        var closeBtn = root.querySelector('.iiif-player-close');
        var tpl = root.querySelector('template.iiif-player-template');
        if (!btn || !overlay || !closeBtn) return;

        var lazy = stage.getAttribute('data-lazy') === '1';
        var loaded = false;

        function open() {
            if (!loaded) {
                if (isCore) {
                    initCore(stage);
                } else if (lazy && tpl) {
                    stage.appendChild(tpl.content.cloneNode(true));
                    runScripts(stage);
                }
                loaded = true;
            }
            overlay.style.display = 'flex';
            btn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            // Module viewers init hidden: force relayout.
            window.dispatchEvent(new Event('resize'));
        }
        function close() {
            overlay.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        btn.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display !== 'none') close();
        });
    }

    function init() {
        document.querySelectorAll('.iiif-player-button').forEach(function (root) {
            if (root.dataset.iiifPlayerButtonInit) return;
            root.dataset.iiifPlayerButtonInit = '1';
            setup(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
