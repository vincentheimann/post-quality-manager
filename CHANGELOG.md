# Changelog

## [3.13.3](https://github.com/vincentheimann/post-quality-manager/compare/v3.13.2...v3.13.3) (2026-11-10)


### Bug Fixes

* **admin:** step 1 places the n8n workflow menu right after the workflow's name (bug 120) ([2b4a5f2](https://github.com/vincentheimann/post-quality-manager/commit/2b4a5f250f8025189498f4980c4ab43a7fd65b57))

## [3.13.2](https://github.com/vincentheimann/post-quality-manager/compare/v3.13.1...v3.13.2) (2026-11-10)


### Bug Fixes

* **workflow:** a reply without results is stored as an error and always reports back (bug 121) ([bd08a95](https://github.com/vincentheimann/post-quality-manager/commit/bd08a95a41fd843603953c264cab897067f92736))

## [3.13.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.13.0...v3.13.1) (2026-11-09)


### Bug Fixes

* **admin:** Set up by hand imports the workflow from a file, for n8n 2 and n8n 3 ([086a013](https://github.com/vincentheimann/post-quality-manager/commit/086a013d6c6f2b5a3cddd4207e95321adbdf8b56))

## [3.13.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.12.4...v3.13.0) (2026-11-09)


### Features

* **admin:** a Post Quality meta box shows the post's review and runs a new one ([d5725d2](https://github.com/vincentheimann/post-quality-manager/commit/d5725d20eafc4d824fe27bd2515ab15e605a1dbf))

## [3.12.4](https://github.com/vincentheimann/post-quality-manager/compare/v3.12.3...v3.12.4) (2026-11-06)


### Bug Fixes

* **admin:** the hidden Custom model ID field stays hidden on phones ([879b8d9](https://github.com/vincentheimann/post-quality-manager/commit/879b8d95dad317a8fd15065630227f943e60ecd2))
* **admin:** the Roles scroll frame is a focusable region, its findings in memory ([bc53696](https://github.com/vincentheimann/post-quality-manager/commit/bc536963ae7db3d143d94e2b97181467e9f7d6ba))
* **admin:** the Roles table scrolls inside its own frame on phones ([480d53e](https://github.com/vincentheimann/post-quality-manager/commit/480d53efcd8773bd83900accff2ddf77f95c751b))
* **setup:** the Set up the workflow button lines up with the Anthropic key field ([ad25a84](https://github.com/vincentheimann/post-quality-manager/commit/ad25a84bf897c171be07b08e53063efe3149897f))

## [3.12.3](https://github.com/vincentheimann/post-quality-manager/compare/v3.12.2...v3.12.3) (2026-11-06)


### Bug Fixes

* **n8n:** the Parse nodes read Claude's JSON answer out of the reply around it ([5032ed6](https://github.com/vincentheimann/post-quality-manager/commit/5032ed644c543ef7490c7b5a5024ea0300daa141))

## [3.12.2](https://github.com/vincentheimann/post-quality-manager/compare/v3.12.1...v3.12.2) (2026-11-06)


### Bug Fixes

* **admin:** the model selects show the selected model's label whole ([55d1d00](https://github.com/vincentheimann/post-quality-manager/commit/55d1d00ab1e9f3e81a9c19ef9456a30840b30e0c))

## [3.12.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.12.0...v3.12.1) (2026-11-05)


### Bug Fixes

* **n8n:** after Update workflow the page shows the new version, or says n8n is still switching ([43d9657](https://github.com/vincentheimann/post-quality-manager/commit/43d9657a756cda22d4c1322af844927591751858))

## [3.12.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.11.2...v3.12.0) (2026-11-05)


### Features

* **setup:** a blogger who chose to set up n8n by hand can go back to the guided setup ([eb888d4](https://github.com/vincentheimann/post-quality-manager/commit/eb888d41141b6ad0e100fa0c89542d61eaed1c40))


### Bug Fixes

* **setup:** the page's "Use the guided setup instead" opens the dialog on Connect your n8n at once ([f42d81d](https://github.com/vincentheimann/post-quality-manager/commit/f42d81d1038d3f4b99ed18fa93665695a4ccd94e))

## [3.11.2](https://github.com/vincentheimann/post-quality-manager/compare/v3.11.1...v3.11.2) (2026-11-04)


### Bug Fixes

* **n8n:** a failed or removed setup leaves nothing behind in n8n, or says what it left ([ee0689f](https://github.com/vincentheimann/post-quality-manager/commit/ee0689fa4d056b30c395a7a56362b02f072e01fc))

## [3.11.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.11.0...v3.11.1) (2026-11-04)


### Bug Fixes

* **n8n:** Update workflow connects Rewrite on a site set up without a WordPress credential ([b77f4ed](https://github.com/vincentheimann/post-quality-manager/commit/b77f4ede04856d4706ea4528b0b4902b3bd60ce5))

## [3.11.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.10.2...v3.11.0) (2026-11-04)


### Features

* **models:** offer Claude's current models, with Sonnet 5.5 as the default ([a0f139b](https://github.com/vincentheimann/post-quality-manager/commit/a0f139b19b7dec66e36723903149e5c12ef7b644))


### Bug Fixes

* **n8n:** workflow version 9 gives thinking models and long posts room ([065e9c2](https://github.com/vincentheimann/post-quality-manager/commit/065e9c2530466359b3d387b224b2b1ca62201d0a))

## [3.10.2](https://github.com/vincentheimann/post-quality-manager/compare/v3.10.1...v3.10.2) (2026-11-03)


### Bug Fixes

* **release:** a re-run finds its version on Freemius through the latest deployment (story 58) ([7f523ce](https://github.com/vincentheimann/post-quality-manager/commit/7f523ce6659ddd0b607aeb6040dfac2f6a1d3288))

## [3.10.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.10.0...v3.10.1) (2026-11-02)


### Bug Fixes

* **release:** read an empty close/open tag pair as Freemius prints it (story 58) ([f5c8fd5](https://github.com/vincentheimann/post-quality-manager/commit/f5c8fd509c89391f2abc50c6a07ca1e1176478bc))

## [3.10.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.9.0...v3.10.0) (2026-11-02)


### Features

* **admin:** the n8n Workflow page offers the setup steps still open ([9e59772](https://github.com/vincentheimann/post-quality-manager/commit/9e5977263fc9c525757508821cc2126c08876ba6))
* **admin:** the n8n Workflow page shows one next step for the site's state ([7c7550a](https://github.com/vincentheimann/post-quality-manager/commit/7c7550a2f40f7cf439143cd92e396505015bd7bd))
* **admin:** the update notice carries the one primary action, Re-import the workflow ([d131f1c](https://github.com/vincentheimann/post-quality-manager/commit/d131f1c21f94f89e8bf97fed07ebd532411adde9))
* **brand:** the WordPress.org banners and screenshots, the outlined lockup, the imagery direction (story 41) ([d4b0316](https://github.com/vincentheimann/post-quality-manager/commit/d4b03167ac61972adf346d0523f38d71dee85826))
* **dashboard:** a feedback card opens a prefilled email to hello@postquality.com (story 79) ([7c30d5a](https://github.com/vincentheimann/post-quality-manager/commit/7c30d5a37efc0d3d6a1ca48c31dc68c563163eda))
* every stored name carries the postquality prefix, and existing sites keep their data (story 91) ([7446e5f](https://github.com/vincentheimann/post-quality-manager/commit/7446e5f591a45fcb16cc24c10c130d76afa51c64))
* **i18n:** translations come from WordPress.org language packs; privacy guide text (story 95) ([e6098fe](https://github.com/vincentheimann/post-quality-manager/commit/e6098fe344e95337d4fff37725b72fb61253cef8))
* **launch:** the measured cost example, the readme FAQ and screenshots, the launch plan (story 49) ([853d218](https://github.com/vincentheimann/post-quality-manager/commit/853d2184348b7da89c72a7828e3843e40e5be0c2))
* **n8n:** an Administrator adopts an existing manual setup ([663369c](https://github.com/vincentheimann/post-quality-manager/commit/663369cf921a448de1462e08664515b42fd2a356))
* **n8n:** an Administrator rotates the webhook token from the plugin ([576b68b](https://github.com/vincentheimann/post-quality-manager/commit/576b68b0a85aceabfb02a0267fb2ba7df87108fd))
* **n8n:** one click updates the workflow to the bundled template ([b0f7a6f](https://github.com/vincentheimann/post-quality-manager/commit/b0f7a6fda3144811cf3365b9825990c138223f43))
* **n8n:** Start over on the setup, and a setup map that remembers its instance ([94f74b6](https://github.com/vincentheimann/post-quality-manager/commit/94f74b68dbbda9665d43b238878bd859a146c359))
* **n8n:** the connection state tells the truth after the fact (story 74) ([3ec41d5](https://github.com/vincentheimann/post-quality-manager/commit/3ec41d5091f083ccbb3e4721cd2e7ce07f69519a))
* **n8n:** the handshake reports a mis-wired workflow in one sentence ([46ebb81](https://github.com/vincentheimann/post-quality-manager/commit/46ebb814dffa0b4ed29ce4c7464b25846da767c5))
* **n8n:** the stored n8n API key shows its last four characters ([ab5f6fb](https://github.com/vincentheimann/post-quality-manager/commit/ab5f6fb0bc14857d65cd5aed55ad700892a9152c))
* **n8n:** the test ping proves the round trip, template v7 calls the site back (story 2) ([49d8fa4](https://github.com/vincentheimann/post-quality-manager/commit/49d8fa45fd908975bcdb509821843efa0dae8529))
* **n8n:** Update workflow asks before replacing an adopted workflow (story 76) ([5342179](https://github.com/vincentheimann/post-quality-manager/commit/53421798b4a10ea2938df036ec35176ad2baef0c))
* **readme:** a complete readme for the WordPress.org directory, under 10 KB (story 95) ([577f14f](https://github.com/vincentheimann/post-quality-manager/commit/577f14f81af215595d34a5378ddbc9177ececb1e))
* **setup:** the first review gives up on time and says why nothing came back (story 2, goal b) ([ed06a8b](https://github.com/vincentheimann/post-quality-manager/commit/ed06a8b1de95d083e8cd07eb6a4af90449f3b95c))
* **site:** story 85, the policies and records behind postquality.com's hygiene slice ([e7c63c0](https://github.com/vincentheimann/post-quality-manager/commit/e7c63c051f3a108d8ae3f42dd1b51c1cca8a2860))
* **site:** story 86 build 1, the policy edits and records behind the Navy and check site ([4143206](https://github.com/vincentheimann/post-quality-manager/commit/4143206991da83e87cfc664f99132dac8441610c))
* **site:** story 86 build 2 records; Pro support answered within 5 business days ([718e19c](https://github.com/vincentheimann/post-quality-manager/commit/718e19c71080d6a42c3b44998d16b50c08437acc))


### Bug Fixes

* **admin:** the plugin calls itself Post Quality, never Content Quality ([21ff51e](https://github.com/vincentheimann/post-quality-manager/commit/21ff51e875e0a1846f603156dd98323f84ce143e))
* **freemius:** a site that opted out of usage data sees no deactivation form and sends no stored answer at deletion (bug 90) ([2eb523d](https://github.com/vincentheimann/post-quality-manager/commit/2eb523de00c0a56b9715557d90109e17ea9f49f6))
* **freemius:** a site that skipped the opt-in sends nothing when it is deactivated and deleted (bug 90) ([4c3dd70](https://github.com/vincentheimann/post-quality-manager/commit/4c3dd70e15bbf430808363681e71a4f261c54ebe))
* **freemius:** every Upgrade link the SDK renders opens the public pricing page (bug 59) ([33a3d01](https://github.com/vincentheimann/post-quality-manager/commit/33a3d016479ce071374db66c971e972a04f358c5))
* **freemius:** Pro waiting for its license asks for it, and a leftover opt-in notice goes (story 65) ([d97a8b7](https://github.com/vincentheimann/post-quality-manager/commit/d97a8b78307a97b139fa3a00a5d9582ee493f33b))
* **license:** a lapsed Pro license says so, with a link to renew (bug 93) ([2733cc4](https://github.com/vincentheimann/post-quality-manager/commit/2733cc4562a1aedaeda430711be4ca631013b715))
* **n8n:** claim the provisioning lock atomically at the database ([0bd6833](https://github.com/vincentheimann/post-quality-manager/commit/0bd6833370d652add6a414ad2af7cce646a5e5eb))
* **n8n:** one provisioning run at a time, a revoked Application Password, honest activation and ping sentences ([526a8bf](https://github.com/vincentheimann/post-quality-manager/commit/526a8bf82eaa7894e71f4e87cfeeac49622f32f5))
* **n8n:** the n8n Instance section shows one state at a time ([08b7c06](https://github.com/vincentheimann/post-quality-manager/commit/08b7c06aa4ea805f19f53d76213683b657910c1d))
* **n8n:** the Notification URL gets each command's report (bug 97) ([37e9f86](https://github.com/vincentheimann/post-quality-manager/commit/37e9f8672402a1ce9f359283ceb6b902c780086d))
* **n8n:** Update workflow retries the 404 of n8n's publication window (bug 84) ([2f311d0](https://github.com/vincentheimann/post-quality-manager/commit/2f311d0a1e505207915eb8b3e847c154a712690b))
* **post-reviews:** keep a space after the check mark on done detail tabs ([9dc0f87](https://github.com/vincentheimann/post-quality-manager/commit/9dc0f87f48ec7c49aeb21db43cc4859639c55948))
* **setup:** the setup guide points a site without n8n to self-hosting ([e5b9727](https://github.com/vincentheimann/post-quality-manager/commit/e5b9727dc299593159de8f20aa76dd909d68a0d8))
* **setup:** the wizard ticks only what the plugin did; a hand-made site takes the manual path ([4bf25c8](https://github.com/vincentheimann/post-quality-manager/commit/4bf25c839670933000d48ec9d20b8c8f612cfde3))
* **upgrade:** the first plugin page after the stored-names upgrade opens for an administrator (bug 94) ([dcccc35](https://github.com/vincentheimann/post-quality-manager/commit/dcccc357e4d5be644d602efa3588fdb93359e266))

## [3.9.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.8.0...v3.9.0) (2026-10-15)


### Features

* **n8n:** roll back a failed provisioning (entry 6.5) ([234a28c](https://github.com/vincentheimann/post-quality-manager/commit/234a28c8ad19f1e91fe34e2714175dee3c536acc))
* **n8n:** the setup wizard connects and provisions n8n, or hands over to the manual path (entry 6.6) ([9ff4cbc](https://github.com/vincentheimann/post-quality-manager/commit/9ff4cbc05b54104f5819cd38cf2f2a8eaf243a15))


### Bug Fixes

* **release:** write the variable property as Freemius prints it (story 58) ([b7c1ee4](https://github.com/vincentheimann/post-quality-manager/commit/b7c1ee446f040aafa9973d34e3c069e59d03f74b))

## [3.8.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.7.0...v3.8.0) (2026-10-14)


### Features

* **n8n:** provision credentials and the workflow server-side (entry 6.4) ([32dbce5](https://github.com/vincentheimann/post-quality-manager/commit/32dbce53e392ef11419cc5698e808282f0b062fe))

## [3.7.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.6.0...v3.7.0) (2026-10-14)


### Features

* **workflow:** the Workflow File section guides a manual setup step by step (provisioning entry 2) ([a33a836](https://github.com/vincentheimann/post-quality-manager/commit/a33a8368fbac1e052f416e65a1466658703bf881))


### Bug Fixes

* **workflow:** n8n 2.42.3 labels and credential order in the Workflow File section ([f09492d](https://github.com/vincentheimann/post-quality-manager/commit/f09492d9903b86ded6acecae032e4d1d45e99627))

## [3.6.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.5.1...v3.6.0) (2026-10-13)


### Features

* **n8n:** connect an n8n instance by API key (provisioning entry 1) ([7195534](https://github.com/vincentheimann/post-quality-manager/commit/71955348a91d30e7205d15183f6c10f72497d4e7))
* **release:** deploy each release to Freemius and check its Free build (story 58) ([46fb4c3](https://github.com/vincentheimann/post-quality-manager/commit/46fb4c34f2fcde9d1ab9c1aa29f6631cc97cd0a3))
* **workflow:** give each site its own n8n webhook path (provisioning entry 3) ([a601c8e](https://github.com/vincentheimann/post-quality-manager/commit/a601c8e0b63f9341355cf7251571dae5d9a7e0e1))


### Bug Fixes

* **n8n:** probe shapes as n8n 2.42.3 answers them ([a79fc0b](https://github.com/vincentheimann/post-quality-manager/commit/a79fc0baeb23d03e3be0c62a8d4e79f7d852bf68))

## [3.5.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.5.0...v3.5.1) (2026-10-13)


### Bug Fixes

* **release:** ship a readme.txt so Freemius accepts the zip ([56cc766](https://github.com/vincentheimann/post-quality-manager/commit/56cc766ef6912f161a3460f7b72e014d1ac80b13))

## [3.5.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.4.0...v3.5.0) (2026-10-12)


### Features

* **admin:** add the plugin icon (story 51) ([56515c1](https://github.com/vincentheimann/post-quality-manager/commit/56515c1a75dff16329eeec31ac4c1ab53ac36bb3))
* **build:** the Free build contains no Pro code (story 54) ([5c4b1b9](https://github.com/vincentheimann/post-quality-manager/commit/5c4b1b999beb4076d149dee12c6c38c2e0c99692))
* **setup:** the setup guide continues into Blog Profile, Editorial Standards and schedule (story 55) ([99fdbdf](https://github.com/vincentheimann/post-quality-manager/commit/99fdbdf6400fe6e484f030702325c0971fe8d99e))

## [3.4.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.3.0...v3.4.0) (2026-10-09)


### Features

* **license:** a real Freemius license unlocks Pro (story 44) ([b3858fb](https://github.com/vincentheimann/post-quality-manager/commit/b3858fbd9051ec71691b0854a24484e873f1d418))

## [3.3.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.2.0...v3.3.0) (2026-10-09)


### Features

* **plugin:** point Plugin URI and Author URI at postquality.com (story 45) ([c5e93c6](https://github.com/vincentheimann/post-quality-manager/commit/c5e93c6ce35a9a3491421d66a40f234b5620017c))

## [3.2.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.1.1...v3.2.0) (2026-10-08)


### Features

* **roles:** set each role's plugin rights on a Settings Roles tab; retire View as ([357c931](https://github.com/vincentheimann/post-quality-manager/commit/357c931ddedbc1ba9394e1c0975c894fea564b60))

## [3.1.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.1.0...v3.1.1) (2026-10-08)


### Bug Fixes

* **release:** publish as a pre-release while the config says so ([f24067f](https://github.com/vincentheimann/post-quality-manager/commit/f24067fd98e657a09062cf614ff5a497b6a3d7db))

## [3.1.0](https://github.com/vincentheimann/post-quality-manager/compare/v3.0.1...v3.1.0) (2026-10-08)


### Features

* **i18n:** load translations from the plugin's languages folder ([15cbbb1](https://github.com/vincentheimann/post-quality-manager/commit/15cbbb183e37f1426d4747c3c870aa059f914f56))

## [3.0.1](https://github.com/vincentheimann/post-quality-manager/compare/v3.0.0...v3.0.1) (2026-10-08)


### Bug Fixes

* **workflow:** version status the user can trust without running a command ([ef31ce9](https://github.com/vincentheimann/post-quality-manager/commit/ef31ce93d6252aa03a0ccf62c856149d068b1873))

## 3.0.0 (2026-10-08)


### Features

* **release:** release PR and a CI-built plugin zip ([489de6c](https://github.com/vincentheimann/post-quality-manager/commit/489de6c06d9f21ad2e46e67aba8c663471af4a23))
