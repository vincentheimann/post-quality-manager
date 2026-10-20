---
id: 83
type: story
title: "An Image Prompt agent turns a post into an image-generation prompt written in the blog's visual style"
parent: none
covers: [O10]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# An Image Prompt agent turns a post into an image-generation prompt written in the blog's visual style

## Description

Owner request (2026-10-20): the next feature is a prompt generator for image generation, not an image generator. A blogger who has written a post needs an illustration and today leaves the plugin to describe the post to an image tool by hand, in words that rarely match the blog's look. This story adds a seventh command, Image Prompt: the workflow reads the post's content, and a Claude agent pre-filled with the site's visual baseline (the graphical style the blog's images follow, set once by an Administrator) writes one prompt that an image generator can take as is. The result is stored and shown with the post's other results, with a Copy control; the plugin never calls an image generator and never writes to the media library. The owner's part (hitl): the visual-baseline presets and their wording, and the final copy of the new setting and result card, as for the voice presets of story 26.

## Acceptance Criteria

1. **A visual baseline is a site setting**
   **Given** an Administrator on Settings → AI
   **When** they open the Visual Style section
   **Then** they pick a preset or Custom, and Custom holds their own description of the blog's imagery (medium, palette, mood, composition, what never appears); the preset's text is read-only and shown, as the Brand Voice section does
2. **The command exists beside the others**
   **Given** a post and a user allowed to run paid agents
   **When** they run Image Prompt from the Dashboard or Post Reviews
   **Then** the webhook payload names the command, carries the post's content and the visual baseline, and the result lands in the post's history like every other command, with its own status, tokens and timing
3. **The prompt reads the post, not the niche**
   **Given** two posts on different subjects on the same site
   **When** Image Prompt runs on each
   **Then** each prompt names that post's subject, scene and key elements in generator-neutral language, and both carry the same visual baseline; nothing in the agent's instructions names a niche (`memory/lessons.md` 2026-03-27)
4. **One prompt, ready to paste**
   **Given** a finished Image Prompt result
   **When** the user opens it
   **Then** the card shows one prompt as plain text with a Copy control, the visual baseline it used and the model and date, and the prompt holds no HTML, no URLs from the post and no personal names unless the post is about that person
5. **Pro only, refused gently**
   **Given** a Free site, or a user without the paid-agents capability
   **When** they look for Image Prompt
   **Then** the Free build has no trace of it (ADR 0014) and the Pro build refuses as the other paid agents do, with the same gate copy
6. **The workflow template carries it**
   **Given** a site whose workflow predates this story
   **When** the handshake runs
   **Then** the n8n Workflow page reports the outdated version and offers Update workflow or the re-import, and the Free template derived from the Pro one has no Image Prompt branch
7. **Harness and tests**
   **Given** the CLI harnesses and the Jest suites
   **When** they run
   **Then** rows cover the setting's sanitizer (preset, Custom, an unknown key), the gate on both builds, the payload fields and the result card

## Boundaries

- Must not change: the six existing agents, their prompts and payload fields; the Blog Profile, Brand Voice and Editorial Standards settings; the Insert into Post flow (Takeaways and Sources only); the quota and token accounting of the other commands beyond adding this one.
- Out of scope: generating an image, calling an image provider, storing images or writing to the media library; a featured-image picker; per-author visual styles (story 37's layer applies to text only); prompts for a specific generator's syntax.

## References

- tiers — `docs/adr/0003-freemium-plan-and-role-based-access.md` (Free = Review + Takeaways; paid = everything else) and `docs/adr/0014-two-builds-from-one-source.md` (Pro markers, the derived Free template)
- the setting pattern — `_bmad-output/backlog/story-ai-prompts-context-ux-review.md` (story 26: a preset is the setting, Custom stays visible) and `_bmad-output/backlog/story-author-voice-on-profile.md` (story 37, the per-author layer) and `memory/lessons.md` 2026-07-23, 2026-10-07
- the command pattern — `memory/llm-options-architecture.md` (model registry, payload-driven request params, the template version handshake)
- the journey — `docs/user-paths.md`, B3 Act on a post
- copy — `docs/brand.md`, Copy Rules
- design — none yet; the result card follows the Post Reviews cards; offer `bmad-ux` at refinement for the Visual Style section

## Notes

- Filed 2026-10-20 at the owner's request ("a prompt generator for image generation, not an image generator; it parses the content of the post and the result is mixed with an agent pre-filled with the graphical baseline of the blog style"). Not ranked by `/prioritize`.
- Assumption: Image Prompt is a paid agent (O2: Free = Review + Takeaways, paid = everything else), capability `pqm_run_paid_agents`, counted against the Author quota like the other paid commands.
- Assumption: the visual baseline is one site-wide setting beside Brand Voice on Settings → AI (presets + Custom), stored in the plugin's settings array and sent in the payload as text; the agent's own per-agent instructions, temperature, model and effort exist as for every other agent.
- Assumption: one prompt per run, for the post's lead image, written for no generator in particular (no Midjourney parameters, no weights); a user who needs several images runs it again after editing the prompt by hand.
- Assumption: the result is one text field in `result_data`, shown on Post Reviews with Copy; nothing is inserted into the post.
- Open question (owner, at refinement): which presets ship (for example editorial photography, flat illustration, 3D render, watercolor) and their wording; the owner approves them as the voice presets were approved.
- Open question (owner, at refinement): whether the prompt should also carry a short negative list (what must not appear) as a second field, or stay one text.
- Open question (owner, at refinement): whether the Image Prompt result is listed in the Dashboard tiles or only on Post Reviews.
