# Review: honesty and conversion (bmad-ux Reviewer Gate, ad-hoc lens)

_Reviewed 2026-10-22 against DESIGN.md, EXPERIENCE.md, .memlog.md (later entries win), .working/sources-extract.md, .working/journeys-and-pages-draft.md, .working/direction-navy-and-check.html, docs/policies/ (terms, privacy, refund, support), docs/brand.md, docs/distribution.md, ADR 0009, 0013, 0014, and the plugin code where a claim depends on it. Review only; the spines are unchanged._

## Overall verdict

The structure and the posture hold up: the page map has one action per page, the prerequisites come before any install, nothing creates urgency, and the spines ban invented proof. The spines cannot ship as written, though. Four committed strings are false today or leave a cost out: "VAT included", "a quota and a token limit cap the spend", "n8n Cloud or self-hosted, either works", and a cost list that never mentions n8n's own bill. All four sit where Lena decides at A2 and where Tom reaches checkout. Fix those, add the renewal, cancellation and Pro-upgrade paths, and the site meets the owner's no-dark-pattern bar and the EU/FTC disclosure baseline. Omnibus price-reduction and review rules do not apply, because the site uses neither.

## Critical

**C1. "VAT included" is false; Freemius adds tax on top.**
- *Location:* EXPERIENCE.md § Component Patterns, row FAQ ("Freemius sells and invoices, VAT included"); § Key Flows, Flow 3 step 5; the prices on the Plan card row and in the direction file's Pro card ("49 USD, EUR or CHF per year").
- *Problem:* Freemius shows net prices. It adds EU/UK VAT for consumers and US sales tax at checkout, based on the buyer's location ([Freemius EU VAT](https://freemius.com/eu-vat-uk-vat-europe/), [reverse-charge guide](https://freemius.com/blog/eu-vat-reverse-charge-guide/): a $100 plan becomes $120 for a French consumer). A price page with buy buttons is an "invitation to purchase" under UCPD Art. 7(4)(c). It must show the price including taxes, or how the tax is calculated. Saying "VAT included" while 49 becomes about 58.8 at checkout is a misleading omission plus a false statement. ADR 0013 D2 only says Freemius *remits* VAT. The spine also gives the buy buttons no action note after launch, so nothing near the button says the visitor leaves for Freemius or that tax is added there.
- *Fix:* Pricing FAQ, "Is tax included?": "Prices are before tax. Freemius, the seller of record, adds VAT or sales tax for your country at checkout and shows the total before you pay. Buying for a business with an EU VAT number? Enter it at checkout and no VAT is added." Assurance item: "Freemius handles checkout, tax, invoices and refunds." Add an after-launch action note under the three buy buttons: "Opens Freemius's secure checkout in CHF. You see the total with your country's tax before you pay." On home, the Pro card's price period: "per year, before tax". Before writing any of it, run the sandbox checkout once per currency (EUR from an EU country, CHF from Switzerland, USD from the US) to confirm what is added. Swiss VAT on CHF sales is not documented anywhere on record.

**C2. "A quota and a token limit cap the spend" is not true today.**
- *Location:* EXPERIENCE.md § Component Patterns, row What you need block (third item); Voice and Tone; Flow 1 step 4; the direction file's "You pay Anthropic per review" item.
- *Problem:* No token limit exists. ADR 0009's site-wide token limit is "decided, not built" (memory/llm-options-architecture.md), and the Usage & Limits tab holds only the Monthly Quota (`class-pqm-admin.php`, L963–984). The quota covers only Authors in the Free build: "Editors and Administrators have no limit". In Pro it covers users without the paid-agents capability, and "scheduled reviews are not counted". Lena is a solo Administrator, so nothing in the plugin caps her spend. This is the cost objection at A2, and the spine answers it with a feature that does not exist.
- *Fix:* "You pay Anthropic per review, on your own key. To cap the bill, set a monthly spend limit in your Anthropic account. In the plugin, a monthly quota limits how many reviews each Author can run." Mention the token limit only once stories 25/29 ship it, and add that string to the drift table (Content sources) so it changes with the release. Check the Anthropic Console's current name for spend limits before publishing.

