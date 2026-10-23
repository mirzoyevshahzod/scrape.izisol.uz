/*
 * Umumiy UI yordamchilari (layouts/app, Bootstrap 5.3 dan keyin yuklanadi):
 *
 *   uiConfirm({title, message, ...})  — brauzerning confirm() o'rniga chiroyli
 *                                       tasdiqlash oynasi; Promise<boolean>.
 *   uiToast(message, variant)         — alert() o'rniga o'ng pastdagi xabar.
 *   <form data-confirm="...">         — yuborishdan oldin uiConfirm so'raydi.
 *
 * Barcha matnlar textContent orqali qo'yiladi (HTML sifatida emas).
 */
(function () {
    const VARIANTS = {
        primary: { button: 'btn-primary', icon: 'fa-circle-question', tile: 'ui-confirm-icon' },
        danger: { button: 'btn-danger', icon: 'fa-triangle-exclamation', tile: 'ui-confirm-icon is-danger' },
    };

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    // ---------- Tasdiqlash oynasi ----------

    let modal = null;
    let parts = null;

    function buildModal() {
        const root = el('div', 'modal fade');
        root.tabIndex = -1;
        root.setAttribute('aria-hidden', 'true');

        const dialog = el('div', 'modal-dialog modal-dialog-centered ui-confirm');
        const content = el('div', 'modal-content');
        const body = el('div', 'modal-body p-4');

        const head = el('div', 'd-flex gap-3 align-items-start');
        const icon = el('span', 'ui-confirm-icon');
        const iconI = el('i', 'fas');
        icon.append(iconI);
        const texts = el('div', 'flex-grow-1 min-w-0');
        const title = el('h5', 'mb-1 fw-semibold');
        title.id = 'ui-confirm-title';
        const message = el('p', 'text-muted mb-0');
        texts.append(title, message);
        head.append(icon, texts);

        const extra = el('div', 'mt-3');
        const error = el('div', 'alert alert-danger py-2 px-3 small mt-3 mb-0 d-none');

        const footer = el('div', 'd-flex justify-content-end gap-2 mt-4');
        const cancel = el('button', 'btn btn-light');
        cancel.type = 'button';
        const confirm = el('button', 'btn');
        confirm.type = 'button';
        footer.append(cancel, confirm);

        body.append(head, extra, error, footer);
        content.append(body);
        dialog.append(content);
        root.append(dialog);
        root.setAttribute('aria-labelledby', title.id);
        document.body.append(root);

        parts = { root, icon, iconI, title, message, extra, error, cancel, confirm };
        modal = new bootstrap.Modal(root);
    }

    window.uiConfirm = function (options) {
        const opts = Object.assign({
            title: 'Tasdiqlang',
            message: '',
            variant: 'primary',
            icon: null,
            confirmText: 'Tasdiqlash',
            cancelText: 'Bekor qilish',
            body: null,          // qo'shimcha DOM element (masalan sana tanlash)
            onConfirm: null,     // async () => true | string (xato matni — oyna yopilmaydi)
        }, options || {});

        if (!modal) buildModal();

        const variant = VARIANTS[opts.variant] || VARIANTS.primary;
        parts.icon.className = variant.tile;
        parts.iconI.className = 'fas ' + (opts.icon || variant.icon);
        parts.title.textContent = opts.title;
        parts.message.textContent = opts.message;
        parts.message.classList.toggle('d-none', !opts.message);
        parts.extra.replaceChildren();
        parts.extra.classList.toggle('d-none', !opts.body);
        if (opts.body) parts.extra.append(opts.body);
        parts.error.classList.add('d-none');
        parts.cancel.textContent = opts.cancelText;
        parts.confirm.className = 'btn ' + variant.button;
        parts.confirm.textContent = opts.confirmText;
        parts.confirm.disabled = false;

        return new Promise(function (resolve) {
            let confirmed = false;

            function cleanup() {
                parts.confirm.onclick = null;
                parts.cancel.onclick = null;
                parts.root.removeEventListener('hidden.bs.modal', onHidden);
            }

            function onHidden() {
                cleanup();
                resolve(confirmed);
            }

            parts.cancel.onclick = function () { modal.hide(); };

            parts.confirm.onclick = async function () {
                if (!opts.onConfirm) {
                    confirmed = true;
                    modal.hide();
                    return;
                }

                parts.confirm.disabled = true;
                parts.cancel.disabled = true;
                parts.error.classList.add('d-none');
                const label = parts.confirm.textContent;
                parts.confirm.replaceChildren(el('span', 'spinner-border spinner-border-sm me-2'), document.createTextNode(label));

                let result;
                try {
                    result = await opts.onConfirm();
                } catch (e) {
                    result = 'Xatolik: ' + (e && e.message ? e.message : e);
                }

                parts.confirm.textContent = label;
                parts.confirm.disabled = false;
                parts.cancel.disabled = false;

                if (typeof result === 'string') {
                    parts.error.textContent = result;
                    parts.error.classList.remove('d-none');
                    return;
                }

                confirmed = true;
                modal.hide();
            };

            parts.root.addEventListener('hidden.bs.modal', onHidden);
            modal.show();
            parts.root.addEventListener('shown.bs.modal', function () { parts.confirm.focus(); }, { once: true });
        });
    };

    // ---------- Bildirishnoma (toast) ----------

    const TOAST = {
        success: { icon: 'fa-circle-check', cls: 'text-success' },
        danger: { icon: 'fa-circle-exclamation', cls: 'text-danger' },
        warning: { icon: 'fa-triangle-exclamation', cls: 'text-warning' },
        info: { icon: 'fa-circle-info', cls: 'text-primary' },
    };

    window.uiToast = function (message, variant) {
        let stack = document.getElementById('ui-toast-stack');
        if (!stack) {
            stack = el('div', 'toast-container position-fixed bottom-0 end-0 p-3');
            stack.id = 'ui-toast-stack';
            document.body.append(stack);
        }

        const kind = TOAST[variant] || TOAST.success;
        const toast = el('div', 'toast ui-toast align-items-center');
        toast.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
        const row = el('div', 'd-flex align-items-center gap-2 p-3');
        const icon = el('i', 'fas ' + kind.icon + ' ' + kind.cls);
        const text = el('div', 'flex-grow-1 small fw-medium', message);
        const close = el('button', 'btn-close btn-close-sm');
        close.type = 'button';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Yopish');
        row.append(icon, text, close);
        toast.append(row);
        stack.append(toast);

        toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
        new bootstrap.Toast(toast, { delay: variant === 'danger' ? 8000 : 4000 }).show();
    };

    // ---------- <form data-confirm="..."> ----------

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

        event.preventDefault();

        window.uiConfirm({
            title: form.dataset.confirmTitle || 'Tasdiqlang',
            message: form.dataset.confirm,
            variant: form.dataset.confirmVariant || 'primary',
            confirmText: form.dataset.confirmButton || 'Tasdiqlash',
        }).then(function (ok) {
            // submit() submit hodisasini qayta chiqarmaydi — oyna takrorlanmaydi
            if (ok) HTMLFormElement.prototype.submit.call(form);
        });
    });
})();
