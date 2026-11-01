# Contributing to SEO Confident

Thanks for taking the time to help. Bug reports, ideas and pull requests are all welcome.

## Before you start

- Check the [open issues](https://github.com/digivator/seo-confident/issues) to see whether your report or idea is already there.
- For anything larger than a small fix, open an issue first so we can agree the approach before you spend time on it.
- Report security problems privately by following [SECURITY.md](SECURITY.md). Please do not open a public issue for them.

## What fits the project

SEO Confident aims to stay small and dependable. A change is more likely to be accepted if it:

- Solves a real problem for site owners
- Keeps the plugin free of tracking, advertising and locked features
- Makes no outside requests unless the site owner has switched on a feature that needs one, and documents it in `readme.txt`
- Follows the [WordPress.org plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), because the plugin is distributed through that directory

## Setting up

You need PHP 7.4 or later and [Composer](https://getcomposer.org/). Clone the repository, then run:

```bash
composer install
```

The plugin has no build step, so you can clone it straight into `wp-content/plugins/seo-confident` on a local WordPress site and work there.

## Checks to run

```bash
composer phpcs                 # coding standards, security and PHP 7.4 compatibility
bash bin/check-version.sh      # the version matches everywhere it is written
node --check assets/admin.js   # the admin script parses
```

CI runs these on every pull request, together with PHP syntax checks across supported versions and the official Plugin Check.

## Code style

- Prefix every function, class, hook name, option and constant with `seoc_`, `SEOC_` or `SEO_Confident`.
- Use four spaces for indentation in PHP and tabs in JavaScript. The `.editorconfig` file sets this for most editors.
- Escape on output, sanitise on input, check capabilities and verify nonces on every admin action.
- Wrap user facing text in the `seo-confident` text domain.
- Load scripts and styles with `wp_enqueue_script()` and `wp_enqueue_style()`. Please do not print inline `<script>` or `<style>` blocks.
- Keep code readable. No obfuscation or minified source.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep the change focused. Several small pull requests are easier to review than one large one.
3. Add an entry under **Unreleased** in `CHANGELOG.md`.
4. Make sure the checks above pass.
5. Fill in the pull request template and describe how you tested the change, including your WordPress and PHP versions.

By contributing you agree that your work is released under the same licence as the project, GPL version 2 or later.

## Conduct

Everyone taking part is expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md).
