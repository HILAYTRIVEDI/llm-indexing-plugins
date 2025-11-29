=== Simple LLMs.txt Manager ===
Contributors: mannadigital
Tags: llms, llms.txt, robots, ai, text
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple LLMs.txt manager for your WordPress site.

== Description ==
- Serves a site-wide `/llms.txt` endpoint when Enabled; when Disabled, requests redirect to your homepage.
- Stores content in its own table (`wp_md_llms_txt`) as LONGTEXT to support very large payloads.
- Uses persistent object cache where available; includes a Clear & Re-prime cache action.
- Provides an admin-only Settings page with Save, Clear Content, cache controls, and the Enabled toggle (nonce + capability checks).
- Automatically upgrades the schema so the Enabled column exists on legacy installs.
- Follows VIP-safe patterns: prepared SQL, escaping, no remote requests.

== Installation ==
1. Upload the ZIP via Plugins → Add New → Upload Plugin.
2. Activate.
3. Go to Settings → LLMs.txt, toggle Enabled, edit content, Save.
4. Visit `https://example.com/llms.txt` (if Enabled) to confirm the response.

== Usage ==
1. Navigate to **Settings → LLMs.txt** in the WordPress admin.
2. Enter the desired content for `/llms.txt`. You can use the available merge fields displayed on the settings page to inject dynamic values.
3. (Optional) Provide a Master Statement to output in HTML comments on public pages for LLM crawlers.
4. Check **Enabled** and click **Save** to publish the text file. The plugin automatically strips any `START`/`END` helper markers from the public output.
5. Toggle **Clear Content** to empty the stored payload, or use **Clear & Re-prime Cache** after large updates to refresh cached copies.
6. If you need to hide the endpoint temporarily, uncheck **Enabled**. Requests to `/llms.txt` will redirect visitors to your homepage until you re-enable it.

== Changelog ==
= 1.0.3 =
* Improved code quality and WordPress VIP compatibility.
* Better uninstall cleanup to remove all plugin data.

= 1.0.2 =
* Added automatic merge-field support for snippets.
* Improved cache clearing when saving settings.
* Master statement now appears earlier on pages.

= 1.0.1 =
* Added Gutenberg block for LLM snippets.
* Added global Master LLM Statement for all pages.
* Improved performance and security.

= 1.0 =
* Initial release.
