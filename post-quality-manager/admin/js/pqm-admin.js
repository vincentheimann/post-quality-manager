/**
 * Post Quality Manager — Admin JS for the Settings and n8n Workflow pages.
 *
 * Blog-profile / voice presets, per-agent model controls, the connection
 * ping and the workflow import-URL copy. The Dashboard and Post Reviews are
 * React pages (src/, ADR 0005) and never load this file; the setup dialog on
 * the n8n Workflow page is React too (src/setup/, story 36).
 */
(function () {
    'use strict';

    /* ================================================================
     *  BOOT
     * ================================================================ */
    document.addEventListener('DOMContentLoaded', function () {

        initSettingsPage();
        initModelControls();
        initPatternLinks();
        initTestConnection();
        initImportUrlCopy();
    });

    /* ================================================================
     *  SETTINGS PAGE (presets, agent overrides)
     * ================================================================ */
    function initSettingsPage() {
        /* Every block guards on its own elements: the Settings page renders
         * only the sections the user's capability allows (e.g. Editors get
         * the agent overrides without the AI Prompts & Context globals). */
        var contextArea = document.getElementById('pqm_website_context');
        var voiceArea = document.getElementById('pqm_brand_voice');

        /* ─── Blog profile & voice presets ──────────────────────────
         * Presets are one-shot fills of the two editable fields; the
         * textareas are always the source of truth for what the AI
         * receives. No confirmation dialogs — nothing takes effect
         * until the settings are saved. Manual edits flip the
         * selection back to Custom so a preset never claims text it
         * no longer matches. */
        if (contextArea && voiceArea) {
            var voicePresets = pqmAdmin.voicePresets || {};

            var checkRadio = function (selector, value) {
                var radio = document.querySelector(selector + '[value="' + value + '"]');
                if (radio) radio.checked = true;
            };

            document.querySelectorAll('.pqm-blog-profile').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    var profile = (pqmAdmin.blogProfiles || {})[this.value];
                    if (!profile || this.value === 'custom') return; // Custom overwrites nothing.
                    contextArea.value = profile.website_context;
                    voiceArea.value = voicePresets[profile.brand_voice_preset] || '';
                    checkRadio('.pqm-voice-preset', profile.brand_voice_preset);
                });
            });

            document.querySelectorAll('.pqm-voice-preset').forEach(function (preset) {
                preset.addEventListener('change', function () {
                    if (this.value === 'custom') return; // Keep whatever text is in the box.
                    voiceArea.value = voicePresets[this.value] || '';
                });
            });

            contextArea.addEventListener('input', function () {
                checkRadio('.pqm-blog-profile', 'custom');
            });
            voiceArea.addEventListener('input', function () {
                checkRadio('.pqm-voice-preset', 'custom');
            });
        }

        /* ─── Agent overrides: temperature sliders + reset ─────────── */
        document.querySelectorAll('.pqm-temp-range').forEach(function (range) {
            range.addEventListener('input', function () {
                this.nextElementSibling.textContent = parseFloat(this.value).toFixed(1);
            });
        });

        document.querySelectorAll('.pqm-reset-agent').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                // No confirm(): the fill only touches unsaved form fields — Save is the commit point.
                var key = this.dataset.agent;
                var defs = (pqmAdmin.agentDefaults || {})[key] || {};
                document.getElementById('pqm_agent_inst_' + key).value = '';
                var tempInput = document.getElementById('pqm_agent_temp_' + key);
                var temp = parseFloat(defs.temperature || 0).toFixed(1);
                tempInput.value = temp;
                tempInput.nextElementSibling.textContent = temp;
                var modelSelect = document.getElementById('pqm_agent_model_' + key);
                var effortSelect = document.getElementById('pqm_agent_effort_' + key);
                if (effortSelect) effortSelect.value = defs.effort || 'high';
                var patternSelect = document.getElementById('pqm_agent_pattern_' + key); // Takeaways, Sources only
                if (patternSelect) {
                    patternSelect.value = '0'; // Plain list
                    patternSelect.dispatchEvent(new Event('change')); // re-sync the Edit link
                }
                if (modelSelect) {
                    modelSelect.value = defs.model || '';
                    modelSelect.dispatchEvent(new Event('change')); // re-sync visible knobs
                }
            });
        });
    }

    /* ================================================================
     *  MODEL CAPABILITIES (settings page)
     *  The registry (localized from PHP) says which knobs each model
     *  accepts; selects live-toggle temperature/effort per agent.
     * ================================================================ */
    function initModelControls() {
        // Both controls belong to the AI Model section, absent on the reduced
        // (Editor) view — the localized effective default fills in for them.
        var globalSelect = document.getElementById('pqm_default_model');
        var customField = document.getElementById('pqm_custom_model');
        var registry = pqmAdmin.modelRegistry || {};

        function caps(model) {
            // Unknown IDs (Custom) get the conservative profile: no knobs.
            return registry[model] || { temperature: false, effortLevels: [] };
        }

        function effectiveDefault() {
            if (!globalSelect) return pqmAdmin.defaultModel || '';
            return globalSelect.value === 'custom'
                ? (customField ? customField.value.trim() : '')
                : globalSelect.value;
        }

        function refreshAgentBox(box) {
            var modelSelect = box.querySelector('.pqm-agent-model');
            if (!modelSelect) return;
            var model = modelSelect.value || effectiveDefault();
            var c = caps(model);

            var tempRow = box.querySelector('.pqm-temp-row');
            tempRow.classList.toggle('pqm-locked', !c.temperature);
            tempRow.querySelector('.pqm-temp-range').disabled = !c.temperature;
            box.querySelector('.pqm-temp-desc').classList.toggle('pqm-hidden', !c.temperature);
            box.querySelector('.pqm-temp-hint').classList.toggle('pqm-hidden', c.temperature);

            var effortSelect = box.querySelector('.pqm-agent-effort');
            var hasEffort = c.effortLevels.length > 0;
            effortSelect.disabled = !hasEffort;
            box.querySelector('.pqm-effort-desc').classList.toggle('pqm-hidden', !hasEffort);
            box.querySelector('.pqm-effort-hint').classList.toggle('pqm-hidden', hasEffort);
            Array.prototype.forEach.call(effortSelect.options, function (opt) {
                opt.disabled = c.effortLevels.indexOf(opt.value) === -1;
            });
            if (hasEffort && effortSelect.options[effortSelect.selectedIndex] && effortSelect.options[effortSelect.selectedIndex].disabled) {
                effortSelect.value = 'high'; // every effort-capable model supports 'high'
            }
        }

        function refreshAll() {
            if (globalSelect && customField) {
                customField.classList.toggle('pqm-hidden', globalSelect.value !== 'custom');
            }
            document.querySelectorAll('.pqm-agent-box').forEach(refreshAgentBox);
        }

        if (globalSelect) globalSelect.addEventListener('change', refreshAll);
        if (customField) customField.addEventListener('input', refreshAll);
        document.querySelectorAll('.pqm-agent-model').forEach(function (sel) {
            sel.addEventListener('change', function () {
                refreshAgentBox(sel.closest('.pqm-agent-box'));
            });
        });

        refreshAll(); // sync JS state with the server-rendered initial state
    }

    /* ================================================================
     *  INSERT INTO POST LINKS (settings page)
     *  The Takeaways / Sources boxes carry a pattern select; the Edit
     *  link follows the selection (Reset to Defaults dispatches a
     *  change) and hides for the plain list.
     * ================================================================ */
    function initPatternLinks() {
        document.querySelectorAll('.pqm-agent-box').forEach(function (box) {
            var select = box.querySelector('.pqm-pattern-select');
            var edit = box.querySelector('.pqm-pattern-edit');
            if (!select || !edit) return;

            select.addEventListener('change', function () {
                edit.href = edit.href.replace(/post=\d+/, 'post=' + select.value);
                edit.classList.toggle('pqm-hidden', select.value === '0');
            });
        });
    }

    /* ================================================================
     *  TEST CONNECTION (settings + workflow pages)
     * ================================================================ */
    function initTestConnection() {
        var i18n = pqmAdmin.i18n || {};
        var webhookField = document.getElementById('pqm_n8n_webhook_url');

        document.querySelectorAll('.pqm-test-connection').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var result = btn.parentElement.querySelector('.pqm-test-result');
                // No field on the workflow page: send empty, the server
                // falls back to the saved webhook URL.
                var url = webhookField ? webhookField.value.trim() : '';

                if (webhookField && !url) {
                    showTestResult(result, false, i18n.enterWebhookUrl);
                    webhookField.focus();
                    return;
                }

                btn.disabled = true;
                showTestResult(result, null, i18n.testing);

                var form = new FormData();
                form.append('action', 'pqm_test_connection');
                form.append('webhook_url', url);
                form.append('_wpnonce', pqmAdmin.pageNonce);

                fetch(pqmAdmin.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        showTestResult(result, !!resp.success, typeof resp.data === 'string' ? resp.data : i18n.networkError);
                    })
                    .catch(function () {
                        showTestResult(result, false, i18n.networkError);
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
        });
    }

    function showTestResult(el, ok, msg) {
        if (!el) return;
        el.textContent = msg;
        el.classList.toggle('is-success', ok === true);
        el.classList.toggle('is-error', ok === false);
    }

    /* ================================================================
     *  COPY WORKFLOW IMPORT URL (workflow page)
     * ================================================================ */
    function initImportUrlCopy() {
        var i18n = pqmAdmin.i18n || {};

        document.querySelectorAll('.pqm-import-url').forEach(function (wrap) {
            var field = wrap.querySelector('.pqm-import-url-field');
            var copyBtn = wrap.querySelector('.pqm-copy-import-url');
            var feedback = wrap.querySelector('.pqm-copy-feedback');
            if (!field || !copyBtn) return;

            field.addEventListener('focus', function () { this.select(); });

            copyBtn.addEventListener('click', function () {
                function report(ok) {
                    showTestResult(feedback, ok, ok ? i18n.copied : i18n.copyFailed);
                    if (feedback.pqmTimer) clearTimeout(feedback.pqmTimer);
                    feedback.pqmTimer = setTimeout(function () { showTestResult(feedback, null, ''); }, 4000);
                }
                function fallbackCopy() {
                    field.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) { /* keep selection */ }
                    report(ok);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(field.value).then(function () { report(true); }, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
    }
})();
