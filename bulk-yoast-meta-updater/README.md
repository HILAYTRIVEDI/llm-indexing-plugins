# Bulk SEO Meta Updater – User Guide

Bulk SEO Meta Updater helps editors, marketers, and agencies update Yoast SEO or All in One SEO (AIOSEO) fields across hundreds of posts without touching code. Use AI for quick wins, CSV files for full control, and the Image Alt Texts console to catch accessibility issues—all with job-by-job logging. THE USE OF THIS PLUGIN IS ENTIRELY AT YOUR OWN RISK. NO WARRANTY OR GURANTEE OF SERVICE IS CONVEYED WITH THE USE OF THIS PLUGIN. 

## 1. Overview

- Automatically detects Yoast SEO or AIOSEO and switches into “Yoast Mode” or “AIOSEO Mode.”
- Keeps detailed logs (who ran what, which fields changed, success/error counts).
- Designed for non-developers—everything happens inside the WordPress admin.
- Adheres to coding best practices endorsed by WPVIP, 10up, and rtCamp.

## 2. Key Features

- **AI Updates (Google Gemini)** – Generate titles, descriptions, and focus keyphrases per row or in bulk. Edit before saving, stop jobs mid-run, and inspect logs for every post.
- **CSV Import & Export** – Use spreadsheets to edit meta data in bulk. Preview diffs, apply changes safely, and keep automated backups.
- **Image Alt Texts** – Find attachments used in real content, generate/edit/save alt text, and sync it across every post that embeds the image.
- **Smart Export Filters** – Count post types, focus on empty/short metas, and estimate CSV size before downloading.
- **Maintenance Tools** – Delete old logs, monitor Recent Jobs, and use “Optimize Tables” to defragment the plugin’s database tables on demand.

## 3. Installation & Requirements

1. In WordPress, visit **Plugins → Add New** and search for “Bulk SEO Meta Updater.”
2. Click **Install Now**, then **Activate**.
3. Follow the short setup flow to add your API key, brand name, and prompts (you can skip and revisit later).

**Requirements**

- WordPress 5.8+ and PHP 7.4+
- Yoast SEO or All in One SEO (free or premium)
- Google Gemini API key for AI features (free tier available)

## 4. Quick Start Checklist

1. Open **Bulk SEO Meta → Settings** and confirm your brand name plus any AI prompt tweaks.
2. Paste your Gemini API key under **AI Generation Settings**.
3. Review safety defaults: 15-row batch size, 4 MB CSV upload limit (5 MB documented), and the 180 ms throttle delay.
4. Run a small AI generation or CSV import to confirm logging and permissions.

## 5. Everyday Workflows

### AI Updates

1. Go to **Bulk SEO Meta → AI Updates**.
2. Select post types, statuses, and optional filters (e.g., “Only show posts with short or blank descriptions”).
3. Click **Load Posts** (up to 100 rows).
4. Generate per row or choose **Generate All On Screen**. The queue auto-pauses on rate limits and logs every response.
5. Edit the suggested fields, then **Save** per row or **Save All Changes**. Completed rows show in Recent Jobs with a downloadable log.

### CSV Import

1. Visit **Bulk SEO Meta → Import CSV**.
2. Upload or drag in your file (4 MB enforced limit). Download the [sample CSV](assets/sample.csv) if you need a template.
3. Review the preview diff; rows with warnings stay visible until you resolve them.
4. Click **Apply Changes**. Each row (updated or skipped) is logged with before/after values.

### Dashboard Export

1. Open **Bulk SEO Meta → Dashboard**.
2. Select post types/statuses and optional filters for short/blank metas.
3. The live estimator shows how many rows match before you export.
4. Click **Export to CSV**. The file downloads directly (no new tab).

### Image Alt Texts

1. Go to **Bulk SEO Meta → Image Alt Texts**.
2. Filter by paginated list, Top 100, or “Show Short Alt Texts (<10 characters).”
3. Use Step 1 (Generate), Step 2 (Save), and Step 3 (Sync) controls. The batch dropdown (1/2/5/8) throttles AI requests.
4. “Sync Alt Text in Content” updates every post embedding the attachment, skipping unsupported formats (e.g., AVIF).

