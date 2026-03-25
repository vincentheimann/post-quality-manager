/**
 * Post Quality Manager — Admin JS
 * Minimal: confirm dialogs, detail row expansion, detail loading via REST.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        /* ── Confirm before trigger review ─────────────────────────── */
        document.querySelectorAll('.pqm-trigger-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var msg = btn.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        });

        /* ── Toggle detail rows ────────────────────────────────────── */
        document.querySelectorAll('.pqm-detail-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var postId = btn.getAttribute('data-post-id');
                var row = document.getElementById('pqm-detail-' + postId);
                if (!row) return;

                var isHidden = row.style.display === 'none';
                row.style.display = isHidden ? 'table-row' : 'none';
                btn.textContent = isHidden ? 'Hide' : 'Details';

                // Load detail content on first open.
                var content = row.querySelector('.pqm-detail-content');
                if (isHidden && content && content.dataset.loaded !== 'true') {
                    loadDetail(postId, content);
                }
            });
        });
    });

    /**
     * Fetch review details for a post via REST API.
     */
    function loadDetail(postId, container) {
        container.innerHTML = '<p class="description">Loading...</p>';

        fetch(pqmAdmin.restUrl + '/reviews/' + postId, {
            headers: {
                'X-WP-Nonce': pqmAdmin.nonce,
            },
        })
            .then(function (res) { return res.json(); })
            .then(function (reviews) {
                if (!reviews || reviews.length === 0) {
                    container.innerHTML = '<p class="description">No reviews found.</p>';
                    container.dataset.loaded = 'true';
                    return;
                }

                var html = '';
                reviews.forEach(function (review) {
                    var data = review.result_data || {};
                    html += '<h4>' + escHtml(review.command) + ' — Score: ' + review.overall_score + ' — ' + escHtml(review.reviewed_at) + '</h4>';

                    // Render breakdown scores if available (review/audit commands).
                    if (data.structure || data.review) {
                        var src = data.review || data;
                        html += '<table class="widefat"><thead><tr><th>Dimension</th><th>Score</th></tr></thead><tbody>';
                        var dims = ['structure', 'readability', 'neuroscience', 'marketing', 'attractivity'];
                        dims.forEach(function (dim) {
                            if (src[dim]) {
                                var score = typeof src[dim] === 'object' ? src[dim].score : src[dim];
                                html += '<tr><td>' + dim.charAt(0).toUpperCase() + dim.slice(1) + '</td><td>' + scoreBadge(score) + '</td></tr>';
                            }
                        });
                        html += '</tbody></table>';

                        // Top edits.
                        var edits = src.top_edits || [];
                        if (edits.length > 0) {
                            html += '<h4>Top Edits</h4><ol>';
                            edits.forEach(function (edit) {
                                html += '<li>' + escHtml(edit) + '</li>';
                            });
                            html += '</ol>';
                        }
                    }
                });

                container.innerHTML = html;
                container.dataset.loaded = 'true';
            })
            .catch(function () {
                container.innerHTML = '<p class="description" style="color:#d63638">Failed to load details.</p>';
            });
    }

    function scoreBadge(score) {
        var cls = score >= 71 ? 'green' : score >= 41 ? 'orange' : 'red';
        return '<span class="pqm-score-badge pqm-score-' + cls + '">' + score + '</span>';
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
