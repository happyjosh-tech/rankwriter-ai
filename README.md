# RankWriter AI

> AI-powered WordPress content generator. Learns from your existing blog, writes fresh keyword-driven articles via the Claude API, and ships with autopilot scheduling, SEO plugin integration, schema, internal linking, and Adsense-compliance checks.

[![Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](#)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)

---

## Features

- **40 built-in category profiles** + unlimited custom niches (Agriculture Grants, Visa Sponsorship Jobs, Pet Care, anything you want)
- **Blog Content Learning Engine** — analyzes your existing posts (titles, tone, length, heading structure, keywords, internal links, meta descriptions, image style, publishing cadence, top performers, content gaps, audience intent, monetization patterns) and continues your style automatically
- **Live keyword research** from Google Suggest, Google Trends, competitor RSS feeds, optional SerpAPI + DataForSEO
- **Autopilot** with precise time-of-day scheduling (daily / twice-daily / weekly + day-of-week)
- **AI field auto-fill** — every free-text field has a Claude-powered "✨ AI fill" button
- **Anti-AI prose voice** + opt-in second-pass humanize call that rewrites sentence-by-sentence
- **SEO plugin integration** — Rank Math, Yoast, AIOSEO, SEOPress (auto-writes meta title, description, focus keyword, OG, schema type)
- **JSON-LD schema** — Article, HowTo, FAQPage, NewsArticle, Product, Review
- **Real internal linking** — generator gets actual post URLs and inserts working `<a href>` links
- **Featured image sourcing** — Pexels → Unsplash → Openverse free fallback
- **Legal pages generator** — About / Contact / Privacy Policy / Terms / Disclaimer / Affiliate / Cookie / DMCA, jurisdiction-aware
- **AdSense compliance check** — 8 prohibited-content policy signals + readability scan
- **GitHub-based auto-updater** — your WordPress installs get update prompts directly from this repo's GitHub releases

---

## Installation

### Option A — Install the latest release ZIP

1. Go to the **[Releases](../../releases/latest)** page.
2. Download `rankwriter-ai.zip`.
3. In WordPress admin → **Plugins → Add New → Upload Plugin** → upload the ZIP → Activate.
4. Open **RankWriter AI → Settings** and paste your Claude API key (get one at [console.anthropic.com](https://console.anthropic.com/)).

### Option B — Clone for development

```bash
cd wp-content/plugins
git clone https://github.com/happyjosh-tech/rankwriter-ai.git rankwriter-ai
```

Then activate from the WP admin Plugins screen.

---

## Configuration

After activation, walk the 7-step quick-start shown on the **RankWriter AI Dashboard**:

1. Add your Claude API key (and optional SerpAPI / DataForSEO / Pexels / Unsplash keys + competitor domains)
2. Run the **Blog Analyzer** to build your Style Profile
3. Pick a **Category Profile** (40 presets included) or create a new one
4. Run **Keyword Research** on a seed topic to see live keywords
5. Generate your first AI article (SEO meta written automatically into Rank Math / Yoast / AIOSEO / SEOPress)
6. Enable **Autopilot** for scheduled hands-off generation
7. Generate your AdSense-required legal pages (About / Contact / Privacy Policy)

---

## Auto-updates for end users

This plugin **self-updates from GitHub releases**. Once installed, your WordPress sites will check this repo's `releases/latest` endpoint daily. When a new tag is published, the standard WordPress "Update available" notice appears in the Plugins screen — one click to install.

End users **do not need a GitHub account**. The repository must be **public** for this to work without auth.

---

## Developer workflow

Every code change in VS Code → GitHub → end-user sites flows like this:

```
┌────────────────┐  git commit/push  ┌─────────────────┐
│  Local in VS   │ ─────────────────▶│  GitHub repo    │
│  Code (you)    │                   │  (this one)     │
└────────────────┘                   └────────┬────────┘
                                              │  git tag vX.Y.Z
                                              │  git push --tags
                                              ▼
                                     ┌─────────────────┐
                                     │ GitHub Actions  │
                                     │ build & release │
                                     └────────┬────────┘
                                              │ ZIP uploaded
                                              ▼
                                     ┌─────────────────┐
                                     │ GitHub Releases │
                                     └────────┬────────┘
                                              │ daily poll
                                              ▼
                                     ┌─────────────────┐
                                     │ User WP admin   │
                                     │ "Update now"    │
                                     └─────────────────┘
```

### Day-to-day editing

In VS Code:

1. Make your code changes.
2. Open the **Source Control** panel (Cmd+Shift+G).
3. Stage → commit → push. That's it.

The repo's `main` branch is the development line. Nothing ships to end users until you tag a release.

### Cutting a release

When `main` is stable and you want to push an update to all user sites:

```bash
# Bump version in rankwriter-ai.php (plugin header) and CHANGELOG.md
git add rankwriter-ai.php CHANGELOG.md
git commit -m "Release v1.0.1"
git tag v1.0.1
git push origin main --tags
```

GitHub Actions ([.github/workflows/release.yml](.github/workflows/release.yml)) automatically:

1. Builds a clean ZIP (excluding `.git`, `.github`, build artefacts).
2. Creates a GitHub Release for the tag.
3. Attaches the ZIP as `rankwriter-ai.zip`.
4. Auto-generates release notes from commit messages.

User WordPress sites pick up the new version within 12 hours (or immediately if the admin clicks **Dashboard → Updates → Check again**).

### Rolling back

```bash
# Roll back a bad release — re-tag a previous good commit
git tag -d v1.0.1                # delete locally
git push origin :refs/tags/v1.0.1 # delete on GitHub
# Then delete the GitHub release in the web UI.
```

User sites will see the previous release as latest on their next check.

---

## File structure

```
rankwriter-ai/
├── rankwriter-ai.php                                  ← main plugin entry
├── uninstall.php
├── readme.txt                                         ← WP plugin readme
├── README.md                                          ← GitHub readme (this file)
├── CHANGELOG.md
├── LICENSE
├── .github/
│   └── workflows/
│       ├── release.yml                                ← auto-build + release on tag
│       └── lint.yml                                   ← PHP syntax check on every push
├── admin/
│   ├── class-rankwriter-ai-admin.php
│   ├── css/admin.css
│   ├── js/admin.js
│   └── partials/                                      ← 8 admin page templates
└── includes/
    ├── class-rankwriter-ai.php
    ├── class-rankwriter-ai-{activator, deactivator, helpers}.php
    ├── class-rankwriter-ai-{claude-client, content-generator}.php
    ├── class-rankwriter-ai-{category-profiles, blog-analyzer, style-profile}.php
    ├── class-rankwriter-ai-{keyword-research, internal-linker, compliance}.php
    ├── class-rankwriter-ai-{schema-injector, image-sourcer, seo-integration}.php
    ├── class-rankwriter-ai-{autopilot, legal-pages, ai-suggester}.php
    └── class-rankwriter-ai-github-updater.php         ← self-update from this repo
```

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Anthropic Claude API key (paid, pay-as-you-go)
- Optional: SerpAPI, DataForSEO, Pexels, Unsplash keys for richer keyword data + featured images

---

## License

GPL v2 or later. See [LICENSE](LICENSE).

Built with [Claude](https://www.anthropic.com/claude).
