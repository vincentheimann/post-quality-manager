---
title: "A distribution data sheet lists every field each platform needs, with its value or its owner"
ticket: 47
status: done
---

One document, no code. Delivered 2026-10-09 as `docs/distribution.md`: one section per platform (plugin header, WordPress.org account and readme header and sections and assets, Freemius product, GitHub repository, postquality.com), one line per field with its value, the canon that owns it, or an `OWNER:` / `CHECK:` marker. Values come from ADR 0013, `docs/brand.md` § Naming, the plugin header, the delivery-pipeline memory and a read of the repository's front through `gh repo view` (description, topics, website all empty; Discussions off). `README.md` "Repository layout" lists the file.

Open markers left for the owner: the wordpress.org username, story 41's assets, the Freemius account, mail forwarding at the registrar, Discussions on or off. `CHECK:` rows (`Tested up to`, readme tags, Freemius SDK version and sandbox, fees) are confirmed by the story that uses them.

Verification: every field named in the ticket's acceptance criteria has a row; no `OWNER:` marker was replaced by an invented value; stories 38, 44, 45, 46 and 48 can take their inputs from the sheet. Status `built`; done is the owner's mark.
