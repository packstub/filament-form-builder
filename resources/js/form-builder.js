(function () {
    if (window.__packstubFormBuilder) return;
    window.__packstubFormBuilder = true;

    function setError(form, key, message) {
        var el = form.querySelector('[data-fb-error-for="' + key + '"]');
        var field = form.querySelector('[data-fb-field="' + key + '"]');
        if (el) { el.textContent = message || ''; el.hidden = !message; }
        if (field) {
            field.classList.toggle('fb-field--error', !!message);
            field.querySelectorAll('input, select, textarea').forEach(function (input) {
                if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
            });
        }
    }

    function clearErrors(form) {
        form.querySelectorAll('[data-fb-error-for]').forEach(function (el) { setError(form, el.getAttribute('data-fb-error-for'), ''); });
        var alert = form.querySelector('[data-fb-alert]');
        if (alert) alert.hidden = true;
    }

    function showAlert(form, message) {
        var alert = form.querySelector('[data-fb-alert]');
        if (!alert) return;
        alert.textContent = message;
        alert.hidden = false;
        alert.focus && alert.setAttribute('tabindex', '-1');
        alert.focus();
    }

    function handle(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors(form);

            var button = form.querySelector('[data-fb-submit]');
            if (button) button.disabled = true;

            var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            var token = form.querySelector('input[name="_token"]');
            if (token) headers['X-CSRF-TOKEN'] = token.value;

            fetch(form.action, { method: 'POST', headers: headers, body: new FormData(form), credentials: 'same-origin' })
                .then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        return { status: response.status, body: body };
                    });
                })
                .then(function (result) {
                    var body = result.body || {};
                    if (result.status >= 200 && result.status < 300 && body.ok) {
                        if (body.redirect) { window.location.assign(body.redirect); return; }
                        var wrapper = form.closest('[data-fb-form]');
                        var success = document.createElement('div');
                        success.className = 'fb-success';
                        success.setAttribute('role', 'status');
                        success.setAttribute('data-fb-success', '');
                        success.textContent = body.message || '';
                        form.replaceWith(success);
                        if (wrapper) wrapper.dispatchEvent(new CustomEvent('form-builder:submitted', { bubbles: true, detail: body }));
                        return;
                    }
                    var errors = body.errors || {};
                    var first = null;
                    Object.keys(errors).forEach(function (key) {
                        if (key === 'form') return;
                        var message = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                        setError(form, key.split('.')[0], message);
                        if (!first) first = form.querySelector('[data-fb-field="' + key.split('.')[0] + '"] input, [data-fb-field="' + key.split('.')[0] + '"] select, [data-fb-field="' + key.split('.')[0] + '"] textarea');
                    });
                    showAlert(form, (errors.form && errors.form[0]) || body.message || form.getAttribute('data-fb-message-invalid') || 'Please check the form.');
                    if (first) first.focus();
                })
                .catch(function () {
                    showAlert(form, form.getAttribute('data-fb-message-failed') || 'Something went wrong. Please try again.');
                })
                .finally(function () { if (button) button.disabled = false; });
        });
    }

    function init(root) {
        (root || document).querySelectorAll('form[data-fb-enhance="true"]').forEach(function (form) {
            if (form.__fb) return;
            form.__fb = true;
            handle(form);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); }); else init();
    document.addEventListener('form-builder:init', function (event) { init(event.target); });
})();
