=== NSW AI Visibility ===
Contributors: novasiteweb
Tags: ai, llms.txt, robots.txt, geo, crawler
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control which AI crawlers can read your site, publish an llms.txt file and see which pages AI bots actually visit.

== Description ==

AI assistants such as ChatGPT, Claude and Perplexity send their own crawlers to your site. Some read your pages to cite them in answers, others collect content to train models. NSW AI Visibility shows you which ones come, what they read, and lets you allow or block each of them.

**AI crawlers in robots.txt**

* 16 known AI crawlers from OpenAI, Anthropic, Perplexity, Google, Apple, Meta, Mistral, DuckDuckGo, Amazon, ByteDance and Common Crawl.
* Each bot is labelled by purpose: AI search (cites your pages), user agent (fetches a page for a user) or model training.
* Block only the ones you choose, or block training bots in one click while keeping AI search bots.
* Live preview of the lines added to robots.txt.

**Auto-generated llms.txt**

* Served at /llms.txt, following the llmstxt.org proposal.
* Lists your pages and latest posts with a short summary, using your Rank Math or Yoast meta description when available.
* Pages set to noindex are left out.
* Cached and rebuilt whenever you publish. You can also write the file yourself.

**AI bot visit tracking**

* Headline figures, visits per day, visits per bot and most read pages over 7, 30 or 90 days.
* CSV export and a dashboard widget for the last 7 days.
* No IP address or visitor data is stored. Only known AI bots are counted.
* Adjustable retention period, with a daily clean-up.

**For developers**

* REST route: GET /wp-json/nsw-aiv/v1/stats?days=30 (administrators only).
* WP-CLI: wp ai-visibility stats, pages, llms, block, allow.
* Filters: nsw_aiv_bots, nsw_aiv_llms_lines, nsw_aiv_llms_include_post.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install it from the Plugins screen.
2. Activate NSW AI Visibility.
3. Go to Settings > AI Visibility.

== Frequently Asked Questions ==

= My robots.txt does not show the new rules =

If a physical robots.txt file exists at the root of your site, WordPress does not generate one and cannot add rules to it. The settings screen warns you when this happens. Add the rules to the file by hand or remove it.

= Does blocking AI bots hurt my Google rankings? =

No. Google-Extended only controls the use of your content for Google's AI models. It does not affect Googlebot or your position in Google Search.

= Is any personal data stored? =

No. The plugin only counts requests whose user agent matches a known AI crawler, by day and page. It stores no IP address and no data about human visitors.

== Changelog ==

= 1.1.0 =
* Settings screen split into tabs: Statistics, AI crawlers, llms.txt, Tracking.
* Statistics: period selector (7, 30, 90 days), headline figures, visits per day and per bot.
* CSV export of the visits, with formula injection protection.
* "Block training only" and "Allow all" shortcuts, live robots.txt preview.
* Preview of the current llms.txt in the admin.
* Dashboard widget with the last 7 days.
* New bots: DuckAssistBot, Amazonbot.
* French translation.
* The daily purge and table upgrades no longer run checks on front-end requests.
* Notice when plain permalinks prevent /llms.txt from working.

= 1.0.0 =
* First release: AI crawler rules in robots.txt, generated llms.txt, AI bot visit tracking, REST route and WP-CLI commands.
