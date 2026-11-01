=== SEO Confident ===
Contributors: digivator
Tags: seo, schema, sitemap, meta-tags, llms-txt
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Meta tags, XML sitemaps, schema markup, robots.txt and llms.txt editors, redirects and breadcrumbs in one lightweight SEO plugin.

== Description ==

**SEO Confident** is a comprehensive yet lightweight WordPress SEO plugin that puts you in control of how your site appears in search.

### Core Features

* **Meta Tags** - Custom title, description, and keywords per page
* **XML Sitemaps** - Automatic sitemap index for posts, pages, public custom post types and taxonomies
* **Canonical URLs** - Automatic and custom canonical URL management
* **Open Graph** - Social sharing metadata optimisation
* **Twitter Cards** - Optimised Twitter-specific metadata
* **Schema Markup** - Organisation, LocalBusiness, ProfessionalService, Person, Article, WebPage, BreadcrumbList and custom JSON-LD
* **Robots Meta** - Per-page robots directives (noindex, nofollow, noarchive, etc.)
* **Robots.txt** - Editable robots.txt directives with a live preview
* **Breadcrumbs** - Schema-based breadcrumb markup
* **HTML Sitemap** - Human-readable sitemap with the `[seoc_html_sitemap]` shortcode
* **Redirects** - 301, 302 and 307 redirect management
* **llms.txt** - Editable llms.txt file for AI crawlers and assistants, or one generated automatically from your content
* **Technical SEO** - Security headers, preconnect hints, canonical enforcement, tel: link fixes

### Contact and Business Schema

Contact details are output for every schema type:
- **Email and telephone**
- **Postal address**
- **Social Profiles** - Multiple social media URLs (sameAs)
- **Geographic Areas** - Service region specification (areaServed)
- **Logo Dimensions** - Width and height for proper schema validation
- **Custom Schema** - Add any schema.org JSON-LD, site-wide or per page

### Why Choose SEO Confident?

* **Lightweight** - Minimal overhead
* **Complete** - All essential SEO features included
* **Secure** - Nonces, capability checks, escaping and validation
* **WordPress-Native** - Follows WP best practices
* **Private** - No tracking and no external requests from your server

== Installation ==

1. Upload the plugin to `/wp-content/plugins/seo-confident/` or upload the zip under **Plugins > Add New > Upload Plugin**
2. Activate the plugin via the Plugins menu
3. Open **SEO Confident** in the WordPress admin
4. Configure your settings and schema markup

== Quick Start ==

1. **Homepage Settings** - Set custom title, description, and keywords
2. **Organisation Type** - Choose Organisation, Local Business, Professional Service or Person
3. **Schema Setup** - Add your business name, logo and contact details
4. **Per-Page SEO** - Edit individual pages and posts using the SEO Confident meta box
5. **Custom Schema** - Optionally add any schema.org JSON in the Custom Wide Schema editor
6. **Sitemap** - Submit `/sitemap.xml` once in Google Search Console and Bing Webmaster Tools

== FAQ ==

= Can I use this with other SEO plugins? =
No, we recommend using only one SEO plugin. Deactivate other plugins before activating SEO Confident to avoid conflicts.

= Will my settings be preserved if I deactivate the plugin? =
Yes, settings are stored in the database and will be kept. They're only deleted if you uninstall the plugin.

= Where is my sitemap? =
At `/sitemap.xml`. The WordPress core sitemap at `/wp-sitemap.xml` is switched off while SEO Confident is active, and requests to it are redirected to `/sitemap.xml`.

= Why does my robots.txt or llms.txt not change when I save? =
If a real robots.txt or llms.txt file exists in your WordPress folder, the web server serves that file and WordPress never sees the request. The plugin screens warn you when this happens. Delete or rename the file.

= Does it ping search engines when I publish? =
No. Google and Bing both retired their sitemap ping endpoints. Submit your sitemap once in each webmaster tool and add it to robots.txt, which the plugin does for you.

= What's the performance impact? =
Minimal. SEO Confident only adds the output it needs to the front end.

= Can I export/import settings? =
Yes, export and import are available at the bottom of the main settings screen.

= Does it work with custom post types? =
Yes. Public custom post types get the SEO meta box and their own sitemap.

= What's the difference between Custom Schema and Custom Wide Schema? =
- **Custom Schema** (post editor): Per-page JSON-LD for individual pages
- **Custom Wide Schema** (settings): Site-wide JSON-LD applied globally

= Which developer filters are available? =
* `seoc_excluded_page_slugs` - slugs of pages to keep out of the sitemap and llms.txt
* `seoc_exclude_post` - exclude any post from the sitemap and llms.txt
* `seoc_sitemap_post_types` - post types that get a sitemap
* `seoc_sitemap_max_urls` - maximum URLs per sitemap (default 5000)
* `seoc_llms_txt_content` - final llms.txt content
* `seoc_page_schema` - the auto-generated Article or WebPage schema, before it is printed
* `seoc_ai_bots` - the AI crawlers that get an Allow group in robots.txt
* `seoc_hreflang_code` - the language code used in hreflang tags
* `seoc_output_hreflang` - return false to switch hreflang tags off
* `seoc_speakable_selectors` - CSS selectors marked as speakable on the homepage. Empty by default

= Does it integrate with external services? =
No. Everything is self-contained within your WordPress installation.

== Changelog ==

= 1.0.0 =
* Initial release
* Meta tags, canonical URLs, Open Graph and Twitter Cards
* Organisation, Local Business, Professional Service and Person schema, plus custom JSON-LD
* XML and HTML sitemaps
* Robots.txt and llms.txt editors, with explicit rules for the main AI crawlers
* Redirect management and breadcrumb schema
* Hreflang tags and optional speakable markup
* Technical SEO hardening and settings import and export

== Upgrade Notice ==

= 1.0.0 =
First public release.

== Support ==

For support, bug reports, and feature requests visit:
https://digivator.co.uk

== Credits ==

**Developer:** Digivator
**Website:** https://digivator.co.uk

== License ==

This plugin is licensed under the GNU General Public License v2 or later.

Copyright 2024-2026 Digivator

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

== Privacy Statement ==

SEO Confident respects user privacy:
- No user data collection
- No analytics or tracking
- Your server makes no external requests
- Settings remain on your server
- The optional "preconnect hints" setting is off by default. When you switch it on, your pages include hints that let visitors' browsers open early connections to Google Fonts and Google Analytics hosts. SEO Confident does not send any data to those hosts itself
