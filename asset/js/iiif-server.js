$(function () {

    function escapeAttr(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
    }

    // execCommand('copy') is deprecated but still the only copy API available
    // in non-secure contexts (HTTP, custom dev hostnames, etc.).
    // navigator.clipboard requires HTTPS or the literal "localhost" / 127.0.0.1.
    function copyViaExecCommand(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        document.body.removeChild(ta);
        return ok;
    }

    function copyToClipboard(button, onDone) {
        const iiifUrl = button.data('iiif-url');
        const textFailed = button.data('text-failed') || 'Unable to copy url in clipboard!';
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard
                .writeText(iiifUrl)
                .then(() => onDone && onDone(true))
                .catch(() => {
                    // Fall back to execCommand even when the modern API is
                    // exposed but the user denied permission.
                    const ok = copyViaExecCommand(iiifUrl);
                    if (!ok) {
                        CommonDialog.dialogAlert({ message: textFailed });
                    }
                    onDone && onDone(ok);
                });
        } else {
            const ok = copyViaExecCommand(iiifUrl);
            if (!ok) {
                CommonDialog.dialogAlert({ message: textFailed });
            }
            onDone && onDone(ok);
        }
    }

    function setDragData(ev, url) {
        ev.dataTransfer.setData('text/uri-list', url);
        ev.dataTransfer.setData('text/plain', url);
        ev.dataTransfer.effectAllowed = 'copy';
    }

    function buildDialogBody(button, copiedFlag) {
        const iiifUrl = button.data('iiif-url');
        const dragUrl = button.data('iiif-drag-url') || iiifUrl;
        const iiifIcon = button.data('iiif-icon');
        const fDrag = button.data('flag-drag-icon') === 1 || button.data('flag-drag-icon') === '1';
        const fCopyBtn = button.data('flag-copy-button') === 1 || button.data('flag-copy-button') === '1';
        const fWhat = button.data('flag-what-is-iiif') === 1 || button.data('flag-what-is-iiif') === '1';
        const fCopyClick = button.data('flag-copy-on-click') === 1 || button.data('flag-copy-on-click') === '1';

        const textDrag = button.data('text-drag');
        const textOr = button.data('text-or');
        const textCopyPaste = button.data('text-copy-paste');
        const textCopy = button.data('text-copy');
        const textWhat = button.data('text-what-is-iiif');
        const textCopied = button.data('text-copied');

        let html = '<div class="iiif-share">';

        if (fCopyClick && copiedFlag) {
            html += `<div class="iiif-share-copied">${escapeAttr(textCopied)}</div>`;
        }

        const sections = [];
        if (fDrag) {
            sections.push(`
                <div class="iiif-share-section iiif-share-drag">
                    <p id="iiif-share-drag-label">${escapeAttr(textDrag)}</p>
                    <a class="iiif-share-icon" href="${escapeAttr(dragUrl)}" target="_blank" rel="noopener" aria-labelledby="iiif-share-drag-label">
                        <img src="${escapeAttr(iiifIcon)}" alt="" aria-hidden="true" draggable="false" />
                    </a>
                </div>`);
        }
        if (fCopyBtn) {
            sections.push(`
                <div class="iiif-share-section iiif-share-copy-section">
                    <p>${escapeAttr(textCopyPaste)}</p>
                    <button type="button" class="button iiif-share-copy">${escapeAttr(textCopy)}</button>
                </div>`);
        }

        html += sections.join('');

        if (fWhat) {
            html += `<div class="iiif-share-info-row"><a class="iiif-share-info" href="https://iiif.io/" target="_blank" rel="noopener noreferrer">${escapeAttr(textWhat)}</a></div>`;
        }

        html += '</div>';
        return html;
    }

    function openDialog(button, copiedFlag) {
        CommonDialog.dialogGeneric({
            heading: button.data('text-share') || 'Share',
            body: buildDialogBody(button, copiedFlag),
            textOk: null,
            textCancel: button.data('text-close') || 'Close',
        });
        // Wire dialog interactions.
        setTimeout(() => {
            const dialog = document.querySelector('dialog.dialog-generic');
            if (!dialog) return;
            const copyBtn = dialog.querySelector('.iiif-share-copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', () => copyToClipboard(button, (ok) => {
                    if (ok) {
                        const note = document.createElement('div');
                        note.className = 'iiif-share-copied';
                        note.textContent = button.data('text-copied');
                        const share = dialog.querySelector('.iiif-share');
                        if (share) share.prepend(note);
                    }
                }));
            }
            // The dialog icon is a real <a href> carrying the "?manifest=" url,
            // so it drags natively and opens the manifest on click/Enter; no JS
            // dragstart wiring is needed.
        }, 0);
    }

    // Base: button always draggable. Drag carries the "?manifest=" url so the
    // target viewer can extract the manifest; fall back to the bare url.
    $('.iiif-copy').each(function () {
        const el = this;
        el.addEventListener('dragstart', (ev) => setDragData(ev, el.getAttribute('data-iiif-drag-url') || el.getAttribute('data-iiif-url')));
    });

    // Click: optional copy + dialog.
    $('.iiif-copy').on('click', function (e) {
        e.preventDefault();
        const button = $(this);
        const fCopyClick = button.data('flag-copy-on-click') === 1 || button.data('flag-copy-on-click') === '1';
        if (fCopyClick) {
            copyToClipboard(button, (ok) => openDialog(button, ok));
        } else {
            openDialog(button, false);
        }
    });

});
