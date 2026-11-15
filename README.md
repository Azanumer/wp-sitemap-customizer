# Sitemap Customizer

Take control of WordPress core sitemaps — exclude post types, taxonomies and individual posts, add custom URLs, and set how many URLs each sitemap holds.

## Features

- **Exclude post types & taxonomies** — tick a box under **Settings → Sitemap** and that whole type drops out of the sitemap (handy for attachment pages, internal post types, or tag archives you don't want indexed).
- **Exclude individual posts** — comma-separated post/page IDs, for thank-you pages, landing pages and anything else that should never be in a sitemap.
- **Custom URLs** — one URL per line, optionally with ` | YYYY-MM-DD` for a lastmod date. They are served from their own sitemap (`wp-sitemap-custom-1.xml`) through a proper `WP_Sitemaps_Provider`.
- **Max URLs per sitemap** — override WordPress's 2000 default (up to 50000).
- **Filters** — `wsc_custom_urls` overrides the custom-URL list in code.

## Installation

1. Upload the `wp-sitemap-customizer` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins** in WordPress admin.
3. Configure under **Settings → Sitemap**; your sitemap index is at `/wp-sitemap.xml`.

## Notes

- This customizes the built-in WordPress sitemaps (no extra plugin or service needed). If you use an SEO plugin with its own sitemap, disable one of them — two competing sitemaps confuse search engines.
- Custom URLs are validated to http/https only, and a lastmod date must be a real `YYYY-MM-DD` date or it is ignored.

## Uninstall

Deleting the plugin removes the `wsc_settings` option.

## License

MIT — see [LICENSE](LICENSE).
