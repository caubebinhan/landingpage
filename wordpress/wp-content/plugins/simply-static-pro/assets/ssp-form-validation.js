(function(){
    'use strict';

    // Utility: create or return an error container for an input
    function getErrorEl(input){
        let el = input.closest('[data-ssp-field]') || input.parentElement;
        let msg = el && el.querySelector('.ssp-field-error');
        if(!msg){
            msg = document.createElement('div');
            msg.className = 'ssp-field-error';
            msg.style.cssText = 'color:#b30000;font-size:12px;line-height:1.4;margin-top:4px;display:none;';
            (el || input.parentElement || input).appendChild(msg);
        }
        return msg;
    }

    function setFieldError(input, message){
        const msg = getErrorEl(input);
        if(message){
            msg.textContent = message;
            msg.style.display = 'block';
            input.setAttribute('aria-invalid','true');
        } else {
            msg.textContent = '';
            msg.style.display = 'none';
            input.removeAttribute('aria-invalid');
        }
    }

    function isRadioOrCheckbox(el){
        return el.type === 'radio' || el.type === 'checkbox';
    }

    function isContentEditable(el){
        return !!el && (el.isContentEditable || el.getAttribute && el.getAttribute('contenteditable') === 'true');
    }

    function getFieldValue(el){
        if(isContentEditable(el)){
            return (el.textContent || '').trim();
        }
        return (el.value || '').trim();
    }

    function validateField(input){
        // Skip disabled or hidden (display:none) fields except contenteditable
        if(input.disabled) return {valid:true};
        const rect = input.getBoundingClientRect();
        if(!isContentEditable(input) && rect.width === 0 && rect.height === 0 && input.type !== 'hidden'){
            return {valid:true};
        }

        // Prefer browser validation API where possible
        // But we want to provide our own messages and styling
        let customMessage = '';

        // Required handling (including radio/checkbox groups)
        if(input.required){
            if(isRadioOrCheckbox(input)){
                const name = input.name;
                const form = input.form || document;
                const group = form.querySelectorAll('input[name="'+CSS.escape(name)+'"][type="'+input.type+'"]');
                let anyChecked = false;
                group.forEach(i=>{ if(i.checked) anyChecked=true; });
                if(!anyChecked){
                    customMessage = customMessage || 'This field is required.';
                }
            } else if(input.type === 'file') {
                if(!input.files || input.files.length === 0){
                    customMessage = customMessage || 'Please select a file.';
                }
            } else if(!input.value || input.value.trim() === ''){
                customMessage = customMessage || 'This field is required.';
            }
        }

        // Pattern
        if(!customMessage && input.pattern){
            try{
                const re = new RegExp('^'+input.pattern+'$');
                if(input.value && !re.test(input.value)){
                    customMessage = 'Please match the requested format.';
                }
            }catch(e){/* ignore invalid pattern */}
        }

        // Type-specific
        if(!customMessage && input.value){
            switch(input.type){
                case 'email':
                    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)){
                        customMessage = 'Please enter a valid email address.';
                    }
                    break;
                case 'url':
                    try{ new URL(input.value); } catch(e){ customMessage = 'Please enter a valid URL.'; }
                    break;
                case 'number':
                    const val = Number(input.value);
                    if(Number.isNaN(val)){
                        customMessage = 'Please enter a number.';
                    } else {
                        if(input.min !== '' && val < Number(input.min)){
                            customMessage = 'Value must be greater than or equal to '+input.min+'.';
                        }
                        if(!customMessage && input.max !== '' && val > Number(input.max)){
                            customMessage = 'Value must be less than or equal to '+input.max+'.';
                        }
                        if(!customMessage && input.step && input.step !== 'any'){
                            const step = Number(input.step);
                            if(step>0){
                                const base = input.min !== '' ? Number(input.min) : 0;
                                const diff = (val - base) / step;
                                if(Math.abs(diff - Math.round(diff)) > 1e-8){
                                    customMessage = 'Please enter a valid value.';
                                }
                            }
                        }
                    }
                    break;
            }
        }

        // Length constraints
        if(!customMessage && input.value){
            if(input.maxLength && input.maxLength > 0 && input.value.length > input.maxLength){
                customMessage = 'Please shorten this text to '+input.maxLength+' characters or less.';
            }
            if(!customMessage && input.minLength && input.value.length < input.minLength){
                customMessage = 'Please lengthen this text to at least '+input.minLength+' characters.';
            }
        }

        // File accept
        if(!customMessage && input.type === 'file' && input.accept && input.files && input.files.length){
            const accepts = input.accept.split(',').map(a=>a.trim().toLowerCase());
            for(const f of input.files){
                const name = f.name.toLowerCase();
                const type = (f.type||'').toLowerCase();
                const ok = accepts.some(acc => {
                    if(acc.startsWith('.')) return name.endsWith(acc);
                    if(acc.endsWith('/*')) return type.startsWith(acc.slice(0,-1));
                    return type === acc;
                });
                if(!ok){ customMessage = 'Selected file type is not allowed.'; break; }
            }
        }

        if(customMessage){
            setFieldError(input, customMessage);
            return {valid:false, message:customMessage};
        } else {
            setFieldError(input, '');
            return {valid:true};
        }
    }

    function markRequiredFromAria(form){
        try{
            const inputs = form.querySelectorAll('input');
            const textareas = form.querySelectorAll('textarea');
            const selects = form.querySelectorAll('select');
            inputs.forEach(el => { if(el.getAttribute('aria-required') === 'true'){ el.required = true; } });
            textareas.forEach(el => { if(el.getAttribute('aria-required') === 'true'){ el.required = true; } });
            selects.forEach(el => { if(el.getAttribute('aria-required') === 'true'){ el.required = true; } });
        }catch(e){ /* noop */ }
    }

    function attachValidationToForm(form){
        if(!form || form.__sspValidationAttached) return;
        form.__sspValidationAttached = true;
        // Avoid native tooltip popups; we render our own messages
        form.setAttribute('novalidate','novalidate');

        // Mirror aria-required to required so our validator can pick it up across plugins
        markRequiredFromAria(form);

        const inputSelector = 'input, select, textarea, [contenteditable]';
        const fields = form.querySelectorAll(inputSelector);

        fields.forEach(field => {
            const handler = () => validateField(field);
            field.addEventListener('blur', handler);
            field.addEventListener('input', handler);
            if(isRadioOrCheckbox(field)){
                field.addEventListener('change', handler);
            }
        });

        form.addEventListener('submit', function(e){
            let firstInvalid = null;
            let valid = true;
            fields.forEach(field => {
                const res = validateField(field);
                if(!res.valid && !firstInvalid){
                    firstInvalid = field;
                    valid = false;
                }
            });
            if(!valid){
                e.preventDefault();
                e.stopPropagation();
                if(firstInvalid && typeof firstInvalid.focus === 'function'){
                    firstInvalid.focus({preventScroll:false});
                }
                // Show a top summary if not already present
                let top = form.querySelector('.ssp-form-error-summary');
                if(!top){
                    top = document.createElement('div');
                    top.className = 'ssp-form-error-summary';
                    top.style.cssText = 'width:100%;background:#ffe0e0;color:#7a0000;padding:10px;margin:0 0 12px;border:1px solid #f5b5b5;';
                    form.insertBefore(top, form.firstChild);
                }
                top.textContent = 'Please fix the errors highlighted below and try again.';
            } else {
                // clear summary if any
                const top = form.querySelector('.ssp-form-error-summary');
                if(top){ top.textContent = ''; top.parentElement.removeChild(top); }
            }
        }, true);
    }

    function findFormBySettings(settings){
        // We try multiple strategies depending on plugin type
        // 0) Hidden input name (for "Other Plugin" or generic setups): prefer matching by value first
        if (settings.form_hidden_name) {
            try {
                const baseSel = 'form input[name="' + CSS.escape(settings.form_hidden_name) + '"]';
                if (settings.form_id) {
                    const byVal = document.querySelector(baseSel + '[value="' + CSS.escape(settings.form_id) + '"]');
                    if (byVal) { return byVal.closest('form'); }
                }
                const any = document.querySelector(baseSel);
                if (any) { return any.closest('form'); }
            } catch(e) { /* noop */ }
        }
        // 1) Kadence Forms: match by hidden field value `_kb_form_id`
        if (settings.form_id) {
            const kadHidden = document.querySelector('form input[name="_kb_form_id"][value="' + CSS.escape(settings.form_id) + '"]');
            if (kadHidden) { return kadHidden.closest('form'); }
        }
        // 2) Direct by id (covers many builders if DOM id matches config)
        if(settings.form_id){
            const byId = document.getElementById(settings.form_id);
            if(byId && byId.tagName && byId.tagName.toLowerCase() === 'form'){
                return byId;
            }
            // In case someone configured an element id at the wrapper, still try to find a child form.
            const maybeWrap = document.getElementById(settings.form_id);
            if(maybeWrap && maybeWrap.querySelector){
                const innerForm = maybeWrap.querySelector('form');
                if(innerForm){ return innerForm; }
            }
        }
        // 3) Elementor: hidden input name="form_id" equals settings.form_id
        const elHidden = document.querySelector('form input[name="form_id"][value="'+CSS.escape(settings.form_id)+'"]');
        if(elHidden){ return elHidden.closest('form'); }
        // 4) CF7: hidden _wpcf7 equals id
        const cf7Hidden = document.querySelector('form input[name="_wpcf7"][value="'+CSS.escape(settings.form_id)+'"]');
        if(cf7Hidden){ return cf7Hidden.closest('form'); }
        // 5) Gravity Forms: hidden gform_submit equals id
        const gfHidden = document.querySelector('form input[name="gform_submit"][value="'+CSS.escape(settings.form_id)+'"]');
        if(gfHidden){ return gfHidden.closest('form'); }
        // 6) Fluent Forms: hidden _fluentform_id equals id or data-form-id attribute
        const ffHidden = document.querySelector('form.frm-fluent-form input[name="_fluentform_id"][value="'+CSS.escape(settings.form_id)+'"]');
        if(ffHidden){ return ffHidden.closest('form'); }
        const ffData = document.querySelector('form.frm-fluent-form[data-form-id="'+CSS.escape(settings.form_id)+'"]');
        if(ffData){ return ffData; }
        // 7) Forminator: hidden input name="form_id", data-form-id, or wrappers/ids matching forminator-module-<id> / forminator-form-<id>
        const foHidden = document.querySelector('form.forminator-custom-form input[name="form_id"][value="'+CSS.escape(settings.form_id)+'"]');
        if(foHidden){ return foHidden.closest('form'); }
        // Match by data-form-id on the <form>
        const foData = document.querySelector('form.forminator-custom-form[data-form-id="'+CSS.escape(settings.form_id)+'"]');
        if(foData){ return foData; }
        // If form_id is numeric string, prefer forminator-module-<id> as the form element id
        try {
            if(/^\d+$/.test(String(settings.form_id))){
                const byModuleId = document.getElementById('forminator-module-' + settings.form_id);
                if(byModuleId && byModuleId.tagName && byModuleId.tagName.toLowerCase() === 'form'){ return byModuleId; }
                const byFormId = document.getElementById('forminator-form-' + settings.form_id);
                if(byFormId && byFormId.tagName && byFormId.tagName.toLowerCase() === 'form'){ return byFormId; }
                // Wrapper with id forminator-module-<id>, find inner form
                const moduleWrap = document.getElementById('forminator-module-' + settings.form_id);
                if(moduleWrap && moduleWrap.querySelector){
                    const inner = moduleWrap.querySelector('form.forminator-custom-form');
                    if(inner){ return inner; }
                }
            }
        } catch(e){ /* noop */ }
        return null;
    }

    function init(){
        const configMeta = document.querySelector("meta[name='ssp-config-path']");
        if(!configMeta){ return; }
        const versionMeta = document.querySelector("meta[name='ssp-config-version']");
        let version_suffix = '';
        if(versionMeta){
            const v = versionMeta.getAttribute('content');
            if(v){ version_suffix = '?ver=' + encodeURIComponent(v); }
        }
        const configPath = configMeta.getAttribute('content');
        const configUrl = window.location.origin + configPath + 'forms.json' + version_suffix;

        function markWrappers(form){
            try{
                form.querySelectorAll('input,select,textarea').forEach(inp => {
                    if(!inp.closest('[data-ssp-field]')){
                        const wrappers = [
                            inp.closest('.elementor-field-group'),
                            inp.closest('.wpcf7-form-control-wrap'),
                            inp.closest('.gfield'),
                            inp.closest('.forminator-field'),
                        ].filter(Boolean);
                        if(wrappers[0]) wrappers[0].setAttribute('data-ssp-field','1');
                    }
                });
            }catch(e){ /* noop */ }
        }

        function attachFromConfig(json){
            if(!Array.isArray(json)) return;
            json.forEach(settings => {
                const form = findFormBySettings(settings);
                if(form){
                    markWrappers(form);
                    attachValidationToForm(form);
                }
            });
        }

        function attachFallbackAll(){
            // Fallback: attach to any known form selector on the page to ensure validation even if config lookup fails
            const forms = document.querySelectorAll(
                ".wpcf7 form, .wpcf7-form, .gform_wrapper form, .wpforms-container form, .elementor-form, .wsf-form, .frm-fluent-form, .brxe-form, .brxe-brf-pro-forms, .wp-block-kadence-form form, .forminator-custom-form"
            );
            forms.forEach(form => {
                if(!form.__sspValidationAttached){
                    markWrappers(form);
                    attachValidationToForm(form);
                }
            });
        }

        fetch(configUrl)
            .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(json => {
                attachFromConfig(json);
                // Always run fallback in case some forms aren't represented in config or selectors differ
                attachFallbackAll();
            })
            .catch(()=>{
                // If config can't be loaded, still try to attach validation to known forms
                attachFallbackAll();
            });
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();