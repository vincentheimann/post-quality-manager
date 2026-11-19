---
id: 140
type: bug
title: "The readme has a broken n8n sentence and a caption that misses its screenshot"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# The readme has a broken n8n sentence and a caption that misses its screenshot

## Description

Two lines of `post-quality-manager/readme.txt`, which the WordPress.org directory shows and its reviewers read, are wrong. Under External services, the n8n entry ends "You run your own n8n instance; n8n's terms and privacy policy.": the clause after the semicolon has no verb. Under Screenshots, caption 1 says "Click Review on a post to send it to your workflow.", but `assets/screenshot-1.png` shows each row's Details button and the Review selected bar, no Review button.

## Reproduction

1. Story 38's directory preview on 4.0.5 (sitting D, 2026-11-19): Plugins → Add New Plugin → More Details on the card, tabs Description and Screenshots; the owner's Claude in Chrome read.
2. Actual: the sentence and the caption as above.
3. Expected: the n8n line reads as a sentence; caption 1 names what the image shows.

## Acceptance Criteria

1. **The n8n line reads as a sentence**
   **Given** the readme's External services section
   **When** a reader reaches the n8n entry's last line
   **Then** it reads as a full sentence and keeps both links
2. **Caption 1 matches its screenshot**
   **Given** `assets/screenshot-1.png` (Post Reviews with Details buttons and Review selected)
   **When** the directory shows caption 1
   **Then** it names only what the image shows and keeps "Demo posts, fictional scores."

## References

- source — story 38's plan, E looks sitting D (P041), 2026-11-19
- related — `docs/brand.md` § Imagery (captions)

## Notes

- Decision (owner, 2026-11-19): fixed before the submission, in a 4.0.7; joins story 38's `after`.
