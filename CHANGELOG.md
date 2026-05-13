# Changelog

All notable changes to RankWriter AI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/happyjosh-tech/rankwriter-ai/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/happyjosh-tech/rankwriter-ai/releases/tag/v1.0.0