**C3. n8n's own cost is left out: a hidden cost at the A2 moment.**
- *Location:* EXPERIENCE.md What you need block (first item), Requirement line, Plan card row (Free plan note: "you pay Anthropic per review"), pricing FAQ row ("the AI cost is separate"); Flow 1 step 4; Flow 3 step 5.
- *Problem:* terms § 2 says "the price covers the plugin only. You pay your own n8n hosting and your own AI provider separately". The spines name only the Anthropic bill. Lena has no n8n yet. Whichever route she takes, she pays for it: an n8n Cloud subscription after its trial, or a server for self-hosted n8n. That bill can be larger per month than Pro is per year. Leaving it out of every cost line is the hidden-cost pattern the owner ruled out.
- *Fix:* What you need, first item, add: "n8n is a cost of its own: an n8n Cloud plan, or the server you run self-hosted n8n on." Requirement line: "You need your own n8n and an Anthropic API key, each paid separately." On `/pricing/`, directly under the pack cards (not only in the FAQ), terms § 2 word for word: "The price covers the plugin only. You pay your own n8n and AI provider separately." Pricing FAQ "What else will I pay for?" gives both bills. Never quote n8n's prices; link to n8n's pricing page from Getting n8n.

**C4. "n8n Cloud or self-hosted, either works" is untested, and the setup guide cannot run on an n8n Cloud trial.**
- *Location:* EXPERIENCE.md What you need block ("n8n Cloud or self-hosted, either works"); Flow 1 step 4; the direction file's "Self-hosted or n8n Cloud"; .memlog.md entry 24.
- *Problem:* docs/user-paths.md (A4) says "The n8n Cloud path (sign-up step, Cloud URL) is untested — no Cloud instance." It is also predictably broken for the visitor most likely to try it. The setup guide's first value is an n8n API key, and n8n Cloud does not offer its public API during the free trial, only on paid plans ([n8n API docs](https://docs.n8n.io/api/), [n8n pricing](https://n8n.io/pricing/)). Lena (no n8n, follows guides) will pick the Cloud trial as the easy start, install, and bounce at the guide's first step. That is the exact A2 failure the page exists to prevent.
- *Fix:* Before launch, walk the Cloud path on a paid Cloud plan (the agentic dry run already covers the rest). Until then, What you need reads: "Tested with self-hosted n8n. On n8n Cloud, the setup guide needs n8n's API, which Cloud includes on paid plans but not during its free trial." Getting n8n on `/docs/` repeats it in its two sentences. Restore "either works" only after the Cloud walk passes, and record the walk in user-paths.md.

## High

**H1. The trust line overclaims what reaches the author.**
- *Location:* EXPERIENCE.md § Component Patterns, row Trust line ("The author receives nothing from your site."); § Honest nudges ("you hold the keys").
- *Problem:* privacy.md (short version, § 2) adds "unless you opt in to usage data or buy a Pro license". With a license, the site sends its address, plugin version and key to Freemius, and the author receives the buyer's name, email and license details. The optional opt-in sends site address, title, versions and the plugin list. Dropping the condition turns a true statement into a false one, on the page that sells Pro.
- *Fix:* "Your content goes only to your own n8n and, through it, to Anthropic. The author receives nothing from your site unless you opt in to usage data or buy Pro."

