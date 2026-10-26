/**
 * Post Quality Manager — Admin JS for the Settings and n8n Workflow pages.
 *
 * The Settings tabs, blog-profile / Editorial Standards preset choice and
 * per-agent model controls; on the n8n Workflow page (story 67), the one
 * body swap every reply feeds, the connection test, the REST actions
 * (Update workflow, Rotate token, Save and test, Set up by hand instead),
 * the status refresh (window focus, the dialog's close), the re-import
 * link and the Copy rows. The Dashboard and Post Reviews are React pages
 * (src/, ADR 0005) and never load this file; the setup dialog and the n8n
 * connection island on the n8n Workflow page are React too (src/setup/,
 * src/n8n-connection/).
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
        initRestActions();
        initWorkflowStatusRefresh();
        initReimportLink();
        initCopyRows();
        // The rows come back fresh with every body swap (story 67).
        document.addEventListener('postquality:workflow-status', initCopyRows);
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
            document.getElementById('pqm_context_field'), postqualityAdmin.blogProfiles);
        bindPresetChoice('.pqm-voice-preset', document.getElementById('pqm_voice_text'),
            document.getElementById('pqm_voice_field'), postqualityAdmin.voicePresets);

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
                var defs = (postqualityAdmin.agentDefaults || {})[key] || {};
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
        var registry = postqualityAdmin.modelRegistry || {};

        function caps(model) {
            // Unknown IDs (Custom) get the conservative profile: no knobs.
            return registry[model] || { temperature: false, effortLevels: [] };
        }

        function effectiveDefault() {
            if (!globalSelect) return postqualityAdmin.defaultModel || '';
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
     *  WORKFLOW PAGE: ONE BODY, ONE SWAP (story 67)
     *  Every reply that carries status_html carries the page's whole
     *  body under the H1 (PostQuality_Admin::render_workflow_body()). The swap
     *  replaces #pqm-workflow-body's content (the aria-live region
     *  stays) and dispatches postquality:workflow-status, on which the React
     *  bundle remounts the n8n connection island (src/index.js) and the
     *  Copy rows bind again. Every button below is delegated, so it
     *  keeps working after a swap.
     * ================================================================ */

    /**
     * Swap the body for a fresh server render. `focusId`: an element of
     * the new body to open (a <details>) and focus; otherwise focus that
     * was inside the old body (a pressed button, the dialog handing focus
     * back) lands on the new state's primary action, else on the status
     * block. The disclosures the owner opened stay open.
     */
    function swapWorkflowStatus(html, focusId) {
        var body = document.getElementById('pqm-workflow-body');
        if (!body || typeof html !== 'string') return;
        var hadFocus = body.contains(document.activeElement);
        var wasOpen = Array.prototype.map.call(body.querySelectorAll('details.pqm-disclosure[open]'), function (d) { return d.id; });
        body.innerHTML = html;
        wasOpen.forEach(function (id) {
            var details = document.getElementById(id);
            if (details) details.open = true;
        });
        var target = focusId && document.getElementById(focusId);
        if (target) {
            if (target.tagName === 'DETAILS') {
                target.open = true;
                target = target.querySelector('summary') || target;
            }
            target.focus();
        } else if (hadFocus) {
            var status = body.querySelector('.pqm-workflow-status');
            var primary = status && status.querySelector('.button-primary');
            if (primary) {
                primary.focus();
            } else if (status) {
                status.setAttribute('tabindex', '-1');
                status.focus();
            }
        }
        document.dispatchEvent(new CustomEvent('postquality:workflow-status'));
    }

    /**
     * The outcome line, first in the status block (the server renders it
     * with every reply; a busy state or a request that did not go through
     * writes it here). tone: 'busy' | 'success' | 'warning' | 'error'.
     */
    function showOutcome(message, tone) {
        var status = document.querySelector('#pqm-workflow-body .pqm-workflow-status');
        if (!status) return;
        var line = status.querySelector('.pqm-workflow-outcome');
        if (!line) {
            line = document.createElement('p');
            status.insertBefore(line, status.firstChild);
        }
        line.className = 'pqm-workflow-outcome' + (tone === 'busy' ? '' : ' is-' + tone);
        line.textContent = message;
    }

    /**
     * Re-read the body from WordPress (never pings n8n) and swap it in; a
     * failed read leaves the body as it is. `ifChanged` (the window-focus
     * refresh): skip the swap when the fresh status block is the current
     * one, so a typed value or an open proposal in the island survives a
     * tab switch that changed nothing.
     */
    function refreshWorkflowStatus(focusId, ifChanged) {
        var form = new FormData();
        form.append('action', 'postquality_workflow_status');
        form.append('_wpnonce', postqualityAdmin.pageNonce);

        return fetch(postqualityAdmin.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp || !resp.success || !resp.data || typeof resp.data.status_html !== 'string') return;
                if (ifChanged && statusBlockOf(resp.data.status_html) === statusBlockOf(document.getElementById('pqm-workflow-body').innerHTML)) return;
                swapWorkflowStatus(resp.data.status_html, focusId);
            })
            .catch(function () { /* silent: keep the body */ });
    }

    /** The status block's markup inside a body's HTML ('' when none). */
    function statusBlockOf(html) {
        var holder = document.createElement('div');
        holder.innerHTML = html;
        var status = holder.querySelector('.pqm-workflow-status');
        return status ? status.outerHTML : '';
    }

    /* ================================================================
     *  TEST THE CONNECTION / TEST AGAIN (workflow page)
     *  Pings the saved webhook URL through admin-ajax; the reply, passed
     *  or failed, carries the body with its message first. Busy label on
     *  the button meanwhile.
     * ================================================================ */
    function initTestConnection() {
        var i18n = postqualityAdmin.i18n || {};

        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.pqm-test-connection');
            if (!btn || btn.disabled) return;
            var label = btn.textContent;

            btn.disabled = true;
            btn.textContent = i18n.testing || label;
            showOutcome(i18n.testing || '', 'busy');

            var form = new FormData();
            form.append('action', 'postquality_test_connection');
            form.append('webhook_url', ''); // the saved URL
            form.append('_wpnonce', postqualityAdmin.pageNonce);

            fetch(postqualityAdmin.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    // Success and error replies both carry {message, status_html}
                    // once the saved URL was pinged; an error string otherwise.
                    var data = resp && typeof resp.data === 'object' && resp.data ? resp.data : {};
                    if (typeof data.status_html === 'string') {
                        swapWorkflowStatus(data.status_html);
                        return;
                    }
                    var msg = resp && typeof resp.data === 'string' ? resp.data : data.message;
                    showOutcome(typeof msg === 'string' ? msg : i18n.networkError, resp && resp.success ? 'success' : 'error');
                })
                .catch(function () {
                    showOutcome(i18n.networkError, 'error');
                })
                .finally(function () {
                    // A button swapped out with its body stays as it is.
                    if (btn.isConnected) {
                        btn.disabled = false;
                        btn.textContent = label;
                    }
                });
        });
    }

    /* ================================================================
     *  REST ACTIONS (workflow page): one handler for every control that
     *  posts to a REST route and wants the fresh body back — Update
     *  workflow (POST n8n/workflow), Rotate token (POST n8n/token), the
     *  Save and test form (POST setup/connect with its field) and the
     *  "Set up by hand instead" link (POST setup {manual: true}, whose
     *  reply carries no body: the status is re-read instead). The
     *  element names its route (data-pqm-post), an optional JSON body
     *  (data-pqm-body), its busy label (data-pqm-busy) and what to
     *  focus after the swap (data-pqm-focus). A REST error whose data
     *  carries the page's body (status_html: Rotate and Update, story
     *  74) is swapped in like a reply; any other REST error or a
     *  request that did not go through shows in the outcome line, the
     *  control enabled again. A control with data-pqm-confirm first
     *  dispatches the cancelable postquality:confirm-action {action, element}:
     *  a listener that prevents it (the n8n connection island, story 76)
     *  asks and posts itself; unheard, the click posts as before.
     * ================================================================ */
    function initRestActions() {
        var i18n = postqualityAdmin.i18n || {};

        function post(el, body) {
            if (el.pqmBusy) return;
            var control = el.tagName === 'FORM' ? el.querySelector('button[type="submit"]') : el;
            var busy = (control && control.dataset.pqmBusy) || el.dataset.pqmBusy || '';
            var label = control ? control.textContent : '';
            el.pqmBusy = true;
            if (control && control.tagName === 'BUTTON') {
                control.disabled = true;
                if (busy) control.textContent = busy;
            }
            if (busy) showOutcome(busy, 'busy');

            fetch(postqualityAdmin.restUrl + el.dataset.pqmPost, {
                method: 'POST',
                headers: { 'X-WP-Nonce': postqualityAdmin.restNonce, 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body || {})
            })
                .then(function (r) {
                    return r.json().then(function (data) { return { ok: r.ok, data: data || {} }; });
                })
                .then(function (resp) {
                    // A refusal is a REST error {code, message, data}.
                    if (!resp.ok) {
                        var error = resp.data.data;
                        if (error && typeof error.status_html === 'string') {
                            swapWorkflowStatus(error.status_html, el.dataset.pqmFocus);
                            return;
                        }
                        showOutcome(typeof resp.data.message === 'string' ? resp.data.message : i18n.networkError, 'error');
                        return;
                    }
                    if (typeof resp.data.status_html === 'string') {
                        swapWorkflowStatus(resp.data.status_html, el.dataset.pqmFocus);
                        return;
                    }
                    return refreshWorkflowStatus(el.dataset.pqmFocus);
                })
                .catch(function () {
                    showOutcome(i18n.networkError, 'error');
                })
                .finally(function () {
                    el.pqmBusy = false;
                    // A control swapped out with its body stays as it is.
                    if (control && control.isConnected && control.tagName === 'BUTTON') {
                        control.disabled = false;
                        control.textContent = label;
                    }
                });
        }

        document.addEventListener('click', function (e) {
            var el = e.target.closest && e.target.closest('[data-pqm-post]');
            if (!el || el.tagName === 'FORM' || el.disabled) return;
            e.preventDefault();
            if (el.dataset.pqmConfirm) {
                var ask = new CustomEvent('postquality:confirm-action', { cancelable: true, detail: { action: el.dataset.pqmConfirm, element: el } });
                if (!document.dispatchEvent(ask)) return; // prevented: the listener asks first
                // Unheard while the built app is on the page: its island has not mounted yet. Never post unasked.
                if (document.getElementById('pqm-n8n-connection-root')) {
                    showOutcome(i18n.stateLoading || 'The page is still loading its state. Try again in a moment.', 'error');
                    return;
                }
            }
            var body = null;
            if (el.dataset.pqmBody) {
                try { body = JSON.parse(el.dataset.pqmBody); } catch (err) { body = null; }
            }
            post(el, body);
        });

        document.addEventListener('submit', function (e) {
            var form = e.target.closest && e.target.closest('form[data-pqm-post]');
            if (!form) return;
            e.preventDefault();
            var body = {};
            Array.prototype.forEach.call(form.querySelectorAll('input[name]'), function (field) {
                body[field.name] = field.value.trim();
            });
            post(form, body);
        });
    }

    /* ================================================================
     *  WORKFLOW STATUS REFRESH (workflow page, D1)
     *  A download, an import from URL or a command's report may land
     *  while the user is away: re-read the body whenever the tab
     *  regains focus — unless the owner is typing in it. The setup
     *  dialog (src/setup/) asks for the same refresh when it closes
     *  (postquality:setup-closed, with the element to focus in `detail`), and
     *  the n8n connection island after an adoption (postquality:workflow-refresh).
     * ================================================================ */
    function initWorkflowStatusRefresh() {
        var body = document.getElementById('pqm-workflow-body');
        if (!body) return;

        window.addEventListener('focus', function () {
            var active = document.activeElement;
            if (active && body.contains(active) && active.matches('input, textarea')) return;
            refreshWorkflowStatus(undefined, true);
        });
        ['postquality:setup-closed', 'postquality:workflow-refresh'].forEach(function (type) {
            document.addEventListener(type, function (e) {
                refreshWorkflowStatus(e.detail && e.detail.focus);
            });
        });
    }

    /* ================================================================
     *  RE-IMPORT THE WORKFLOW (workflow page): the status block's fix on
     *  a hand-made setup opens the Set up by hand disclosure and moves
     *  focus to its heading. Delegated, so it keeps working after a
     *  swap. Without script, the link's own #pqm-by-hand jump still works.
     * ================================================================ */
    function initReimportLink() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('.pqm-reimport');
            var details = link && document.getElementById('pqm-by-hand');
            if (!details) return;
            e.preventDefault();
            details.open = true;
            var summary = details.querySelector('summary') || details;
            summary.scrollIntoView();
            summary.focus();
        });
    }

    /* ================================================================
     *  COPY ROWS (workflow page): every value Set up by hand asks the
     *  reader to enter in n8n, the import URL and the webhook token
     *  included (PostQuality_Admin::render_copy_value()). Clipboard refused: the
     *  value stays selected and the feedback says to press Ctrl+C. Runs
     *  again after each body swap; a row binds once.
     * ================================================================ */
    function initCopyRows() {
        var i18n = postqualityAdmin.i18n || {};

        /** ok: true / false / null (cleared). */
        function setFeedback(el, ok, msg) {
            el.textContent = msg;
            el.classList.toggle('is-success', ok === true);
            el.classList.toggle('is-error', ok === false);
        }

        document.querySelectorAll('.pqm-copy-row').forEach(function (row) {
            var field = row.querySelector('.pqm-copy-field');
            var copyBtn = row.querySelector('.pqm-copy-button');
            var feedback = row.querySelector('.pqm-copy-feedback');
            if (!field || !copyBtn || !feedback || row.pqmBound) return;
            row.pqmBound = true;
            // The import URL row also says where to paste it.
            var copied = row.closest('.pqm-import-url') ? i18n.copied : i18n.copiedValue;

            field.addEventListener('focus', function () { this.select(); });

            copyBtn.addEventListener('click', function () {
                function report(ok) {
                    setFeedback(feedback, ok, ok ? copied : i18n.copyFailed);
                    if (feedback.pqmTimer) clearTimeout(feedback.pqmTimer);
                    feedback.pqmTimer = setTimeout(function () { setFeedback(feedback, null, ''); }, 4000);
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
