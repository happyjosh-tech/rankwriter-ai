=== RankWriter AI ===
Contributors: rankwriterai
Tags: ai, content generation, claude, seo, blog automation
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered article generation that learns from your existing blog and supports unlimited custom category profiles. Built on Anthropic's Claude API.

== Description ==

RankWriter AI is a Claude-powered WordPress content engine designed for sites that need to scale publishing without losing their voice.

**Two flagship features:**

1. **Custom Category Creation** — Define unlimited category profiles for any niche the default WordPress categories don't cover. Each profile captures niche description, target audience, target country, article tone, monetization goal, preferred article structure, banned topics/words, preferred keywords, custom prompt instructions, internal linking rules, and image style.

2. **Existing Blog Content Learning Engine** — Analyzes your live blog and produces a Blog Style Profile so every new article continues your existing patterns. Scans titles, categories, tags, tone, word count, heading structure, keywords, internal links, meta descriptions, image use, publishing cadence, top-performing posts (when analytics meta is available), monetization patterns, and detects duplicate topics, SEO gaps, and content expansion opportunities.

When generating new articles, RankWriter AI feeds both the Category Profile and the Blog Style Profile into Claude — so the output matches what's already working on your site while improving SEO and readability.

== Installation ==

1. Upload the `rankwriter-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Go to **RankWriter AI → Settings** and add your Claude API key.
4. Go to **RankWriter AI → Blog Analyzer** and run the analyzer.
5. Go to **RankWriter AI → Category Profiles** and create your first profile.
6. Go to **RankWriter AI → Generate Article** to produce your first draft.

== Frequently Asked Questions ==

= Where do I get a Claude API key? =
Sign up at https://console.anthropic.com/ — billing is per-token usage by Anthropic.

= Does it overwrite my existing posts? =
No. Every generated article is saved as a draft for you to review before publishing.

= Will it work on a brand-new blog with no content? =
Yes, but the Blog Style Profile won't have much to learn from. Run the analyzer again after you have 20+ posts.

== Changelog ==

= 1.0.0 =
* Initial release.
* Custom Category Profiles (CPT-backed, unlimited).
* Blog Content Learning Engine with weekly auto-analysis cron.
* Claude API integration with selectable model.
