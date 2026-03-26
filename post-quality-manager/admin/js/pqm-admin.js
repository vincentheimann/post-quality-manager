/**
 * Post Quality Manager — Admin JS (MVP)
 *
 * Clean, focused: detail row expansion with tabs, AJAX command triggers,
 * toast feedback. No dropdowns, no unnecessary abstractions.
 */
(function () {
    'use strict';

    var DIMS = ['structure', 'readability', 'neuroscience', 'marketing', 'attractivity'];

    /* ================================================================
     *  BOOT
     * ================================================================ */
    document.addEventListener('DOMContentLoaded', function () {

        // Delegate clicks on dynamic buttons.
        document.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('.pqm-detail-toggle');
            if (toggleBtn) {
                var togglePostId = toggleBtn.getAttribute('data-post-id');
                var dRow = document.getElementById('pqm-detail-' + togglePostId);
                if (!dRow) return;

                var hidden = dRow.style.display === 'none';
                dRow.style.display = hidden ? 'table-row' : 'none';
                toggleBtn.textContent = hidden ? 'Hide' : 'Details';

                var dEl = dRow.querySelector('.pqm-detail-content');
                if (hidden && dEl && dEl.dataset.loaded !== '1') {
                    loadDetail(togglePostId, dEl);
                }
                return;
            }

            var btn = e.target.closest('[data-pqm-cmd]');
            if (!btn) return;
            e.preventDefault();
            var postId = btn.getAttribute('data-post-id');
            var command = btn.getAttribute('data-pqm-cmd');
            if (postId && command) triggerCommand(postId, command);
        });

        // Auto-resume background polling for processing items.
        document.querySelectorAll('.pqm-cell-status').forEach(function(td) {
            if (td.textContent.indexOf('Processing') !== -1) {
                var row = td.closest('.pqm-post-row');
                if (row) pollForResult(row.getAttribute('data-post-id'), 'review', null, 0);
            }
        });
    });

    /* ================================================================
     *  LOAD DETAIL
     * ================================================================ */
    function loadDetail(postId, el) {
        el.innerHTML = '<div class="pqm-loading"><span class="pqm-spinner"></span> Loading…</div>';

        fetch(pqmAdmin.restUrl + '/reviews/' + postId, {
            headers: { 'X-WP-Nonce': pqmAdmin.nonce }
        })
            .then(function (r) { return r.json(); })
            .then(function (reviews) {
                renderPanel(postId, el, reviews || []);
                el.dataset.loaded = '1';
                updateTableRow(postId, reviews || []);
            })
            .catch(function () {
                el.innerHTML = '<div class="pqm-loading" style="color:#d63638">Failed to load. Please try again.</div>';
            });
    }

    /* ================================================================
     *  RENDER TABBED PANEL
     * ================================================================ */
    function renderPanel(postId, el, reviews) {
        // Latest per command.
        var latest = {};
        reviews.forEach(function (r) {
            if (!latest[r.command]) latest[r.command] = r;
        });
        el.pqmLatest = latest;

        var tabs = [
            { id: 'review', label: 'Review' },
            { id: 'rewrite', label: 'Rewrite' },
            { id: 'history', label: 'History' },
        ];

        // Build HTML.
        var html = '';

        // Tab nav.
        html += '<div class="pqm-tabs-nav">';
        tabs.forEach(function (t, i) {
            var has = t.id === 'history' ? reviews.length > 0 : !!latest[t.id];
            html += '<button type="button" class="pqm-tab-btn' + (i === 0 ? ' is-active' : '') + '" data-tab="' + t.id + '-' + postId + '">'
                + esc(t.label)
                + (has && t.id !== 'history' ? ' <span class="pqm-tab-count">✓</span>' : '')
                + (t.id === 'history' ? ' <span class="pqm-tab-count">' + reviews.length + '</span>' : '')
                + '</button>';
        });
        html += '</div>';

        // Tab panels.
        html += panelWrap('review-' + postId, true, renderReview(latest, postId));
        html += panelWrap('rewrite-' + postId, false, renderRewrite(latest.rewrite, postId));
        html += panelWrap('history-' + postId, false, renderHistory(reviews));

        el.innerHTML = html;

        // Wire tabs.
        el.querySelectorAll('.pqm-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-tab');
                el.querySelectorAll('.pqm-tab-btn').forEach(function (b) { b.classList.remove('is-active'); });
                el.querySelectorAll('.pqm-tab-panel').forEach(function (p) { p.classList.remove('is-active'); });
                btn.classList.add('is-active');
                var panel = document.getElementById('pqm-p-' + key);
                if (panel) panel.classList.add('is-active');
            });
        });
    }

    function panelWrap(id, active, content) {
        return '<div class="pqm-tab-panel' + (active ? ' is-active' : '') + '" id="pqm-p-' + id + '">' + content + '</div>';
    }

    /* ─── Review Tab ──────────────────────────────────────── */
    function renderReview(latest, postId) {
        var r = latest.review;
        if (!r) return empty('dashicons-chart-bar', 'No review yet.', postId, 'review', 'Run Review');

        var d = r.result_data || {};
        var src = d.review || d;
        var h = '';

        // Score breakdown.
        h += '<div class="pqm-card"><h4><span class="dashicons dashicons-chart-bar"></span> Score Breakdown</h4>';
        h += '<table class="pqm-breakdown"><thead><tr><th>Dimension</th><th>Score</th><th style="width:50%"></th></tr></thead><tbody>';
        DIMS.forEach(function (dim) {
            if (!src[dim]) return;
            var s = typeof src[dim] === 'object' ? src[dim].score : src[dim];
            var c = s >= 71 ? 'green' : s >= 41 ? 'orange' : 'red';
            h += '<tr><td>' + cap(dim) + '</td><td>' + badge(s) + '</td>'
                + '<td><div class="pqm-bar-wrap"><div class="pqm-bar-track"><div class="pqm-bar-fill pqm-bar-fill-' + c + '" style="width:' + s + '%"></div></div></div></td></tr>';
        });
        h += '</tbody></table></div>';

        // Top edits.
        var edits = src.top_edits || [];
        if (edits.length) {
            h += '<div class="pqm-card"><h4><span class="dashicons dashicons-editor-ol"></span> Top Edits</h4>';
            h += '<ul class="pqm-edits">';
            edits.forEach(function (e) { h += '<li>' + esc(e) + '</li>'; });
            h += '</ul></div>';
        }

        // Summary.
        var summary = src.overall_summary || src.summary || d.summary;
        if (summary) {
            h += '<div class="pqm-card"><h4><span class="dashicons dashicons-format-aside"></span> Summary</h4>'
                + '<p style="font-size:13px;line-height:1.6;margin:0">' + esc(summary) + '</p></div>';
        }

        h += '<div class="pqm-detail-actions">'
            + actionBtn(postId, 'review', 'Re-run Review', 'dashicons-update', '')
            + '</div>';

        return h;
    }

    /* ─── Rewrite Tab ─────────────────────────────────────── */
    function renderRewrite(review, postId) {
        if (!review) return empty('dashicons-edit', 'No rewrite generated yet.', postId, 'rewrite', 'Generate Rewrite');

        var d = review.result_data || {};
        
        // n8n returns { rewrite_applied: true } when it successfully patches the DB directly.
        if (d.rewrite_applied) {
            var row = document.querySelector('.pqm-post-row[data-post-id="' + postId + '"]');
            var titleA = row ? row.querySelector('strong a') : null;
            var editLnk = titleA ? titleA.getAttribute('href') : '#';

            var h = '<div class="pqm-card" style="text-align: center; padding: 40px 20px;">';
            h += '<div style="color: #2e7d32; margin-bottom: 16px;"><span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px;"></span></div>';
            h += '<h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 600;">Rewrite Applied Successfully</h3>';
            h += '<p style="font-size: 14px; color: #50575e; max-width: 400px; margin: 0 auto 24px; line-height: 1.6;">';
            h += 'The post has been updated with the AI-generated content and set to <strong>Pending Review</strong>.<br><br>Please review and validate it using the native WordPress revision compare tool, or rollback to the previous version if needed.';
            h += '</p>';
            h += '<div class="pqm-detail-actions" style="justify-content: center; border-top: none; margin-top: 0; padding-top: 0;">';
            h += '<a href="' + editLnk + '" target="_blank" class="button button-primary">Review in Editor</a> ';
            h += '<button type="button" class="button" data-post-id="' + postId + '" data-pqm-cmd="rewrite"><span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;line-height:20px;margin-right:4px"></span>Regenerate Rewrite</button>';
            h += '</div>';
            h += '</div>';
            return h;
        }

        return empty('dashicons-edit', 'Rewrite data is empty. The workflow may not have returned rewrite_applied correctly.', postId, 'rewrite', 'Regenerate');
    }



    /* ─── History Tab ─────────────────────────────────────── */
    function renderHistory(reviews) {
        if (!reviews.length) return '<div class="pqm-empty"><span class="dashicons dashicons-backup"></span><p>No history.</p></div>';

        var h = '<div class="pqm-card"><h4><span class="dashicons dashicons-backup"></span> Review History</h4>';
        h += '<ul class="pqm-history">';
        reviews.forEach(function (r) {
            h += '<li><span class="pqm-history-cmd">' + esc(r.command) + '</span>';
            if (r.overall_score > 0) h += badge(r.overall_score);
            if (r.status) h += '<span>' + statusLabel(r.status) + '</span>';
            h += '<span class="pqm-history-date">' + esc(r.reviewed_at) + '</span></li>';
        });
        h += '</ul></div>';
        return h;
    }

    /* ================================================================
     *  TRIGGER COMMAND
     * ================================================================ */
    function triggerCommand(postId, command) {
        var isApply = command.indexOf('apply_') === 0;
        var label = isApply ? 'Apply ' + cap(command.replace('apply_', '')) : cap(command);
        var msg = isApply
            ? 'Apply this to the post? The post will be set to Pending status.'
            : 'Run "' + label + '" for this post?';
            
        showModal(label, msg, function() {
            executeCommand(postId, command, label, isApply);
        });
    }

    function executeCommand(postId, command, label, isApply) {
        var modalContent = document.getElementById('pqm-modal-content');
        if (modalContent) {
            modalContent.innerHTML = '<div class="pqm-modal-running"><span class="pqm-spinner"></span><span>' + 
                (isApply ? 'Applying...' : 'Running "' + label + '"... Please wait.') + 
                '</span></div>';
        }

        setBusy(postId, true);

        var oldDate = null;
        var el = document.querySelector('.pqm-detail-content[data-post-id="' + postId + '"]');
        if (!isApply && el && el.pqmLatest && el.pqmLatest[command]) {
            oldDate = el.pqmLatest[command].reviewed_at;
        }

        var form = new FormData();
        form.append('action', 'pqm_trigger_command');
        form.append('post_id', postId);
        form.append('command', command);
        form.append('_wpnonce', pqmAdmin.pageNonce);

        fetch(pqmAdmin.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success) {
                    if (isApply) {
                        setBusy(postId, false);
                        closeModal();
                        toast('ok', (resp.data && resp.data.message) ? resp.data.message : label + ' successful.');
                        setTimeout(function () { window.location.reload(); }, 800);
                    } else {
                        closeModal();
                        toast('info', cap(command) + ' started asynchronously!');
                        pollForResult(postId, command, oldDate, 0);
                    }
                } else {
                    setBusy(postId, false);
                    closeModal();
                    toast('err', (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Failed.'));
                }
            })
            .catch(function () {
                setBusy(postId, false);
                closeModal();
                toast('err', 'Network error.');
            });
    }

    /* ================================================================
     *  MODAL UI
     * ================================================================ */
    function showModal(title, text, onConfirm) {
        closeModal();
        var html = '<div class="pqm-modal-overlay" id="pqm-custom-modal"><div class="pqm-modal" id="pqm-modal-content">'
            + '<h2>' + esc(title) + '</h2>'
            + '<p>' + esc(text) + '</p>'
            + '<div class="pqm-modal-actions">'
            + '<button type="button" class="button" id="pqm-modal-cancel">Cancel</button>'
            + '<button type="button" class="button button-primary" id="pqm-modal-confirm">Confirm</button>'
            + '</div></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
        document.getElementById('pqm-modal-cancel').addEventListener('click', closeModal);
        document.getElementById('pqm-modal-confirm').addEventListener('click', onConfirm);
    }

    function closeModal() {
        var m = document.getElementById('pqm-custom-modal');
        if (m) m.remove();
    }

    /* ================================================================
     *  POLL FOR RESULT
     * ================================================================ */
    function pollForResult(postId, command, oldDate, attempt) {
        if (attempt > 30) { // 90 seconds timeout
            setBusy(postId, false);
            closeModal();
            toast('err', cap(command) + ' timed out waiting for n8n. Please refresh later.');
            return;
        }

        fetch(pqmAdmin.restUrl + '/reviews/' + postId, {
            headers: { 'X-WP-Nonce': pqmAdmin.nonce }
        })
            .then(function (r) { return r.json(); })
            .then(function (reviews) {
                var latest = null;
                if (Array.isArray(reviews)) {
                    for (var i = 0; i < reviews.length; i++) {
                        if (reviews[i].command === command) {
                            latest = reviews[i];
                            break;
                        }
                    }
                }

                if (latest && latest.status !== 'processing' && (!oldDate || latest.reviewed_at !== oldDate)) {
                    setBusy(postId, false);
                    closeModal();
                    toast('ok', cap(command) + ' finished successfully!');
                    var el = document.querySelector('.pqm-detail-content[data-post-id="' + postId + '"]');
                    if (el) {
                        el.dataset.loaded = '0';
                        loadDetail(postId, el);
                        
                        var dRow = document.getElementById('pqm-detail-' + postId);
                        if (dRow && dRow.style.display === 'none') {
                            dRow.style.display = 'table-row';
                            // Note: toggle button might be created dynamically by updateTableRow inside loadDetail,
                            // so we delay changing its text slightly to ensure it exists.
                            setTimeout(function() {
                                var tblBtn = document.querySelector('.pqm-detail-toggle[data-post-id="' + postId + '"]');
                                if (tblBtn) tblBtn.textContent = 'Hide';
                            }, 50);
                        }
                    }
                } else {
                    if (latest && latest.status === 'processing') {
                        updateTableRow(postId, reviews);
                    }
                    setTimeout(function () { pollForResult(postId, command, oldDate, attempt + 1); }, 3000);
                }
            })
            .catch(function () {
                setTimeout(function () { pollForResult(postId, command, oldDate, attempt + 1); }, 3000);
            });
    }

    function setBusy(postId, on) {
        document.querySelectorAll('[data-post-id="' + postId + '"][data-pqm-cmd]').forEach(function (b) {
            b.disabled = on;
            b.style.opacity = on ? '.5' : '';
        });
    }

    /* ================================================================
     *  SEAMLESS DOM UPDATE
     * ================================================================ */
    function updateTableRow(postId, reviews) {
        var row = document.querySelector('.pqm-post-row[data-post-id="' + postId + '"]');
        if (!row) return;

        var latestReview = null;
        var hasRewrite = false;
        var hasReview = false;
        reviews.forEach(function (r) {
            if (r.command === 'review' && !latestReview) latestReview = r;
            if (r.command === 'review') hasReview = true;
            if (r.command === 'rewrite') hasRewrite = true;
        });

        if (latestReview) {
            var scoreTd = row.querySelector('.pqm-cell-score');
            if (scoreTd) scoreTd.innerHTML = badge(latestReview.overall_score);

            var statusTd = row.querySelector('.pqm-cell-status');
            if (statusTd) statusTd.innerHTML = esc(statusLabel(latestReview.status));

            var dateTd = row.querySelector('.pqm-cell-date');
            if (dateTd) {
                var dStr = latestReview.reviewed_at;
                if (!dStr || dStr.indexOf('0000') === 0) {
                    dateTd.innerHTML = '<em>Never</em>';
                } else {
                    var parts = dStr.split(/[- :]/);
                    dateTd.innerHTML = parts.length >= 5 
                        ? esc(parts[0] + '-' + parts[1] + '-' + parts[2] + ' ' + parts[3] + ':' + parts[4])
                        : esc(dStr);
                }
            }
        }

        var progressTd = row.querySelector('.pqm-cell-progress');
        if (progressTd) {
            progressTd.innerHTML = '<div class="pqm-pills">'
                + '<span class="pqm-pill ' + (hasReview ? 'pqm-pill-done' : 'pqm-pill-pending') + '">'
                + (hasReview ? '✓ ' : '— ') + 'Review</span>'
                + '<span class="pqm-pill ' + (hasRewrite ? 'pqm-pill-done' : 'pqm-pill-pending') + '">'
                + (hasRewrite ? '✓ ' : '— ') + 'Rewrite</span>'
                + '</div>';
        }

        var actionsTd = row.querySelector('.pqm-cell-actions');
        if (actionsTd && hasReview && !actionsTd.querySelector('.pqm-detail-toggle')) {
            actionsTd.innerHTML = '<button type="button" class="button button-small pqm-detail-toggle" data-post-id="' + postId + '">Details</button>';
        }
    }

    /* ================================================================
     *  TOAST
     * ================================================================ */
    function toast(type, msg) {
        document.querySelectorAll('.pqm-toast').forEach(function (t) { t.remove(); });
        var icons = { ok: 'dashicons-yes-alt', err: 'dashicons-warning', info: 'dashicons-info' };
        var el = document.createElement('div');
        el.className = 'pqm-toast pqm-toast-' + type;
        el.innerHTML = '<span class="dashicons ' + (icons[type] || icons.info) + '"></span><span>' + esc(msg) + '</span>';
        document.body.appendChild(el);
        setTimeout(function () { if (el.parentNode) el.remove(); }, 5000);
    }

    /* ================================================================
     *  HELPERS
     * ================================================================ */
    function empty(icon, msg, postId, cmd, label) {
        return '<div class="pqm-empty"><span class="dashicons ' + icon + '"></span><p>' + esc(msg) + '</p>'
            + actionBtn(postId, cmd, label, 'dashicons-update', 'button-primary')
            + '</div>';
    }
    function actionBtn(postId, cmd, label, icon, cls) {
        return '<button type="button" class="button ' + cls + '" data-post-id="' + postId + '" data-pqm-cmd="' + cmd + '">'
            + '<span class="dashicons ' + icon + '" style="font-size:14px;width:14px;height:14px;line-height:20px;margin-right:4px"></span>'
            + esc(label) + '</button>';
    }

    function badge(score) {
        var c = score >= 71 ? 'green' : score >= 41 ? 'orange' : 'red';
        return '<span class="pqm-score-badge pqm-score-' + c + '">' + score + '</span>';
    }

    function statusLabel(s) {
        return { published: '🟢 published', needs_revision: '🟠 Needs Revision', major_revision: '🔴 Major Revision' }[s] || s;
    }

    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }


})();
