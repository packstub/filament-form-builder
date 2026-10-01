/*
 * The script embed: renders a form into a container on any site through the
 * JSON API, then hands it to the enhancement script above. Served by
 * GET /forms/{slug}/embed.js with __FB_EMBED_CONFIG__ filled in.
 *
 *   <div data-form-builder="contact"></div>
 *   <script src="https://your-app.test/forms/contact/embed.js" async></script>
 */
(function () {
    var config = __FB_EMBED_CONFIG__;
    var script = document.currentScript;

    function esc(text) { return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function styles() {
        if (document.querySelector('style[data-fb-embed-styles]')) return;
        var style = document.createElement('style');
        style.setAttribute('data-fb-embed-styles', '');
        style.textContent = config.styles;
        document.head.appendChild(style);
    }

    function input(field, id) {
        var attrs = ' id="' + id + '" name="' + esc(field.key) + '"' + (field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '') + ' aria-describedby="' + id + '-error"';
        var value = field.default === null || field.default === undefined ? '' : field.default;
        var choices = field.choices || {};
        var keys = Object.keys(choices);
        var opts = field.options || {};
        switch (field.type) {
            case 'textarea': case 'richtext':
                return '<textarea class="fb-input fb-textarea"' + attrs + ' rows="' + (opts.rows || 4) + '">' + esc(value) + '</textarea>';
            case 'select': case 'country':
                return '<select class="fb-input fb-select"' + attrs + '><option value="">' + esc(field.placeholder || config.strings.select_placeholder) + '</option>' + keys.map(function (k) { return '<option value="' + esc(k) + '"' + (String(value) === k ? ' selected' : '') + '>' + esc(choices[k]) + '</option>'; }).join('') + '</select>';
            case 'multiselect':
                return '<select class="fb-input fb-select fb-select--multiple" multiple id="' + id + '" name="' + esc(field.key) + '[]">' + keys.map(function (k) { return '<option value="' + esc(k) + '">' + esc(choices[k]) + '</option>'; }).join('') + '</select>';
            case 'radio': case 'toggle_buttons':
                var cls = field.type === 'radio' ? 'fb-choices' : 'fb-toggle-buttons', item = field.type === 'radio' ? 'fb-choice' : 'fb-toggle-button';
                return '<div class="' + cls + '">' + keys.map(function (k, i) { return '<label class="' + item + '" for="' + id + '-' + i + '"><input type="radio" id="' + id + '-' + i + '" name="' + esc(field.key) + '" value="' + esc(k) + '"' + (String(value) === k ? ' checked' : '') + '><span>' + esc(choices[k]) + '</span></label>'; }).join('') + '</div>';
            case 'checkboxes':
                return '<div class="fb-choices">' + keys.map(function (k, i) { return '<label class="fb-choice" for="' + id + '-' + i + '"><input type="checkbox" id="' + id + '-' + i + '" name="' + esc(field.key) + '[]" value="' + esc(k) + '"><span>' + esc(choices[k]) + '</span></label>'; }).join('') + '</div>';
            case 'checkbox': case 'consent': case 'toggle':
                return '<label class="fb-choice fb-choice--single" for="' + id + '"><input type="checkbox" id="' + id + '" name="' + esc(field.key) + '" value="1"><span>' + esc(field.label) + (opts.link_url ? ' <a class="fb-link" target="_blank" rel="noopener" href="' + esc(opts.link_url) + '">' + esc(opts.link_text || opts.link_url) + '</a>' : '') + '</span></label>';
            case 'rating':
                var max = parseInt(opts.max || 5, 10), stars = '';
                for (var s = 1; s <= max; s++) stars += '<label class="fb-rating__star" for="' + id + '-' + s + '"><input type="radio" id="' + id + '-' + s + '" name="' + esc(field.key) + '" value="' + s + '"><span aria-hidden="true">★</span></label>';
                return '<div class="fb-rating">' + stars + '</div>';
            case 'address':
                var parts = field.parts || {}, required = field.required_parts || [], countries = field.countries || {};
                var auto = { line1: 'address-line1', line2: 'address-line2', city: 'address-level2', region: 'address-level1', postal_code: 'postal-code', country: 'country' };
                return '<div class="fb-address__parts">' + Object.keys(parts).map(function (p) {
                    var pid = id + '-' + p.replace('_', '-'), name = esc(field.key) + '[' + p + ']', req = required.indexOf(p) >= 0 ? ' required aria-required="true"' : '';
                    var control = p === 'country'
                        ? '<select class="fb-input fb-select" id="' + pid + '" name="' + name + '" autocomplete="country"' + req + '><option value="">' + esc(config.strings.select_placeholder) + '</option>' + Object.keys(countries).map(function (c) { return '<option value="' + esc(c) + '">' + esc(countries[c]) + '</option>'; }).join('') + '</select>'
                        : '<input class="fb-input" type="text" id="' + pid + '" name="' + name + '" autocomplete="' + auto[p] + '"' + req + '>';
                    return '<div class="fb-address__part fb-address__part--' + p.replace('_', '-') + '"><label class="fb-sublabel" for="' + pid + '">' + esc(parts[p]) + '</label>' + control + '</div>';
                }).join('') + '</div>';
            case 'file':
                return '<input class="fb-input fb-file" type="file" id="' + id + '" name="' + esc(field.key) + '[]"' + (opts.multiple ? ' multiple' : '') + '>';
            case 'hidden':
                return '<input type="hidden" name="' + esc(field.key) + '" value="' + esc(value) + '">';
            case 'heading':
                return '<h3 class="fb-heading">' + esc(field.label) + '</h3>';
            case 'paragraph':
                return '<p class="fb-paragraph">' + esc(opts.text || '') + '</p>';
            case 'divider':
                return '<div class="fb-divider" role="separator"></div>';
            default:
                var types = { email: 'email', phone: 'tel', url: 'url', number: 'number', currency: 'number', date: 'date', datetime: 'datetime-local', time: 'time', color: 'color' };
                return '<input class="fb-input" type="' + (types[field.type] || 'text') + '"' + attrs + ' value="' + esc(value) + '">';
        }
    }

    function render(container, def) {
        var id = 'form-' + def.slug;
        var noLabel = ['checkbox', 'consent', 'toggle', 'hidden', 'heading', 'paragraph', 'divider'];
        var fieldset = ['radio', 'toggle_buttons', 'checkboxes', 'rating', 'address'];
        var html = '<div id="' + id + '" class="fb-form' + (def.mode === 'wizard' ? ' fb-form--wizard' : '') + (def.layout === 'horizontal' ? ' fb-form--horizontal' : '') + '" data-fb-form="' + esc(def.slug) + '">';
        if (!def.accepting) { container.innerHTML = html + '<div class="fb-closed" role="status">' + esc(def.closed_reason) + '</div></div>'; return; }
        html += '<form method="post" action="' + esc(def.submit_url) + '" class="fb-form__form" novalidate data-fb-enhance="true" enctype="multipart/form-data" data-fb-message-invalid="' + esc(config.strings.invalid) + '" data-fb-message-failed="' + esc(config.strings.failed) + '" data-fb-step-of="' + esc(config.strings.step_of) + '">';
        html += '<input type="hidden" name="' + esc(def.protection.token_field) + '" value="' + esc(def.protection.token) + '">';
        if (def.protection.honeypot_field) html += '<div class="fb-hp" aria-hidden="true"><label for="' + id + '-hp">' + esc(config.strings.honeypot_label) + '</label><input type="text" id="' + id + '-hp" name="' + esc(def.protection.honeypot_field) + '" tabindex="-1" autocomplete="off" value=""></div>';
        var logic = { wizard: def.mode === 'wizard' ? { progress: def.wizard.progress, numbers: def.wizard.step_numbers, navigation: def.wizard.navigation } : null, validate: def.validate_url, fields: {}, sections: [] };
        var byKey = {};
        def.fields.forEach(function (f) { byKey[f.key] = f; if (f.input) logic.fields[f.key] = { visibility: f.visibility, requirement: f.requirement, required: f.required, type: f.type }; });
        html += '<script type="application/json" data-fb-logic></script>';
        html += '<div class="fb-alert fb-alert--error" role="alert" data-fb-alert hidden></div>';
        if (def.mode === 'wizard' && def.wizard.progress) html += '<div class="fb-progress" data-fb-progress hidden><div class="fb-progress__bar"><span class="fb-progress__value" data-fb-progress-value style="width:0%"></span></div><p class="fb-progress__label" data-fb-progress-label></p></div>';
        def.sections.forEach(function (section, index) {
            logic.sections.push({ key: section.key, visibility: section.visibility && section.visibility.mode !== 'always' ? section.visibility : null, fields: section.fields.filter(function (k) { return byKey[k] && byKey[k].input; }) });
            html += '<section class="fb-section' + (section.label ? ' fb-section--named' : '') + '" data-fb-section="' + esc(section.key) + '" data-fb-step="' + index + '">';
            if (section.label) html += '<h3 class="fb-section__title">' + (def.mode === 'wizard' && def.wizard.step_numbers ? '<span class="fb-section__number">' + (index + 1) + '</span>' : '') + esc(section.label) + '</h3>' + (section.description ? '<p class="fb-section__description">' + esc(section.description) + '</p>' : '');
            html += '<div class="fb-fields">';
            section.fields.forEach(function (key) {
                var f = byKey[key];
                if (!f) return;
                var fid = id + '-' + f.key.replace(/_/g, '-');
                html += '<div class="fb-field fb-field--' + esc(f.width) + ' fb-field--' + esc(f.type) + '" data-fb-field="' + esc(f.key) + '">';
                var label = '<span class="fb-label">' + esc(f.label) + (f.required ? ' <span class="fb-required" aria-hidden="true">*</span>' : '') + '</span>';
                if (fieldset.indexOf(f.type) !== -1) html += '<fieldset class="fb-fieldset"><legend class="fb-label">' + esc(f.label) + (f.required ? ' <span class="fb-required" aria-hidden="true">*</span>' : '') + '</legend>' + input(f, fid) + '</fieldset>';
                else if (noLabel.indexOf(f.type) !== -1) html += input(f, fid);
                else html += '<label class="fb-label" for="' + fid + '">' + esc(f.label) + (f.required ? ' <span class="fb-required" aria-hidden="true">*</span>' : '') + '</label>' + input(f, fid);
                if (f.hint) html += '<p class="fb-hint">' + esc(f.hint) + '</p>';
                if (f.input) html += '<p class="fb-error" data-fb-error-for="' + esc(f.key) + '" id="' + fid + '-error" hidden></p>';
                html += '</div>';
            });
            html += '</div></section>';
        });
        html += '<div class="fb-actions">';
        if (def.mode === 'wizard') html += '<button type="button" class="fb-button fb-button--secondary" data-fb-previous hidden>' + esc(def.wizard.previous_label) + '</button><button type="button" class="fb-submit" data-fb-next hidden>' + esc(def.wizard.next_label) + '</button>';
        html += '<button type="submit" class="fb-submit" data-fb-submit>' + esc(def.submit_label) + '</button></div></form></div>';
        container.innerHTML = html;
        container.querySelector('[data-fb-logic]').textContent = JSON.stringify(logic);
        window.PackstubFormBuilder.init(container);
    }

    function mount(container) {
        if (container.__fbMounted) return;
        container.__fbMounted = true;
        styles();
        fetch(config.definition, { headers: { 'Accept': 'application/json' }, credentials: 'omit' })
            .then(function (r) { return r.json(); })
            .then(function (def) { render(container, def); })
            .catch(function () { container.innerHTML = '<div class="fb-form"><div class="fb-closed">' + esc(config.strings.failed) + '</div></div>'; });
    }

    function start() {
        var target = script && script.getAttribute('data-target') ? document.querySelector(script.getAttribute('data-target')) : null;
        var containers = target ? [target] : Array.prototype.slice.call(document.querySelectorAll('[data-form-builder="' + config.slug + '"]'));
        if (!containers.length && script) { target = document.createElement('div'); script.parentNode.insertBefore(target, script); containers = [target]; }
        containers.forEach(mount);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
