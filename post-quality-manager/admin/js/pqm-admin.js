/**
 * Post Quality Manager — Admin JS for the Settings and n8n Workflow pages.
 *
 * The Settings tabs, blog-profile / Editorial Standards preset choice,
 * per-agent model controls, the connection ping, the workflow status refresh,
 * the update notice's re-import link and the Workflow File section's Copy
 * rows. The Dashboard and Post Reviews are React pages (src/, ADR 0005) and
 * never load this file; the setup dialog on the n8n Workflow page is React
 * too (src/setup/, story 36).
 */
(function () {
    'use strict';

    /* ================================================================
     *  BOOT
     * ================================================================ */
    document.addEventListener('DOMContentLoaded', function () {

        initSettingsTabs();
        initSettingsPage();
        initModelControls();
        initPatternLinks();
        initTestConnection();
        initWorkflowStatusRefresh();
        initReimportLink();
        initCopyRows();
    });

    /* ================================================================
     *  SETTINGS TABS (story 27)
     *  Tabs switch panels over the one form, so a save still posts every
     *  section. The active tab lives in the hash (#tab-<slug>, never an
     *  element id, so the page does not jump) and rides the referer back
     *  after a save. Section anchors (#pqm-cron-settings…) open their
     *  panel and scroll. Skipped: a hashchange listener — add it when a
     *  same-page link changes the hash.
     * ================================================================ */
    function initSettingsTabs() {
        var nav = document.querySelector('.pqm-tabs');
        if (!nav) return;
        var tabs = Array.prototype.slice.call(nav.querySelectorAll('.nav-tab'));
        var form = document.querySelector('.pqm-wrap form[action="options.php"]');
        var referer = form && form.querySelector('input[name="_wp_http_referer"]');

        function panelOf(tab) {
            return document.getElementById('pqm-panel-' + tab.hash.slice('#tab-'.length));
        }

        function activate(tab, record) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('nav-tab-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                panelOf(t).classList.toggle('is-active', on);
            });
            if (!record) return;
            history.replaceState(null, '', tab.hash);
            // options.php redirects to the referer and keeps its fragment.
            if (referer) referer.value = referer.value.split('#')[0] + tab.hash;
        }

        nav.setAttribute('role', 'tablist');
        tabs.forEach(function (tab, i) {
            var panel = panelOf(tab);
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tab.id);
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                activate(tab, true);
            });
            tab.addEventListener('keydown', function (e) {
                if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return; // Alt+Arrow = Back/Forward
                var step = document.documentElement.dir === 'rtl' ? -1 : 1; // RTL tabs run right to left
                var next = { ArrowLeft: i - step, ArrowRight: i + step, Home: 0, End: tabs.length - 1 }[e.key];
                if (next === undefined) return;
                e.preventDefault();
                next = tabs[(next + tabs.length) % tabs.length];
                activate(next, true);
                next.focus();
            });
        });

        // A field the browser rejects on submit must be visible to get its
        // message: open the first invalid field's panel (one per submit).
        var opening = false;
        if (form) {
            form.addEventListener('invalid', function (e) {
                var panel = e.target.closest('.pqm-tab-panel');
                if (opening || !panel) return;
                opening = true;
                setTimeout(function () { opening = false; });
                activate(tabs.filter(function (t) { return panelOf(t) === panel; })[0], true);
            }, true);
        }

        // Hash → tab: a tab hash names its panel, a section id resolves to the
        // panel holding it; anything else (unknown, a tab this role lacks)
        // keeps the server's first tab.
        var hash = location.hash.slice(1);
        var target = hash ? document.getElementById(hash) : null; // no element carries a tab-… id
        var found = tabs.filter(function (t) {
            return t.hash === '#' + hash || (target && panelOf(t).contains(target));
        })[0];
        activate(found || tabs[0], !!found);
        document.documentElement.classList.remove('pqm-tabs-pending'); // set inline under the tab bar
        if (found && target) target.scrollIntoView();
    }

    /* ================================================================
     *  SETTINGS PAGE (presets, agent overrides)
     * ================================================================ */
    function initSettingsPage() {
        /* Every block guards on its own elements: the Settings page renders
         * only the sections the user's capability allows (e.g. Editors get
         * the agent overrides without the AI Prompts & Context globals). */
        /* ─── Blog profile & Editorial Standards (story 26) ──────────
         * The radio is the setting: a preset shows its text muted (what
         * the AI gets), Custom reveals the textarea with the stored text. */
        bindPresetChoice('.pqm-blog-profile', document.getElementById('pqm_profile_text'),
            document.getElementById('pqm_context_field'), pqmAdmin.blogProfiles);
        bindPresetChoice('.pqm-voice-preset', document.getElementById('pqm_voice_text'),
            document.getElementById('pqm_voice_field'), pqmAdmin.voicePresets);

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

    /**
     * Radio set + muted preview + Custom field: a preset shows texts[value]
     * in the preview, Custom hides it and reveals the field (focusing its
     * textarea after a pointer pick). Never writes into the textarea; Save
     * is the commit point.
     */
    function bindPresetChoice(selector, preview, wrapper, texts) {
        var radios = document.querySelectorAll(selector);
        if (!preview || !wrapper || !radios.length) return; // Section not rendered for this user.
        texts = texts || {};
        // Focus moves only after a pointer pick: arrowing through the group
        // fires change on every radio and must stay in the group (WCAG 3.2.2).
        var byPointer = false;
        var fieldset = radios[0].closest('fieldset');
        if (fieldset) {
            fieldset.addEventListener('pointerdown', function () { byPointer = true; });
            fieldset.addEventListener('keydown', function () { byPointer = false; });
        }
        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                var text = this.value === 'custom' ? '' : (texts[this.value] || '');
                preview.textContent = text;
                preview.hidden = text === '';
                wrapper.hidden = text !== '';
                if (text === '' && byPointer) {
                    var area = wrapper.querySelector('textarea');
                    if (area) area.focus();
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

        // Delegated, so the status block's Test again (n8n provisioning,
        // entry 7) keeps working after swapWorkflowStatus() replaces it.
        // Its result line shows a failure; a passing check swaps the block.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.pqm-test-connection');
            if (!btn || btn.disabled) return;
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
                    // Errors answer a string; success answers {message,
                    // outdated}, plus status_html when the saved URL was
                    // pinged. An older workflow is connected but warned.
                    var data = resp.data || {};
                    var msg = typeof resp.data === 'string' ? resp.data : data.message;
                    showTestResult(result, !!resp.success, typeof msg === 'string' ? msg : i18n.networkError, !!data.outdated);
                    swapWorkflowStatus(data.status_html);
                })
                .catch(function () {
                    showTestResult(result, false, i18n.networkError);
                })
                .finally(function () {
                    // A Test again swapped out with its block stays as it is.
                    if (btn.isConnected) btn.disabled = false;
                });
        });
    }

    /**
     * ok: true / false / null (pending); warning turns a success amber
     * (a ping answered by an older workflow).
     */
    function showTestResult(el, ok, msg, warning) {
        if (!el) return;
        el.textContent = msg;
        el.classList.toggle('is-success', ok === true && !warning);
        el.classList.toggle('is-warning', ok === true && !!warning);
        el.classList.toggle('is-error', ok === false);
    }

    /**
     * Replace the n8n Workflow page's status block with a fresh server render,
     * then tell the page's React islands (src/kit/use-reimport-shown.js), which
     * read the block's primary button to know whether theirs must yield.
     */
    function swapWorkflowStatus(html) {
        var status = document.querySelector('.pqm-workflow-status');
        if (status && typeof html === 'string') {
            // Focus inside the old block (Test again) would drop to <body>: keep it on the new block.
            var hadFocus = status.contains(document.activeElement);
            status.outerHTML = html;
            var fresh = hadFocus && document.querySelector('.pqm-workflow-status');
            if (fresh) {
                fresh.setAttribute('tabindex', '-1');
                fresh.focus();
            }
            document.dispatchEvent(new CustomEvent('pqm:workflow-status'));
        }
    }

    /* ================================================================
     *  WORKFLOW STATUS REFRESH (workflow page, D1)
     *  A download, an import from URL or a command's report may land
     *  while the user is away: re-read the block whenever the tab
     *  regains focus. Reads WordPress only, never pings n8n; a failed
     *  read leaves the block as it is.
     * ================================================================ */
    function initWorkflowStatusRefresh() {
        if (!document.querySelector('.pqm-workflow-status')) return;

        window.addEventListener('focus', function () {
            var form = new FormData();
            form.append('action', 'pqm_workflow_status');
            form.append('_wpnonce', pqmAdmin.pageNonce);

            fetch(pqmAdmin.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (resp && resp.success && resp.data) {
                        swapWorkflowStatus(resp.data.status_html);
                    }
                })
                .catch(function () { /* silent: keep the block */ });
        });
    }

    /* ================================================================
     *  RE-IMPORT THE WORKFLOW (workflow page): the update notice's
     *  primary action brings the Workflow File section into view and
     *  moves focus to its heading. Delegated, so the button keeps
     *  working after swapWorkflowStatus() replaces the block. Without
     *  script, the link's own #pqm-workflow-file jump still works.
     * ================================================================ */
    function initReimportLink() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('.pqm-reimport');
            var heading = link && document.getElementById('pqm-workflow-file');
            if (!heading) return;
            e.preventDefault();
            heading.scrollIntoView();
            heading.focus();
        });
    }

    /* ================================================================
     *  COPY ROWS (workflow page): every value the Workflow File section
     *  asks the reader to enter in n8n, the import URL included
     *  (PQM_Admin::render_copy_value()). Clipboard refused: the value stays
     *  selected and the feedback says to press Ctrl+C.
     * ================================================================ */
    function initCopyRows() {
        var i18n = pqmAdmin.i18n || {};

        document.querySelectorAll('.pqm-copy-row').forEach(function (row) {
            var field = row.querySelector('.pqm-copy-field');
            var copyBtn = row.querySelector('.pqm-copy-button');
            var feedback = row.querySelector('.pqm-copy-feedback');
            if (!field || !copyBtn) return;
            // The import URL row also says where to paste it.
            var copied = row.closest('.pqm-import-url') ? i18n.copied : i18n.copiedValue;

            field.addEventListener('focus', function () { this.select(); });

            copyBtn.addEventListener('click', function () {
                function report(ok) {
                    showTestResult(feedback, ok, ok ? copied : i18n.copyFailed);
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