## 6. Customizing AI Prompts

- Edit prompts under **Settings → AI Generation Settings** (titles, descriptions, focus keyphrases, image alt text).
- Use `{{BRAND}}` anywhere to inject your brand automatically.
- Keep instructions concise: state character limits, tone, and output format (“Return only the title, no quotes”).
- Test new prompts on a few posts, then scale. Each bulk job logs how many fields succeeded or failed.

## 7. CSV Import & Export

**Columns**

| Column | Required? | Purpose |
| --- | --- | --- |
| `post_id` *or* `url` | Required | Which post/page to update |
| `meta_title` | Optional | New SEO title |
| `meta_description` | Optional | New meta description |
| `focus_keyword` | Optional | Yoast/AIOSEO focus keyphrase |

Leave cells blank to skip those fields. Only changed rows are processed, and you can always export before large updates to keep a backup.

Exports include post-type counts and respect filters such as “Only include posts where the title or description is blank/under 30 characters.”

## 8. Managing Image Alt Texts

- Generate suggestions per image, edit inline, and save them in batches.
- **Save Generated Alt Text** stays disabled until editable rows exist, preventing accidental clicks.
- **Sync Alt Text in Content** pushes the saved copy into every post that references the image, reducing manual updates across your site.
- Media Library buttons (`Generate Alt Text`) share the same logging, so everything stays traceable.

## 9. Logging, Maintenance & Optimization

### 📋 Recent Jobs

- Located on the Dashboard page. Shows who ran each job, timestamps, success/error counts, and download options.
- Jobs include CSV imports, exports, AI generations, AI saves, alt-text syncs, and maintenance events.

### Clear Old Logs

- Use **Settings → Maintenance → Delete Old Logs**. Respect the retention window configured in Settings or choose “Force all” to wipe every entry.

### Optimize Database → Optimize Tables

- Clicking **Optimize Tables** asks for confirmation and then runs an authenticated AJAX request (`bymu_optimize_database`).
- The server-side handler verifies permissions, then calls `Bulk_Yoast_Meta_Updater_DB_Manager::optimize_tables()`, which executes MySQL’s `OPTIMIZE TABLE` for the plugin’s two logging tables (`bymu_jobs` and `bymu_actions`).
- This defragments storage, rebuilds indexes, and refreshes table statistics—helpful after heavy import/export activity. No other WordPress tables are touched.

## 10. Frequently Asked Questions

