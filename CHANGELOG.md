# Changelog

All notable changes to RankWriter AI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
