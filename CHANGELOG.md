# Changelog

All notable changes to RankWriter AI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.1] - 2026-05-18

### Added — Safe Mode kill switch (bisection tool)

After three rounds of "fix the 504 / still 504s" we needed a definitive way to answer the question "is the slow admin page coming from RankWriter or from something else on the site?" — without me shipping more guesses. Now you can:

Add this line to `wp-config.php` (above `/* That's all, stop editing! */`):

```php
define( 'RWAI_SAFE_MODE', true );
```

Reload the failing admin page. In safe mode, RankWriter registers ONLY:
- The admin menu (so pages still render)
- The Category Profile custom post type (so taxonomy queries don't break)

EVERYTHING else — autopilot ticks, generation queue, schedule recovery, ads injection, speed optimizer, cluster manager, PSE queue, fact checker, SEO healer, browser cron, etc. — is skipped at boot.

If the page now loads → the bug is in one of our background hooks; we bisect from there. If it still 504s → the cause is outside this plugin (theme, another plugin, or host config). Remove the constant from `wp-config.php` to restore normal operation.

A yellow admin notice on every page confirms safe mode is active, so you don't forget you turned it on.

## [1.3.0] - 2026-05-18

### Added — Ads module (Ad Inserter clone, no extra plugin needed)

Full-featured ad management built into RankWriter so users don't need to install and maintain a separate ad-management plugin. Inspired by Ad Inserter by Igor Funa.

- **16 ad blocks**, each with its own code editor (HTML / JS / AdSense ins-tags), name, and enable toggle.
- **Insertion modes per block**: After paragraph N (CSV of paragraph numbers like `3,6,9,12`), Before content, After content, Before/After excerpt, Between posts on archive pages (every Nth post), Manual via shortcode `[rwai_ad block=N]`.
- **Display conditions**: Posts / Pages / Homepage / Category pages / Tag pages / Search / Archive — per block. Plus CSV-driven include/exclude lists for category IDs, tag IDs, and specific post IDs.
- **Device targeting**: Show on Desktop / Tablet / Mobile checkboxes. User-agent detection: iPad / Android tablets → tablet; iPhone / Android phones → mobile; everything else → desktop.
- **Scheduling**: start/end date-time range, time-of-day window (with midnight-wrap support for overnight ads like 22:00 → 06:00), and day-of-week checkboxes.
- **Alignment**: Default / Left (float) / Right (float) / Center, applied via a single wrapper class.
- **AdSense Auto Ads**: one toggle + paste your `ca-pub-` ID; the official Google Auto Ads script is emitted in `<head>`.
- **ads.txt manager**: paste your `ads.txt` content in Settings; the plugin serves it at `your-site.com/ads.txt` (required for AdSense / programmatic verification).
- **Custom HTML in &lt;head&gt;**: useful for Google Search Console / Bing Webmaster / AdSense site-verification meta tags without editing the theme.
- **Per-post override**: meta box on every Post / Page editor with a "Disable ALL ads on this post" checkbox and a comma-separated "disable specific blocks" field.
- **Lazy-loaded inline CSS**: ad alignment styles are emitted in the footer only when at least one ad is being rendered. Zero CSS request when no ads on the page.

Limitations vs. full Ad Inserter (we didn't try to clone the entire 6-year project in one pass): no viewport-based rules, no A/B testing, no AMP-specific blocks, no CSS-sticky ads, no manual position-by-CSS-selector, no GDPR consent integration. These can be added in follow-up versions if needed.

### Architecture

- `RankWriter_AI_Ads_DB`: storage + sanitization (single `rwai_ads_blocks` option for all 16 blocks, single `rwai_ads_settings` option for globals).
- `RankWriter_AI_Ads_Inserter`: frontend engine. Hooks `the_content` / `the_excerpt` / `wp_head` / `init` (for ads.txt) / `add_meta_boxes` / `save_post`. Splits content by `</p>` boundaries for paragraph-precise insertion. Resets a per-loop counter via `loop_start` / `loop_end` for the between-posts mode.
- Admin UI at `RankWriter AI → Ads`: tab-based navigator across all 16 blocks, single-form save covering all blocks + global settings in one POST.

## [1.2.7] - 2026-05-18

### Fixed — Autopilot page 504 (cause #2)

Removed `spawn_cron()` in 1.2.6 wasn't the whole story. The same `Schedule_Recovery` sweep also calls `wp_publish_post()` on every missed scheduled post — which fires every other plugin's `transition_post_status` / `publish_post` / `save_post` hooks (Yoast/Rank Math sitemap rebuild, Jetpack social share, image regen, broken-link checker, etc.). On a site with several stuck posts AND heavy SEO plugins, that's seconds of synchronous work per post happening on every admin page load.

### Changed

- **Auto-sweep on `init` / `wp_loaded` is OFF by default.** The schedule-recovery sweep no longer runs on every admin page load. It still runs when the user clicks the "Publish missed scheduled posts now" button on the Autopilot page. Power users who want the old behavior can re-enable with `define( 'RWAI_AUTO_RECOVERY', true );` in `wp-config.php`.
- **Manual sweep caps at 5 posts per click.** Each `wp_publish_post()` fires expensive cross-plugin hooks; publishing 50 in one request was hitting nginx's request timeout. Click the button again to publish the next batch.

## [1.2.6] - 2026-05-17

### Fixed — 504 Gateway Time-out on Autopilot save + Run-now

The recovery sweep added in 1.2.2 called WordPress's `spawn_cron()` on every admin page load to kick stalled cron events server-side. On most hosts that returns in <50ms because of `blocking => false`. But on hosts where the firewall/CDN/load balancer blocks WordPress from connecting back to its own public URL (Cloudflare-fronted sites, some shared hosts, sites behind restrictive VPCs), the underlying TCP `connect()` hangs at the OS layer for the full PHP-FPM timeout — even though WordPress asked for non-blocking — which produced nginx 504s on the Autopilot page (and any other page that triggered the sweep).

### Changed — Browser-side WP-Cron trigger replaces server-side spawn_cron

- **New `RankWriter_AI_Browser_Cron`.** Injects a tiny `<script>` into the admin footer that fires `wp-cron.php` from the user's tab via XHR. The browser → server connection always works (it's the same path the user used to load the admin page in the first place), so wp-cron.php runs reliably regardless of the host's server-to-server loopback health.
- **All `spawn_cron()` calls removed from the plugin.** Recovery sweep, generation enqueue, autopilot run-now, manual job reset, stale-job recovery — none of them call it anymore. The browser-side trigger picks up the work on the next admin page load (typically under a second after the redirect).
- **Trigger only fires when there's pending work.** The admin_footer snippet first checks: any overdue RWAI cron hook? any post stuck at `future` past its date? any queued / stale-running generation job? If none, no XHR is emitted — saves a request per admin page when the system is idle.
- **Trade-off surfaced honestly.** Browser-side cron fires only when an admin user has a wp-admin tab open. For genuine 3-AM autopilot runs with no one logged in, you still need natural visitor traffic hitting any frontend page OR a real system cron pointing at `wp-cron.php` every minute. We'd already documented the latter in the Autopilot diagnostics panel.

### Notes for users still missing featured images

Featured image sourcing uses the Pexels API or Unsplash Access Key — both require a free API key. With neither configured, the sourcer silently skips (rather than failing the whole generation). Add a Pexels key at https://www.pexels.com/api/ → paste into RankWriter AI → Settings → Image API keys to enable featured images.

## [1.2.5] - 2026-05-16

### Fixed — generation jobs no longer stuck at "running" forever

The 1.2.3 queue treated `running` as a permanent skip, so when a worker process actually died mid-generation (PHP-FPM killing the worker at 60s, host killing the loopback HTTP request, or any other crash) the job stayed at "running" indefinitely. Every retry attempt saw "running" and refused to re-run it. Compounding bug: the generation pipeline made **three** Claude API calls in sequence (intent detection → main article → humanizer) plus image sourcing + fact-checker + risk scan, easily 200-300s end-to-end — too long for the PHP-FPM windows most managed shared hosts allow.

### Changed

- **Stale "running" jobs now auto-retry.** `Generation_Queue::run_job()` distinguishes terminal states (`done` / `failed` — never re-run) from `running` jobs whose `started_at_ts` is older than 10 minutes (presumed-dead worker — re-run). New `attempts` counter caps retries at 3 so a genuinely broken job doesn't loop forever; after 3 attempts the job is marked failed with a clear "your host's PHP timeout is too short" message and a pointer to disable Humanize.
- **Stale-job recovery sweep.** `Schedule_Recovery` now also scans the generation queue every minute and resets any job stuck at `running` past the stale window. Result: the 24-hour-stuck job you're staring at right now will auto-recover on the next admin page load after this update.
- **Per-step progress visibility.** `Content_Generator::generate()` fires `rwai_generation_step` at every major stage (keyword research, intent detection, main Claude call, humanizer pass, post insert, image sourcing, fact-checker, risk scan). The queue listens and records the last step on each job, so the recent-generations panel now shows exactly WHERE a job is right now — and exactly where the previous worker died if it crashed.
- **"Reset & retry" button on every failed or stale-running job.** Lets the user unstick a job manually without waiting for the 10-minute auto-recovery window.
- **Humanize pass defaults to OFF on new installs.** The humanizer is a second Claude call adding 60-120s to the critical path — on hosts with strict PHP-FPM timeouts (most managed shared hosts) it's the single step most likely to get killed mid-pipeline. The voice rules baked into the main generation prompt already strip most AI tells; opt back in via Settings → Humanize pass only if your host has 120s+ timeouts.

### Why this means manual generation + autopilot finally work on cheap hosts

The critical path is now: keyword research (10-20s) → intent detection (5-15s) → main Claude call (60-90s) → post insert (fast). Image sourcing + fact-checker + risk scan run after the post is already saved as a draft, so even if PHP-FPM kills them, the post exists. Total before-save: 75-125s, fits in most hosts' PHP windows.

## [1.2.4] - 2026-05-14

### Added — Autopilot diagnostics + manual "Run now"

- **Autopilot status panel.** New diagnostics card at the top of the Autopilot screen shows: whether autopilot is enabled, whether a cron event is actually registered, next-run time in both site time and UTC, current server time in both, the WP timezone string (with a warning if it's a raw `+00:00` offset rather than a named timezone like `Africa/Lagos`), whether `DISABLE_WP_CRON` is set in `wp-config.php`, and the current queue length. Most "autopilot isn't running" reports trace back to one of these four conditions — making them visible means the user can self-diagnose in 5 seconds instead of digging through code.
- **Timezone offset warning.** If your WP timezone is set to a raw UTC offset (e.g. `+00:00`) rather than a named city, the panel surfaces the exact mismatch: "Your site timezone is +00:00 — if you live in Nigeria (UTC+1) and type 16:14, autopilot will fire at 17:14 your local time." Direct link to Settings → General with instructions to switch to `Africa/Lagos`.
- **DISABLE_WP_CRON detection.** If the constant is true in `wp-config.php`, the panel warns that scheduled events won't fire on visitor traffic and gives the exact `wp-cron.php` URL to point a system cron at.
- **"Run autopilot now" button.** Schedules a one-shot tick via the dedicated `rwai_autopilot_run_now` hook (separate hook so it doesn't collide with the recurring event's 10-minute dedup window) and fires `spawn_cron()` to kick a non-blocking background worker. Lets you confirm autopilot actually produces an article without enabling the schedule first or waiting for the scheduled time. Uses the same async pattern as v1.2.3's manual generation so it doesn't 504.
- **Recovery sweep covers the new hook.** The 1.2.2 schedule-recovery sweep now also kicks stalled `rwai_autopilot_run_now` events.

### Changed

- **`Autopilot::tick()` accepts an optional `$force` parameter.** Lets the run-now flow proceed without flipping the saved enabled flag on/off (which would have raced with concurrent save_autopilot writes).

## [1.2.3] - 2026-05-14

### Fixed

- **"504 Gateway Time-out" on manual article generation.** The Generate Article page ran the entire pipeline (keyword research + intent detection + main Claude call + humanizer + image sourcing + fact-checker + risk scan) synchronously inside the admin POST request — easily 90-180 seconds end-to-end. On most managed hosts nginx `proxy_read_timeout` is ~60s and PHP-FPM `request_terminate_timeout` is ~30-60s, so the browser saw nginx's 504 page even though Claude was still running on the backend. Reproduced on every new domain because every shared host / cheap VPS uses similar defaults.
- **Now async via WP-Cron loopback.** Added `RankWriter_AI_Generation_Queue`. Submitting the form enqueues a job, fires `spawn_cron()` to trigger a non-blocking loopback request to `wp-cron.php`, and redirects the browser to a "Generating in background…" status page that auto-refreshes every 5 seconds and redirects to the post editor as soon as the job completes (typically 1-3 minutes). The background request can run as long as it needs to — your browser is no longer behind nginx's timeout.
- **Recent generations panel.** The Generate Article page now shows the last 8 jobs (queued / running / done / failed) so you can see what's in flight and jump straight to any completed post. Failed jobs show the exact Claude / API error message.
- **Stalled generation jobs auto-recover.** The schedule-recovery sweep added in 1.2.2 now also kicks `rwai_generate_async` cron events whose next-run is in the past, so a job stuck because WP-Cron itself stalled gets re-fired on the next admin page load instead of sitting forever.

## [1.2.2] - 2026-05-14

### Fixed

- **Scheduled posts never published (the "Missed schedule" symptom).** WordPress relies on WP-Cron, which only fires when a visitor hits the site. On low-traffic sites, a post scheduled for e.g. 16:14 could sit at `post_status="future"` long past its publish time. Added a `RankWriter_AI_Schedule_Recovery` sweep that runs on `init` + `wp_loaded` (throttled to once per minute) and publishes every post stuck in `future` with `post_date_gmt <= now` via `wp_publish_post()` so transition hooks fire properly. The same sweep also detects any RankWriter cron hook (Autopilot, PSE queue, Pinterest scheduler, SEO Healer, Refresher, Gap Detector, Seasonal, Blog Analysis) whose next-run is in the past and calls `spawn_cron()` so the missed tick fires on the current request instead of waiting for the next traffic event.
- **One-click recovery from the Autopilot screen.** New "Publish missed scheduled posts now" button under the Autopilot page lets an admin force the sweep on demand and shows how many posts were published + how many cron hooks were kicked.

### Changed — Human title generation

- **Auto-fill topic suggester (Generate article ✨ AI fill) no longer defaults to the AI-listicle template.** Previously it returned generic "Top 15 X for Y in 2026 (With Z)" titles — the most over-used AI pattern. Rewrote the system prompt with a shared `RankWriter_AI_AI_Suggester::human_title_rules()` block that bans the giveaway templates ("Top N {plural} for {audience} in {year} (With {modifier})", "Ultimate Guide to", trailing parentheticals, year tacked on as marketing tag, round 5/10/15/20 numbers), enforces opener variety, and shows good/bad calibration examples.
- **Title Lab variant generator now shares the same human rules.** All five styles (SEO / viral / Discover / Pinterest / social) inherit the anti-template guardrails, and the prompt now explicitly demands opener variety across the three variants in a single style instead of producing three minor rewrites of the same template.

## [1.2.1] - 2026-05-13

### Fixed

- **Clear cache / Optimize images buttons returned a blank page.** Both were generated as `<a href="admin-post.php?rwai_action=...">` GET links, but the dispatcher only listens for `$_POST['rwai_action']` and `admin-post.php` exits silently when its required `action=` query param is missing. They also sat inside the settings form, which is invalid HTML (nested forms). Converted both to hidden POST mini-forms placed outside the settings form, with the buttons referencing them via the HTML5 `form=` attribute so they can sit anywhere in the page.

### Added — Aggressive-mode score movers

Five common Lighthouse / PSI complaints on WordPress sites that v1.2.0's Aggressive mode wasn't fixing yet. All on by default in Aggressive mode, mostly on in Balanced mode, off in Safe mode.

- **HTML minification** — light-weight output minifier that strips comments and collapses whitespace between tags. Stashes `<pre>`, `<script>`, `<style>`, `<textarea>`, and IE conditional comments first so it never corrupts code or whitespace-significant content.
- **DNS prefetch + preconnect** — emits `<link rel="dns-prefetch">` + `<link rel="preconnect">` for Google Fonts, GA, GTM, AdSense, and DoubleClick by default. User can override the host list.
- **Google Fonts `display=swap` rewrite** — appends `&display=swap` to every `fonts.googleapis.com` stylesheet so text paints with the fallback font immediately (fixes Lighthouse's "Reduce text-rendering delay" almost every time).
- **Disable WordPress emoji loader** — drops the inline emoji detection script + the s.w.org DNS lookup the emoji loader injects. Saves ~6 KB JS + an extra DNS round-trip on every page.
- **Remove `jquery-migrate` dependency** — dequeues the migrate shim on the front-end (only safe with modern themes/plugins — toggle off if anything breaks).
- **Disable WordPress oEmbed** — removes `wp-embed.js` + the oEmbed discovery `<link>` tags for sites that don't embed external posts.

Each toggle is independently controllable from a new **Page polish (score-movers)** card on the Speed Optimizer screen, so users can keep the ones that fit their theme and skip the rest.

[1.2.1]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.2.1

## [1.2.0] - 2026-05-13

### Added — RankWriter Site Speed Optimizer

A new admin screen (**RankWriter → Speed Optimizer**) with a one-click safe speed optimization pipeline built around a Safe / Balanced / Aggressive mode dial. Every action is reversible from a snapshot taken on first activation, so there's no path where the optimizer locks you out of recovery.

**Static HTML page cache** with auto-purge on `save_post`, `wp_update_nav_menu`, `customize_save_after`, theme switch, and plugin install/activate. Bypasses logged-in users, REST, AJAX, cron, POST requests, WooCommerce cart / checkout / my-account, AMP, query-string URLs, and user-configured exclusions. Cache lives in `wp-content/cache/rwai-speed/` (survives plugin updates).

**Browser caching** via `Cache-Control` + `Vary` headers from PHP, plus a copy-pasteable `.htaccess` snippet for 1-year cache on CSS / JS / images / fonts and Gzip. We do not auto-write `.htaccess` — that file is too fragile per-host to modify without explicit human review.

**CSS optimization** — local stylesheet minify, optional defer-non-critical via the `media=print` swap pattern, and a critical-CSS textarea the admin can paste into (no auto-extraction promises).

**JS optimization** — local script minify, `defer` on non-essential scripts, and delay-until-interaction for known analytics / social / chat scripts (Balanced) or all non-essentials (Aggressive). Protected list: jQuery, wp-i18n, AdSense (`adsbygoogle.js`, `pagead2`), GTM / GA, Stripe, PayPal, reCAPTCHA, WooCommerce checkout / cart, login.

**Image optimization** — lazy-load via `loading="lazy"` + `decoding="async"` while skipping the first image (LCP), injection of missing `width` / `height` to reduce CLS, automatic WebP swap when a same-name `.webp` exists, and a bulk WebP generator that creates `.webp` alongside originals (never destructive).

**Core Web Vitals nudges** — `fetchpriority="high"` on the first content image, `<link rel="preload" as="image">` for the featured image on single-post pages, and `<link rel="preload" as="font">` for a user-supplied font list.

**Database cleanup** — opt-in, one-click cleanup of post revisions (configurable keep-N), auto-drafts, trashed posts, spam comments, expired transients, and orphan post-meta. Never touches users, orders, settings, options, plugin tables, or live content. Pre-flight counter shows exactly how many rows each category would delete before the user confirms.

**PageSpeed Insights integration (optional)** — drop in a Google PSI API key and the screen fetches real mobile / desktop scores plus LCP, CLS, TBT, FCP. Without a key the screen still shows internal optimization status; we do not fabricate scores.

**Activity log** — last 30 actions surfaced inline so users can audit what the optimizer did.

**Rollback & disable** — *Restore previous settings* re-saves the pre-optimization snapshot and wipes the cache; *Disable Speed Optimizer* turns the module off without losing your configuration.

### Files

```
includes/speed-optimizer/
├── class-rwai-speed-logger.php
├── class-rwai-cache-manager.php
├── class-rwai-browser-cache.php
├── class-rwai-css-optimizer.php
├── class-rwai-js-optimizer.php
├── class-rwai-image-optimizer.php
├── class-rwai-database-cleaner.php
├── class-rwai-core-web-vitals.php
└── class-rwai-speed-optimizer.php
admin/partials/speed-optimizer.php
admin/css/speed-optimizer.css
```

[1.2.0]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.2.0

## [1.1.2] - 2026-05-13

### Added

Inline manual-fix actions now exist for **every** SEO Healer rule, not just broken links. Each open issue row exposes a one-form repair so you never have to leave the dashboard:

- **Missing / duplicate meta description** — inline textarea, type your own meta description and click Save.
- **Missing alt text** — per-image inputs listing every flagged `<img>` source, type alt text for each and click Save.
- **Duplicate title** — inline title field, edit and click Save title.
- **Outdated SEO settings** — title + meta description inputs side-by-side, save either or both at once.
- **Orphan post** — dropdown of related candidate source posts; pick one and click "Add inbound link" to inject a "Related reading" reference into that source, clearing the orphan flag.
- **Thin content** — "Expand with Claude" button with target word-count input.
- **Weak headings** — "Restructure headings with Claude" button that adds proper H2/H3 spacing while preserving every existing sentence.

Each manual fix is logged to the repair log with a before/after snapshot, so the existing Rollback flow works for these too.

### Changed

- Rollback support extended to cover the new manual rules (duplicate title, thin content, weak headings).
- Orphan-post repairs cannot be auto-rolled-back (they edit a *different* post) — the repair log shows a clear message explaining how to undo manually.

[1.1.2]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.1.2

## [1.1.1] - 2026-05-13

### Fixed

- **SEO Healer — broken-link notification persisting after fix.** Deleting a broken link (or fixing it manually in the WordPress editor) no longer leaves the broken-link notification stuck on screen until the next cron tick. The healer now hooks `save_post`, so any post edit immediately re-scans that post and clears resolved issues right away.
- **Stale issue detection.** When the user clicks "Delete link" on a link that has already been removed from the post (e.g., edited manually in Gutenberg between scans), the healer now detects the stale state and clears the issue with a clear "no longer in the post — issue cleared" message instead of returning an unhelpful "no matching link" error.
- **Defensive cache flush.** `clean_post_cache()` is now called after every link replace / delete so the immediate re-scan sees the freshly saved content rather than an in-memory copy.

### Added

- **Dismiss button on every open SEO issue.** Safety valve for cases where the detector got something wrong, or the user has already fixed the underlying problem through another route. Clears the notification without touching the post.
- **Trash / delete hook.** When a post is trashed or permanently deleted, its open SEO issues are removed automatically so the dashboard doesn't keep counting ghosts.

[1.1.1]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.1.1

## [1.1.0] - 2026-05-13

### Added

- **Topic Clusters** — manager, suggester, analyzer, dedicated clusters DB, admin partials for cluster list / edit. Plan pillar + supporting articles and let the generator know which cluster a piece belongs to.
- **Programmatic SEO (PSE)** — template engine, manager, queue, presets, dedicated DB. Generate hundreds of templated landing pages from a CSV-style data source.
- **Pinterest Engine** — DB, scheduler, image generator, pin partials. Auto-create and schedule pins from new posts.
- **Content Refresher** — DB + engine that re-audits older posts and rewrites stale sections via Claude.
- **Fact Checker** — verifies factual claims in generated drafts before publish.
- **SEO Healer** — scans existing posts for SEO issues (thin content, missing schema, broken links, etc.) and patches them.
- **Schema Engine** — expanded JSON-LD support beyond the original injector, with a dedicated schema dashboard.
- **Discover Optimizer** — title / image / opening hook tuning for Google Discover surface placement.
- **Title Intelligence Lab** — A/B title generator with CTR-prediction scoring.
- **Gap Detector** — finds topical gaps versus competitor sites and queues fill-in articles.
- **Seasonal Engine** — seasonal calendar that surfaces upcoming events (holidays, sports, product launches) for timely content.
- **Parasite SEO Engine** — distribute syndicated articles across third-party platforms.
- **Risk Detector + Dashboard** — surfaces compliance / policy / AdSense risk per post.
- **Humanization Lab** — UI on top of the second-pass humanizer for batch rewriting.
- **Translator** — multi-language article translation pipeline with language detection.
- **Voice Memory** — saves the blog's distilled voice across runs so the generator stays consistent.
- **CPC Scorer** — flags low-monetization keywords before generation.
- **Intent Detector** — classifies queries as informational / transactional / navigational and adapts the prompt.

### Changed

- `class-rankwriter-ai-admin.php` grew substantially to host the new admin screens, AJAX handlers, and menu entries for every feature above.
- Content generator now consults the cluster + intent + CPC subsystems when building its prompt context.
- Internal linker now scores candidates against cluster relationships in addition to keyword matches.
- Schema injector hands off complex schema types to the new Schema Engine.
- Keyword research module reuses the intent detector for downstream filtering.

[Unreleased]: https://github.com/happyjosh-tech/rankwriter-ai/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.1.0

## [1.0.0] - 2026-05-12

### Added

- **Custom Category Profiles** — unlimited niche profiles with 12 configurable fields (niche description, target audience, target country, article tone, monetization goal, preferred article structure, banned terms, preferred keywords, custom prompt instructions, internal linking rules, image style, WP-category mapping).
- **40 preset category profiles** seeded on first activation, organized into Finance, Education, Health, Tech, Travel, and Entertainment groups. Each preset comes with smart per-niche defaults.
- **Blog Content Learning Engine** — analyzes existing posts for titles, categories, tags, tone, length, heading structure, keywords, internal links, meta descriptions, image style, publishing cadence, top-performing posts, common topics, content gaps, audience intent, and monetization patterns.
- **Blog Style Profile** — persisted distillation of the analyzer output (preferred tone, headline style, word count, formatting, dominant categories, internal linking opportunities, SEO gaps, expansion opportunities, duplicate topic warnings, structural patterns, audience intent).
- **Deep analyze with Claude** — optional second pass on the blog analyzer that sends 8 sample posts to Claude for a prose voice / tone / weakness brief, injected into every future generation.
- **Live Keyword Research** — fresh signals from Google Suggest, Google Trends RSS, competitor RSS feeds, plus optional SerpAPI and DataForSEO.
- **Autopilot** — scheduled hands-off article generation with time-of-day + day-of-week precision, max-articles-per-run, draft/pending/publish status, max-tags cap, and per-run WP-category override.
- **AI field auto-fill** — every free-text field in Category Profile, Generate Article, and Autopilot forms has a Claude-powered "✨ AI fill" button.
- **WordPress category placement picker** — Category Profile / Generate Article / Autopilot all support mapping posts to an existing WP category instead of auto-creating one.
- **Real internal linking** — generator gets a candidate list of real existing posts (top performers + same category + keyword-matched + recent) with URLs, and runs an auto-link pass that converts bare title mentions into `<a href>` tags.
- **Compliance validator** — banned-terms enforcement, AdSense policy-signal scanning (8 prohibited-content categories), readability heuristics (thin content, headings, links, paragraph length, AI tell detection). Report shown as a meta box on the post edit screen.
- **JSON-LD schema injection** — Article / HowTo / FAQPage / NewsArticle / Product / Review. Auto-extracts FAQ pairs and HowTo steps from generated content.
- **Featured image sourcing** — Pexels → Unsplash → Openverse fallback, biased by the Category Profile's image_style.
- **SEO plugin integration** — auto-writes meta title, description, focus keyword, OG fields, and schema type into Rank Math, Yoast SEO, AIOSEO, or SEOPress.
- **Legal Pages generator** — one-click About Us, Contact Us, Privacy Policy, Terms of Service, Disclaimer, Affiliate Disclosure, Cookie Policy, DMCA. Jurisdiction-aware, niche-aware via the Blog Style Profile.
- **Anti-AI voice rules** — every generation prompt contains explicit hard rules against AI tell phrases (in today's, furthermore, plethora, robust, delve into, etc.) and explicit DO rules for human writing (opinion, specificity, varied rhythm, contractions, concrete openings).
- **Second-pass "Humanize"** — optional second Claude call that rewrites every sentence to scrub AI tells while preserving facts, numbers, HTML tags, and internal link URLs.
- **GitHub-based auto-updater** — checks the GitHub Releases API on a schedule, displays standard WordPress update prompts, downloads and installs the latest release ZIP on click.

### Notes

- Default `max_tokens` is 8,000; cap is 64,000 (Claude Opus 4.7's model ceiling).
- Default Claude model: `claude-opus-4-7`.
- All API keys (Claude, SerpAPI, DataForSEO, Pexels, Unsplash) are optional except Claude.

[1.0.0]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.0.0
