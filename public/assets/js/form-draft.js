/**
 * Persists in-progress form data across locale switches (full page reload).
 * Uses sessionStorage keyed by pathname + form id.
 */
(function () {
    'use strict';

    var TTL_MS = 24 * 60 * 60 * 1000;
    var RESTORE_AFTER_LOCALE_KEY = 'form_draft_restore_after_locale';
    var hooks = {};

    function storageKey(form) {
        var id = form.getAttribute('id') || form.getAttribute('data-form-draft') || 'default';
        return 'form_draft:' + window.location.pathname + ':' + id;
    }

    function hasLaravelOldInput() {
        var meta = document.querySelector('meta[name="form-has-old-input"]');
        return meta && meta.getAttribute('content') === '1';
    }

    function serializeForm(form) {
        var fields = {};

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            var name = el.name;
            if (!name || el.type === 'file') {
                return;
            }

            if (el.type === 'checkbox') {
                if (name.endsWith('[]')) {
                    if (!fields[name]) {
                        fields[name] = [];
                    }
                    if (el.checked) {
                        fields[name].push(el.value);
                    }
                } else {
                    fields[name] = el.checked;
                }
            } else if (el.type === 'radio') {
                if (el.checked) {
                    fields[name] = el.value;
                }
            } else if (el.tagName === 'SELECT' && el.multiple) {
                fields[name] = Array.from(el.selectedOptions).map(function (opt) {
                    return opt.value;
                });
            } else {
                fields[name] = el.value;
            }
        });

        return fields;
    }

    function applyFields(form, fields) {
        Object.keys(fields).forEach(function (name) {
            var value = fields[name];
            var elements = form.querySelectorAll('[name="' + CSS.escape(name) + '"]');

            if (!elements.length) {
                return;
            }

            var first = elements[0];

            if (first.type === 'checkbox' && !name.endsWith('[]')) {
                first.checked = !!value;
                return;
            }

            if (first.type === 'radio') {
                elements.forEach(function (el) {
                    el.checked = el.value === value;
                });
                return;
            }

            if (first.tagName === 'SELECT' && first.multiple && Array.isArray(value)) {
                Array.from(first.options).forEach(function (opt) {
                    opt.selected = value.indexOf(opt.value) !== -1;
                });
                return;
            }

            elements.forEach(function (el) {
                if (el.type === 'checkbox' && name.endsWith('[]')) {
                    el.checked = Array.isArray(value) && value.indexOf(el.value) !== -1;
                } else {
                    el.value = value == null ? '' : value;
                }
            });
        });

        if (window.jQuery) {
            jQuery(form).find('select').each(function () {
                if (jQuery(this).data('select2')) {
                    jQuery(this).trigger('change.select2');
                }
            });
        }
    }

    function saveForm(form) {
        var key = form.getAttribute('data-form-draft');
        var draftKey = storageKey(form);
        var hook = key ? hooks[key] : null;
        var extra = hook && typeof hook.save === 'function' ? hook.save(form) : null;

        sessionStorage.setItem(draftKey, JSON.stringify({
            fields: serializeForm(form),
            extra: extra,
            savedAt: Date.now(),
        }));
    }

    function clearForm(form) {
        sessionStorage.removeItem(storageKey(form));
    }

    function shouldRestoreDraft() {
        if (sessionStorage.getItem(RESTORE_AFTER_LOCALE_KEY) !== '1') {
            return false;
        }

        sessionStorage.removeItem(RESTORE_AFTER_LOCALE_KEY);
        return true;
    }

    function restoreForm(form) {
        if (hasLaravelOldInput()) {
            return Promise.resolve(false);
        }

        if (!shouldRestoreDraft()) {
            clearForm(form);
            return Promise.resolve(false);
        }

        var raw = sessionStorage.getItem(storageKey(form));
        if (!raw) {
            return Promise.resolve(false);
        }

        var draft;
        try {
            draft = JSON.parse(raw);
        } catch (e) {
            sessionStorage.removeItem(storageKey(form));
            return Promise.resolve(false);
        }

        if (!draft.savedAt || Date.now() - draft.savedAt > TTL_MS) {
            sessionStorage.removeItem(storageKey(form));
            return Promise.resolve(false);
        }

        applyFields(form, draft.fields || {});

        var key = form.getAttribute('data-form-draft');
        var hook = key ? hooks[key] : null;

        if (hook && typeof hook.restore === 'function') {
            return Promise.resolve(hook.restore(form, draft.fields || {}, draft.extra || null));
        }

        return Promise.resolve(true);
    }

    function saveAll() {
        document.querySelectorAll('form[data-form-draft]').forEach(saveForm);
    }

    function restoreAll() {
        var forms = document.querySelectorAll('form[data-form-draft]');
        var chain = Promise.resolve();

        if (!forms.length) {
            return chain;
        }

        window.__formDraftRestoring = true;

        forms.forEach(function (form) {
            chain = chain.then(function () {
                return restoreForm(form);
            });
        });

        return chain.finally(function () {
            window.__formDraftRestoring = false;
        });
    }

    function registerHook(key, hook) {
        hooks[key] = hook;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href*="/locale/"]');
        if (!link) {
            return;
        }
        saveAll();
        sessionStorage.setItem(RESTORE_AFTER_LOCALE_KEY, '1');
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-form-draft]').forEach(function (form) {
            form.addEventListener('submit', function () {
                clearForm(form);
            });
        });

        document.querySelectorAll('form[data-form-draft]').forEach(function (form) {
            if (form.hasAttribute('data-form-draft-defer')) {
                return;
            }
            restoreForm(form);
        });
    });

    window.FormDraft = {
        registerHook: registerHook,
        save: saveForm,
        restore: restoreForm,
        clear: clearForm,
        saveAll: saveAll,
        restoreAll: restoreAll,
    };
})();