**Do I need a Gemini API key?**  
Only for AI features. Everything else works without it. The API key is free - [get one here](https://aistudio.google.com/app/apikey).

**How many posts can I update at once?**  
AI runs handle up to 100 rows per screen. CSV imports depend on your file size (4 MB enforced). Large sites usually work in batches of 50–200 items for speed.

**Can I undo changes?**  
Yes. Export your data first, and use Recent Jobs logs to inspect previous values. You can re-import older data if needed. The plugin logs all old values, so you can manually revert if needed.

**Does it support Yoast Premium or AIOSEO Pro?**  
Yes—both free and premium versions share the same meta fields. Works with both free and premium versions of Yoast SEO.

**Why do I see rate-limit or "service unavailable" messages?**  
Gemini pauses occasionally. The plugin shows a friendly message, temporarily disables the button, and logs the status so you can retry later. Bulk runs auto-retry 429 responses after a cooldown.

**Will this break my site?**  
No. The plugin only updates Yoast SEO fields (titles, descriptions, keywords). It doesn't modify your actual post content or WordPress settings.

**Will AI-generated content be good?**  
The AI analyzes your post content and follows SEO best practices. Always review suggestions before saving - AI is a tool to help you, not replace you.

**Is this safe for enterprise hosting (WPVIP, WP Engine, Pantheon)?**  
Yes. The code follows their performance and security guidelines, with throttled AJAX requests, consistent caching, and database safeguards. The plugin follows WordPress VIP coding standards and is compatible with WP Engine, Pantheon, and enterprise hosting platforms.

## 11. Privacy & Data

- The plugin itself does not collect personal data or phone home. All processing happens on your WordPress site.
- When you use AI features, the relevant post content (text + headings) is sent to Google's Gemini API to generate suggestions. Google's privacy policy applies to that request.
- Image alt-text generation sends a scaled version of the image plus instructions to Gemini.
- No data is shared beyond what's needed for the AI responses.
- The plugin is GDPR compliant and doesn't collect personal data or track users.

## 12. Changelog

### 1.0.3 (2025-11-15)

- Rebranded to **Bulk SEO Meta Updater** with dynamic Yoast/AIOSEO mode labeling
- Added first-run setup wizard to capture Gemini API key, brand name, and prompt overrides (with Skip option)
- Fixed AIOSEO meta detection/export and AI Updates display by batching/caching provider lookups
- Locked batch size (15 rows), upload limit (4 MB enforced / 5 MB documented), and throttle delay (180 ms) for WPVIP compliance
- Renamed Image Alt Sync to **Image Alt Texts** and improved the Top 100 sorting by actual reference counts
- Reduced admin load time via memoization, recent-job caching, and notice gating

### 1.0.2 (2025-11-10)

- Added Image Alt Texts page with bulk AI and syncing controls
- Introduced AI bulk generation queue with rate limiting and automatic logging
- Expanded Recent Jobs logging for AI successes/failures and alt-text actions
- Updated settings layout and Save button for clearer workflows

### 1.0.1 (2025-11-03)

- Added per-user test mode toggle via `?testmode=1`
- Improved export filters for short/blank meta detection
- Hardened WPVIP compliance, database cleanup, and inline rate-limiting
- Bug fixes and UI refinements across dashboard and AI pages

### 1.0.0 (2025-10-27)

**Initial Release:**

- CSV Import/Export for bulk Yoast SEO updates
- AI-powered title and description generation
- AI alt text generation for images  
- Complete change logging
- Easy-to-use settings
- Clean, modern interface
- WordPress VIP compatible

## 13. License

Bulk SEO Meta Updater is free software, released under the GPLv2 (or later).  
Copyright © 2025 Development Team.

You may redistribute and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License or (at your option) any later version.

---

## Customizing AI Prompts for Better Results

The quality of AI-generated content depends heavily on the instructions (prompts) you provide. The plugin includes proven default prompts, but you can customize them to match your brand voice, industry, and SEO strategy.

### Where to Customize Prompts

Go to **Bulk SEO Meta > Settings > AI Generation Settings** to edit:
- **Brand Name** - Your company/site name (defaults to your WordPress site title)
- **Title Prompt** - Instructions for generating SEO titles
- **Meta Description Prompt** - Instructions for generating meta descriptions
- **Focus Keyphrase Prompt** - Instructions for identifying keywords
- **Image Alt Text Prompt** - Instructions for image descriptions

### Using the {{BRAND}} Merge Field

You can use `{{BRAND}}` in any of your custom prompts, and it will automatically be replaced with your Brand Name when sending requests to the AI.

**Example:**
```
Generate an SEO title for {{BRAND}} that includes the main keyword...
```

If your Brand Name is "Acme Corporation", the AI will receive:
```
Generate an SEO title for Acme Corporation that includes the main keyword...
```

**Why use {{BRAND}}?**
- Keep your prompts consistent across all content
- Easy to update brand references in one place
- AI provides more contextual, brand-relevant suggestions

### What Makes a Good Prompt

**Good prompts are:**
1. **Specific** - Include exact requirements (character limits, tone, structure)
2. **Clear** - Use simple, direct language
3. **Actionable** - Tell AI exactly what to do
4. **Constrained** - Set boundaries (length, style, format)
5. **Examples-driven** - Show what you want (when possible)

**Poor prompts are:**
- Vague ("make it good")
- Contradictory ("be brief but detailed")
- Overly complex (multiple competing requirements)
- Missing key constraints (no length limits)

### Prompt Examples for SEO Titles

**Default Prompt (Included):**
```
Generate an SEO-optimized title tag for this content. Requirements:
1) Maximum 60 characters
2) Include primary keyword naturally
3) Be compelling and click-worthy
4) Use active voice
5) Avoid clickbait or exaggeration
6) Return only the title text, no quotes or extra formatting
```

**E-commerce Store:**
```
Create a product title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Product Name] - [Key Benefit] | {{BRAND}}
3) Include the main product keyword
4) Highlight the primary selling point
5) Use title case
6) Be clear and descriptive, not promotional
7) Return only the title, no quotes
```

**News/Blog Site:**
```
Write a headline for this article. Requirements:
1) Maximum 60 characters
2) Start with a number, question, or power word when relevant
3) Include the main topic keyword
4) Create curiosity without being clickbait
5) Use sentence case
6) Be specific about what readers will learn
7) Return only the headline, no formatting
```

**Local Business:**
```
Generate a local SEO title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Service/Product] in [City Name] | {{BRAND}}
3) Include the target service keyword
4) Include the city or neighborhood name
5) Use title case
6) Be straightforward and professional
7) Return only the title, no quotes
```

**SaaS/Technology:**
```
Create a technical SEO title for this content. Requirements:
1) Maximum 60 characters
2) Include the primary feature or solution keyword
3) Focus on the problem being solved or outcome delivered
4) Use clear, jargon-free language when possible
5) Include a benefit or result
6) Use title case
7) Return only the title, no extra text
```

### Prompt Examples for Meta Descriptions

**Default Prompt (Included):**
```
Generate an SEO-optimized meta description for this content. Requirements:
1) Maximum 155 characters
2) Summarize the main topic clearly
3) Include a call-to-action or benefit
4) Use natural language, avoid keyword stuffing
5) Be compelling and informative
6) Return only the description, no quotes or extra formatting
```

**E-commerce Store:**
```
Write a product meta description for {{BRAND}}. Requirements:
1) Maximum 155 characters
2) First sentence: Primary benefit or unique selling point
3) Second sentence: Key features or what's included
4) Include a soft call-to-action (e.g., "Shop at {{BRAND}}" or "Compare options")
5) Use persuasive but honest language
6) Include the target keyword naturally
7) Return only the description, no formatting
```

**Service Business:**
```
Create a service-focused meta description. Requirements:
1) Maximum 155 characters
2) Start with the main problem this service solves
3) Mention your unique approach or advantage
4) Include location if relevant
5) End with a call-to-action (e.g., "Get a free quote")
6) Use second person ("you," "your")
7) Return only the description, no quotes
```

**Blog/Content Site:**
```
Write an informative meta description. Requirements:
1) Maximum 155 characters
2) Summarize what readers will learn or discover
3) Mention the format (guide, tutorial, tips, etc.)
4) Include the main keyword naturally
5) Create interest without spoiling conclusions
6) Use active voice
7) Return only the description, no extra text
```

### Prompt Examples for Focus Keyphrases

**Default Prompt (Included):**
```
Identify the primary focus keyphrase for this content. Requirements:
1) Return 1-4 words only
2) Choose the most important keyword or phrase
3) Should match what users would search for
4) Must appear in the content naturally
5) Prefer longer-tail phrases over single words
6) Return only the keyphrase, lowercase, no quotes
```

**High-Volume Keywords:**
```
Identify the main SEO keyword. Requirements:
1) Return 1-2 words maximum
2) Choose the broadest relevant term
3) Focus on high-search-volume keywords
4) Must be directly related to the main topic
5) Should be a term people commonly search
6) Return lowercase, no punctuation
```

**Long-Tail Keywords:**
```
Identify a specific long-tail keyword phrase. Requirements:
1) Return 3-5 words
2) Choose a phrase that matches specific user intent
3) Should be more specific than broad
4) Include qualifiers (location, type, purpose, etc.)
5) Must reflect the actual content focus
6) Return lowercase, no special characters
```

### Prompt Examples for Image Alt Text

**Default Prompt (Included):**
```
Describe this image for accessibility. Requirements:
1) Maximum 15 words
2) Follow object-action-context structure
3) Be objective and functional
4) No phrases like "image of" or "picture showing"
5) Include brand name if relevant
6) Focus on what's actually visible
7) Return only the description, no quotes
```

**Product Images:**
```
Create product image alt text for {{BRAND}}. Requirements:
1) Maximum 12 words
2) Format: [Product name], [key visual feature], [color if relevant]
3) Include {{BRAND}} naturally
4) Describe what makes this product unique visually
5) Be objective, not promotional
6) No "image of" or similar phrases
7) Return only the description
```

**People/Team Photos:**
```
Describe this photo for accessibility at {{BRAND}}. Requirements:
1) Maximum 15 words
2) Include number of people and their roles/actions
3) Describe the setting/environment
4) Mention any notable interactions or activities
5) Include {{BRAND}} naturally
6) Be professional and objective
7) Return only the description, no formatting
```

### Testing Your Custom Prompts

1. **Start with defaults** - The included prompts follow SEO best practices
2. **Make one change at a time** - Test individual modifications
3. **Generate 5-10 examples** - Test on different post types
4. **Review for consistency** - Check if output matches your expectations
5. **Adjust and refine** - Modify based on results

### Common Prompt Mistakes to Avoid

**Too Vague:**
- BAD: "Write a good title"
- GOOD: "Generate a 50-60 character title that includes the keyword and creates urgency"

**Conflicting Requirements:**
- BAD: "Be brief but include lots of detail"
- GOOD: "Summarize the main benefit in 10 words maximum"

**Missing Constraints:**
- BAD: "Create a meta description"
- GOOD: "Create a meta description between 140-155 characters"

**Too Many Requirements:**
- BAD: 15+ requirements covering every edge case
- GOOD: 5-7 clear, essential requirements

**No Format Specification:**
- BAD: Prompt doesn't specify output format
- GOOD: "Return only the title, no quotes, punctuation, or extra formatting"

### Industry-Specific Customization Tips

**Legal/Medical:**
- Add: "Use professional, accurate terminology"
- Add: "Avoid making claims or guarantees"
- Add: "Be factual and conservative in tone"

**Hospitality/Tourism:**
- Add: "Use descriptive, appealing language"
- Add: "Mention location prominently"
- Add: "Highlight experience or atmosphere"

**B2B/Enterprise:**
- Add: "Focus on business outcomes and ROI"
- Add: "Use industry terminology appropriately"
- Add: "Address decision-maker concerns"

**Education:**
- Add: "Clearly state learning outcomes"
- Add: "Use accessible, clear language"
- Add: "Mention skill level when relevant"

---

## 14. Troubleshooting

**"Insufficient permissions" error**

You need to be logged in as an Administrator.

**CSV file won't upload**

- Check the file is saved as .csv format
- Make sure it's under 4 MB (enforced limit)
- Try exporting a sample first to see the correct format

**AI not generating content**

- Confirm your API key is present and valid in Settings
- Make sure the post has meaningful content for the AI to analyze
- Review the latest Recent Job entry for rate-limit or content errors
- Bulk runs auto-retry 429 responses after a cooldown; rerun if limits persist

**Changes aren't showing in Yoast/AIOSEO**

- Clear your browser cache
- Check the Recent Jobs log to confirm changes were applied
- Verify you're editing the correct post

**Need More Help?**

Check the Recent Jobs section for detailed error messages, or contact your WordPress administrator.

---

## 15. Support & Documentation

### Getting Help
- Check the FAQ section above
- Review the Recent Jobs logs for error details
- Check your WordPress error logs
- Contact your site administrator

---

## 16. Credits

**Built for SEO Professionals and Content Managers**

- Uses Google Gemini AI for intelligent content generation
- Follows WordPress VIP coding standards
- Compatible with enterprise hosting platforms
- Optimized for performance and security
- Developed by [Manna Digital](https://mannadigital.com).
