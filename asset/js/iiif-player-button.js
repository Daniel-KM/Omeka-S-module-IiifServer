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
        } else if (player === 'openseadragon_core') {
            var info = stage.getAttribute('data-info');
            var prefix = stage.getAttribute('data-asset-prefix');
            var start2 = function () {
                window.OpenSeadragon({ id: inner.id, prefixUrl: prefix, tileSources: [info] });
            };
            if (window.OpenSeadragon) start2(); else loadScript(assetJs, start2);
        }
    }

    function setup(root) {
        var btn = root.querySelector('.iiif-player-toggle');
        var overlay = root.querySelector('.iiif-player-overlay');
        var stage = root.querySelector('.iiif-player-stage');
        var closeBtn = root.querySelector('.iiif-player-close');
        var tpl = root.querySelector('template.iiif-player-template');
        if (!btn || !overlay || !stage || !closeBtn) return;

        var player = stage.getAttribute('data-player');
        var isCore = player === 'mirador_core' || player === 'openseadragon_core';
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