**H2. Renewal and cancellation are not disclosed enough to meet auto-renewal rules.**
- *Location:* EXPERIENCE.md Plan card row ("renewal at the same price ... beside the buttons"); the direction file's Pro card ("One yearly plan, renews at the same price."); Flow 3 step 4. No cancellation path appears anywhere in the spines.
- *Problem:* terms § 3: licenses renew yearly "unless you cancel before the renewal date; Freemius sends a reminder beforehand". "Renews at the same price" never says *automatically*, never mentions the reminder, and never says how to cancel. FTC ROSCA and the state auto-renewal laws want automatic renewal, its price and the cancellation method stated clearly before purchase. EU dark-pattern guidance treats a subscription that is easy to start and opaque to stop as a red flag. Also, terms § 4 ("When a license expires or is cancelled, the Pro features lock") does not say whether cancelling the *renewal* keeps Pro until the paid year ends. The site cannot promise either way until the terms do.
- *Fix:* Beside the buy buttons: "Renews automatically every year at the price you paid. Freemius emails you before each renewal. Cancel the renewal any time in the customer portal." Pricing FAQ "How do I cancel?" plus a `/docs/` section (see H4). The owner should first settle in terms § 3/§ 4 whether a cancelled renewal keeps Pro until the end of the paid year (Freemius's usual behaviour). If it does, add "Pro stays on until the end of the year you paid for."

**H3. After launch, the header's install button comes before the prerequisites on every page.**
- *Location:* EXPERIENCE.md § On every page (Header), Component Patterns rows Header and Menu disclosure, What you need ("No install action comes before the prerequisites"); .memlog.md entry 31.
- *Problem:* The header sits above the hero in reading and tab order, so "Install the free plugin" in the header is the first install action on `/`, before the requirement line. On `/pricing/`, `/docs/`, the policies and the 404 it is the *only* install action, with no prerequisite anywhere near it. The spine's central A2 rule ends up false for the most visible button on the site.
- *Fix:* After launch, the header button reads **Get the free plugin** and leads to `/#need`, the What you need block. The block ends with the only header-reachable WordPress.org link, "Install from WordPress.org", with the version line. The direct install links stay where prerequisites precede them: the hero (under the requirement line), the end of What you need, and Getting started. Update the Header, Menu and Button-labels rows to match.

**H4. Upgrading to Pro means replacing the plugin, and deleting it destroys the reviews. The spines miss both.**
- *Location:* EXPERIENCE.md Flow 3 step 7 ("the license key goes into the plugin, and Pro unlocks"); `/docs/` blocks in the Page map (no Pro/licence section); Assurance item and ticks ("Nothing deleted if Pro lapses").
- *Problem:* ADR 0014: "Pro is never installed by the Free build. After checkout the customer downloads the Pro build from Freemius and replaces the plugin; updates come from Freemius while the license is valid, from WordPress.org for Free." privacy § 1: "Deleting the plugin (not just deactivating it) removes the table, the settings...". Tom used Free for three weeks. If he deletes the free plugin before uploading Pro, which many people do, he loses every review, and the site never warned him. The same trap applies on lapse: a customer who goes back to the Free build to get updates may delete Pro first. "Nothing deleted if Pro lapses" is literally true of the lapse itself, but the site never says where updates come from afterwards.
- *Fix:* Flow 3 step 7: "Freemius emails the license key and a download link for the Pro build. On each site, Tom uploads it with Plugins → Add New → Upload and chooses *Replace current with uploaded*, then enters the key. Reviews and settings carry over." Add a `/docs/` section **Upgrade to Pro and manage your license** (download, replace, activate, move an activation, cancel, refund, what happens when it ends). Its first line: "Don't delete the free plugin first: deleting removes your stored reviews and settings." Verify the real story 44 flow on the Local sandbox and confirm what updates a lapsed Pro build receives before writing the lapse line's last clause.

**H5. "What counts as a site" is promised but has no source of truth.**
- *Location:* EXPERIENCE.md FAQ row and Flow 3 step 5 ("what counts as a site"); Story 57 questions table.
- *Problem:* terms § 3 says only that a license activates on 1, 5 or unlimited sites and can be moved in the customer portal. Staging and local copies, client sites, and a site whose address changes are not covered. Tom (own blog plus two client blogs) buys on this answer, and a freelancer will also ask whether client sites are allowed and what happens when a client leaves. The FAQ would end up written from nothing, against the "policies first" rule.
- *Fix:* The owner decides, then terms § 3 states it, then the FAQ copies it. Suggested FAQ once the terms agree: "A site is one WordPress install with its own address; each uses one activation from your pack. You can move an activation in the customer portal when a site changes. You may use a pack on client sites; the license stays yours, so a client site you move away from returns to Free with nothing deleted. Multisite is not supported." Add the staging/local rule only after checking how Freemius counts those activations.

**H6. Launch would leave "A worked cost example is coming" at the drop-off point.**
- *Location:* EXPERIENCE.md What you need block, Voice and Tone ("A worked cost example is coming."), Content sources (story 49), State Patterns (no launch gate); sources-extract A2.
- *Problem:* user-paths A2 names "no 'what will this cost me in API spend' guidance" as the reason "many evaluators bounce". Before launch the placeholder is acceptable. After launch it sits beside a live install button, so the biggest known objection stays unanswered at the moment of action. No state row makes story 49 a condition of the switch.
- *Fix:* Add story 49 as a condition of the "Free installable" state in State Patterns, as the install target already is. Until the worked example exists, a figure-free line keeps the owner's no-figure rule: "What a review costs depends on the post's length, the model and how many agents you run. Anthropic lists its per-token prices on its own pricing page." A plain link is not a third-party request.

## Medium

**M1. The ticks claim things that are not yet true, or that do not fit the visitor.**
- *Location:* EXPERIENCE.md rows Ticks and Action note; direction file hero (before and after).
- *Problem:* Before launch, "The free plugin is the trial" is a promise shown with a check mark, the site's sign for "confirmed". After launch, "Nothing deleted if Pro lapses" is a Pro reassurance shown to someone deciding whether to install Free. It belongs beside the buy button (§ Honest nudges says so). "The free plugin is the trial" also overstates: Free lets you try the setup and the reviews, not Rewrite or Automated Reviews, the things Tom buys Pro for.
- *Fix:* Before launch, third tick: "A free plugin to try it first". After launch, third tick: "Free: Review and Key Takeaways, no license". Keep "Nothing deleted if your license ends" on `/pricing/` and the Pro card. Action note after launch: "Start free with Review and Key Takeaways. Pro comes with a 14-day money-back guarantee."

**M2. The demo Dashboard implies a result.**
- *Location:* direction file, screenshot 1 ("Average score — last 8 weeks 63 → 71"); EXPERIENCE.md § Screenshots row 1 ("the 8-week average"); Voice and Tone bans "results figures".
- *Problem:* A fictional trend climbing eight points in eight weeks, captioned only "Demo posts, fictional", reads as "use it and your scores rise". That is an outcome claim with no owner-verified evidence (user-paths: value claims are owner-verified only). FTC guidance treats demonstrations as implied typical results.
- *Fix:* Shoot the sparkline with a flat or uneven line, or crop it out of the hero. Caption: "The Dashboard in WP Admin. Demo posts and scores, fictional; not a measured result."

**M3. The h1 "Every post scored." implies automatic, free, whole-catalog scoring.**
- *Location:* direction file h1; EXPERIENCE.md Flow 1 step 2.
- *Problem:* On Free, a review runs when someone clicks Review or Review selected. Scheduled reviews are Pro. Each review is paid to Anthropic, and 240 posts means 240 reviews. The passive claim hides both the effort and the cost. The tagline's own verb is an instruction ("Score every post").
- *Fix:* "Score every post. Know what to fix next." The tagline already uses it, and it keeps the highlight bar under "fix next". Story 60 owns final copy; carry this note to it.

**M4. No "who it's for / not for".**
- *Location:* EXPERIENCE.md What you need block, Page map row `/`; Flow 1's "honest exit".
- *Problem:* The honest exit relies on the visitor working out the fit from three requirement cards. Good plugin sites state the fit in two lines. Honesty about a non-fit also builds trust with the visitors who do fit.
- *Fix:* Two lines at the end of What you need: "A good fit if you run your own WordPress site and are happy to follow a setup guide for n8n. Not a fit if you want a hosted service with no setup, run Multisite, or your site can't be reached from the internet."

**M5. The reachability and HTTPS requirements are buried or missing.**
- *Location:* EXPERIENCE.md What you need ("site and n8n must reach each other"); Plan card and pricing rows (Pro).
- *Problem:* The site's docs.md says "A local or password-protected site cannot be reached from n8n Cloud. The Rewrite agent also needs HTTPS." The spine keeps only the jargon half and drops the HTTPS rule. Rewrite is Tom's reason to buy, and client staging sites are often plain HTTP or password-protected.
- *Fix:* What you need, first item: "n8n must be able to reach your site: a local or password-protected site can't be reached from n8n Cloud." Pro card and `/pricing/` Free vs Pro row for Rewrite: "Rewrite needs HTTPS on your site."

**M6. The CHF payment restriction is not disclosed.**
- *Location:* EXPERIENCE.md Currency switch row; Flow 3 steps 2 and 6.
- *Problem:* docs/distribution.md: "PayPal is not offered in CHF, card only." Tom picks CHF and only finds out at checkout.
- *Fix:* Under the CHF tab's packs: "In CHF, pay by card. PayPal is available in USD and EUR." Verify in the sandbox (C1).

**M7. The interest-email promise doesn't fit the pricing visitor or the privacy policy.**
- *Location:* EXPERIENCE.md Page map row `/pricing/` and § Where the actions lead (one list, one message); State Patterns intro (install and buy switch on separate triggers); Action note.
- *Problem:* A `/pricing/` visitor wants to know when Pro is on sale, but the shared note promises "you hear once, when you can install it". If the listing and the Freemius release come at different times, the one message is either early for the buyer or followed by a second one, which breaks "once". privacy § 3 keeps an email "as long as needed to answer you". It says nothing about holding it until launch, or deleting it after.
- *Fix:* `/pricing/` note before launch: "Not on sale yet. The button opens your email app; you hear once, when the first public release is out." Send the one launch message only when both Free and Pro are available, or say which one it announces. Add to privacy § 3 (source first): "An email asking to hear about the launch is kept until that one message is sent, then deleted."

**M8. The only pre-launch action fails silently for webmail users.**
- *Location:* EXPERIENCE.md State Patterns row No email app; Action note row.
- *Problem:* With no default mail client, a `mailto:` does nothing. The address is printed only in the footer, far from the button, so the site's one conversion leaks without anyone noticing, and there is no analytics to show it.
- *Fix:* Action note: "Not installable yet. The button opens your email app, or write to hello@postquality.com. You hear once, when you can install it." Prefill a body as well as the subject: "Please tell me once when Post Quality Manager can be installed."

**M9. "You hold the keys" leaves out the n8n API key the plugin stores.**
- *Location:* EXPERIENCE.md What you need lead ("you hold the keys"), Steps row 1 (three values); direction file.
- *Problem:* privacy § 1: the n8n API key is kept in the site's database, and "An n8n API key opens your whole n8n instance, so create one for this site alone, with an expiration date". The site highlights that the Anthropic key is never stored and says nothing about the key that *is*.
- *Fix:* Getting started, and one sentence in What you need: "The setup guide also asks for an n8n API key. The plugin keeps it in your site's database to set up and update the workflow, so create one for this site alone, with an expiry date. Prefer not to? Set the workflow up by hand."

**M10. The Free card's support line becomes false once the plugin is listed.**
- *Location:* direction file Free card ("Email support, best effort"); EXPERIENCE.md Plan card row; State Patterns (no "listed" row).
- *Problem:* support.md and ADR 0013 D9: once listed, Free questions go to the WordPress.org support forum. If the plugin is listed but the card still promises email, it promises something the policy no longer offers.
- *Fix:* State Patterns gains a "Listed" row: the Free card reads "Support forum on WordPress.org, best effort", linked.

**M11. "No cookies, no analytics" is true but leaves out the host's logs.**
- *Location:* EXPERIENCE.md Foundation and Footer row; privacy § 3.
- *Problem:* GitHub Pages logs visitors' IP addresses for security. The privacy text names no host. "No tracking" stays true, but a privacy policy that omits the processor serving every page is incomplete under GDPR Art. 13.
- *Fix:* privacy § 3 (source first, then copy): "The site is hosted on GitHub Pages; GitHub logs visitors' IP addresses for security, under GitHub's privacy statement." Check GitHub's current wording before quoting it.

**M12. The guarantee wording drifts from refund.md and risks reading as a statutory right.**
- *Location:* EXPERIENCE.md Assurance item row; direction file ("14-day money-back guarantee On Pro.").
- *Problem:* refund.md promises more than the spine says: "no questions asked", full amount, renewals too, and how to ask. EU consumers already have a 14-day withdrawal right for digital content unless they waive it at checkout. Presenting a legal right as a special feature is blacklisted (UCPD Annex I No. 10). The guarantee is safe because it goes further (no questions, renewals, after use), so the copy should say that.
- *Fix:* "14-day money-back guarantee. Ask within 14 days of buying or of a renewal and get the full amount back, no questions asked. Freemius processes it to your original payment method."

**M13. Trader identity on a commercial site (verify).**
- *Location:* EXPERIENCE.md Footer row ("© 2026 Vincent Heimann · GPL-2.0-or-later", email only).
- *Problem:* UCPD Art. 7(4)(b) and Swiss UWG Art. 3(1)(s) expect the trader's identity and contact address in e-commerce. Freemius, as seller of record, shows its own at checkout, which may be enough. Nobody has checked whether the author's offer page needs a postal address.
- *Fix:* The owner checks once (Swiss UWG 3(1)(s) is the likely test) and records the answer in the policy stories. If an address is required, use a business or postal-box address, not a home address.

## Low

**L1. Provider facts need one source, because ADR 0009 will change them.**
- *Location:* every "Anthropic" string (ticks, What you need, flow diagram, data line).
- *Problem:* ADR 0009 (OpenAI, Google, OpenRouter) is decided but not built. When it ships, a dozen site strings go stale at once.
- *Fix:* Add "provider list and the spend-cap wording" to the Content sources table, with ADR 0009 / stories 25, 29, 30 as the trigger.

**L2. The author line has to be literally true.**
- *Location:* EXPERIENCE.md Trust line ("built and used on the author's own blogs").
- *Problem:* Both "blogs" (plural) and "own" must hold. If one of the sites is an employer's blog, "own" overclaims.
- *Fix:* The owner confirms. Otherwise: "Built and used on the author's own blog."

**L3. The lapse wording should match terms § 4.**
- *Location:* Assurance item row ("nothing deleted if Pro lapses ... schedules ... stay").
- *Problem:* "Lapses" does not obviously cover cancellation or refund. "Schedules stay" can read as "keep running". Terms also promise that Review and Key Takeaways keep working.
- *Fix:* "Nothing deleted if your license ends. The site returns to Free: reviews, settings, schedules and role rights are kept, and Review and Key Takeaways keep working. Schedules pause until you renew."

**L4. The header interest button has no reason beside it.**
- *Location:* Header row; Accessibility Floor ("Links that leave the page say where they go").
- *Problem:* Only the hero's action note says that the button opens the email app.
- *Fix:* Mail icon on the small button, and accessible name "Tell me when it's available (opens your email app)".

**L5. A freshness signal shouldn't wait for the public repository.**
- *Location:* Trust line row ("Once the repository is public, open-source signals ... changelog link").
- *Problem:* A changelog is the cheapest honest signal at zero users. docs/release-notes/ and the readme changelog exist whatever the repository's visibility.
- *Fix:* At launch, link "What's new" beside the version line to the WordPress.org listing's changelog, or to a copy of the release notes with the same drift check as the docs.

**L6. Overbuilt for a site with zero users.**
- *Location:* Currency switch row (before launch); DESIGN.md Lockup C.
- *Problem:* Before any checkout link exists, the switch only swaps a currency code over identical figures, which the home card already states in one line. Lockup C has no spot on the site.
- *Fix:* Before sale, show "49 / 99 / 199 a year, in USD, EUR or CHF". Ship the switch together with the buy buttons. Keep C in brand.md only.

**L7. Tom's and Lena's smaller questions.**
- *Location:* Pricing FAQ row; What you need.
- *Problem:* Two questions go unanswered: whether three sites need three n8n instances (no: workflow v6 gives each site its own webhook path), and how long setup takes.
- *Fix:* FAQ: "One n8n can serve several sites; the setup guide gives each site its own workflow." Time the setup in the end-of-project agentic walk, then state it ("about N minutes once n8n runs").

**L8. Third-party marks in imagery.**
- *Location:* DESIGN.md What you need block ("a 34 px navy line icon (n8n flow, key, bill)").
- *Problem:* A "flow" glyph that echoes n8n's node-chain logo would break brand.md's no-third-party-mark rule.
- *Fix:* Use a generic workflow glyph (boxes and arrows) and name it "workflow" in the spec, not "n8n flow".

**L9. Text-only first mention on pages without a hero.**
- *Location:* Voice and Tone, Naming ("The header lockup B counts as the first mention").
- *Problem:* The lockup is an SVG. Search snippets, reader modes and copy-paste see `/pricing/` and `/docs/` with no textual "Post Quality Manager" before "Post Quality".
- *Fix:* Each page's h1 or lead names "Post Quality Manager" in text once, e.g. "Post Quality Manager Pro pricing".

## What works and should be kept

- The What you need block straight under the hero, and the requirement line above the install button: the right answer to A2, once H3 closes the header gap.
- Plain status as part of the design ("Not installable yet.", "Pre-release, not installable yet"), and an action note saying what each click does.
- The owner's rewording of "Your content goes nowhere else" to the exact fact. Apply the same discipline to H1, C2 and C4.
- The explicit ban list: no countdowns, scarcity, pre-ticked boxes, invented proof, ratings only from the listing, linked; no AI-cost figure before story 49.
- Guarantee and lapse placed beside the buy buttons: risk reversal where the decision happens.
- No buttons on the pack cards until checkout exists; one interest list and one message.
- A currency choice with no cookie, no geolocation and no personalised price.
- Screenshots framed and captioned as fictional demo content, so the site never reads as the plugin and never as a real blog's results.
- Policies and docs copied from one source with a drift check; prices never set by the site.
- Flow 1's honest exit: a visitor without n8n leaves informed instead of installing and bouncing.
- Naming discipline: lockup B as the first mention, short form after, no "PQM", "PQ" or "Content Quality" anywhere in the spines.
