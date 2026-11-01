# SEO Confident

[![CI](https://github.com/digivator/seo-confident/actions/workflows/ci.yml/badge.svg)](https://github.com/digivator/seo-confident/actions/workflows/ci.yml)
[![Licence: GPL v2 or later](https://img.shields.io/badge/licence-GPLv2%2B-blue.svg)](LICENSE)
![WordPress 5.8+](https://img.shields.io/badge/WordPress-5.8%2B-21759b.svg)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)

A lightweight WordPress SEO plugin. Meta tags, XML sitemaps, schema markup, robots.txt and llms.txt editors, redirects and breadcrumbs, all in one plugin.

## Features

- **Meta tags** with a custom title, description and keywords for every page, post and public post type
- **Canonical URLs** with automatic and per page control
- **Open Graph and Twitter Cards** for clean social sharing
- **Schema markup** for Organisation, Local Business, Professional Service and Person, plus Article, WebPage, BreadcrumbList and your own JSON-LD, site wide or per page
- **XML sitemaps** at `/sitemap.xml` for posts, pages, public custom post types and taxonomies
- **HTML sitemap** through the `[seoc_html_sitemap]` shortcode
- **Robots.txt editor** with a live preview and explicit rules for the main AI crawlers
- **llms.txt editor**, or one generated automatically from your content
- **Redirects** with 301, 302 and 307 support, plus loop and duplicate checks
- **Hreflang tags** and optional speakable markup
- **Technical SEO hardening** such as security headers, canonical enforcement and tel: link fixes
- **Settings import and export**

Everything runs on your own server. There is no tracking and no external service.

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later

## Installation

**From the WordPress.org directory.** Once the plugin is listed, go to **Plugins > Add New**, search for SEO Confident, then install and activate it.

**From a GitHub release.** Download `seo-confident-x.y.z.zip` from the [latest release](https://github.com/digivator/seo-confident/releases/latest), then go to **Plugins > Add New > Upload Plugin** and activate it.

**From source.** Clone the repository into `wp-content/plugins/seo-confident` and activate it. The plugin has no build step.

SEO Confident replaces other SEO plugins, so deactivate any you have before switching on this one.

## Quick start

1. Open **SEO Confident** in the WordPress admin menu.
2. Set the homepage title and description.
3. Choose your organisation type and add your business name, logo and contact details.
4. Edit any page or post to use the SEO Confident meta box.
5. Submit `/sitemap.xml` once in Google Search Console and Bing Webmaster Tools.

## Developer filters

| Filter | What it does |
| --- | --- |
| `seoc_excluded_page_slugs` | Slugs of pages to keep out of the sitemap and llms.txt |
| `seoc_exclude_post` | Exclude any post from the sitemap and llms.txt. Receives `$exclude`, `$post` |
| `seoc_sitemap_post_types` | Post types that get a sitemap |
| `seoc_sitemap_max_urls` | Maximum URLs per sitemap, 5000 by default. Receives `$max`, `$post_type` |
| `seoc_llms_txt_content` | The final llms.txt content |
| `seoc_page_schema` | The auto-generated Article or WebPage schema, before it is printed. Receives `$schema`, `$post` |
| `seoc_ai_bots` | The AI crawlers that get an Allow group in robots.txt |
| `seoc_output_hreflang` | Return `false` to switch hreflang tags off |
| `seoc_hreflang_code` | The language code used in hreflang tags, taken from the site locale by default |
| `seoc_speakable_selectors` | CSS selectors marked as speakable on the homepage. Empty by default |

Example, marking parts of the homepage as speakable:

```php
add_filter( 'seoc_speakable_selectors', function () {
    return array( '.entry-title', '.entry-summary' );
} );
```

## Development

You need PHP 7.4 or later and [Composer](https://getcomposer.org/).

```bash
composer install      # installs the coding standards tooling
composer phpcs        # checks the code against the project ruleset
composer phpcbf       # fixes what can be fixed automatically
bash bin/check-version.sh   # confirms the version matches everywhere
bash bin/build-zip.sh       # builds dist/seo-confident-x.y.z.zip from the last commit
```

The ruleset in `phpcs.xml.dist` is WordPress Extra with the formatting rules relaxed, because the code base uses four space indentation. Security, escaping, i18n, prefixing and PHP compatibility rules are all enforced. Pull requests run the same checks in CI, along with the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) used by the WordPress.org review team.

See [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request and [docs/RELEASING.md](docs/RELEASING.md) for how a release is cut.

## Security

Please report vulnerabilities privately. The details are in [SECURITY.md](SECURITY.md).

## Licence

SEO Confident is free software released under the GNU General Public Licence, version 2 or later. See [LICENSE](LICENSE).

## About

Made by [Digivator.co.uk](https://digivator.co.uk), a digital operations service based in Surrey.
