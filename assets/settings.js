(() => {
    'use strict';
    const root = document.querySelector('.ddw-twc-root');
    if (!root || root.dataset.twcBound) return;
    root.dataset.twcBound = '1';
    const link = document.getElementById('ddw-twc-history');
    const dialog = document.getElementById('ddw-twc-dialog');
    const proto = window.HTMLDialogElement && HTMLDialogElement.prototype;
    const open = proto && proto.showModal;
    const close = proto && proto.close;
    if (link && dialog && typeof open === 'function' && typeof close === 'function') {
        link.addEventListener('click', event => {
            try {
                dialog.hidden = false;
                open.call(dialog);
                if (dialog.open) event.preventDefault();
                else dialog.hidden = true;
            } catch (_) { dialog.hidden = true; }
        });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close.call(dialog);
        });
        dialog.addEventListener('close', () => { dialog.hidden = true; link.focus(); });
    }
})();
