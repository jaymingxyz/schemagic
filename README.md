# Schemagic

Add LocalBusiness structured data (JSON-LD) to your WordPress site by filling in a simple form. Every feature is free.

| Requires WordPress | Tested up to | Requires PHP | Version | License |
|---|---|---|---|---|
| 6.0 | 7.0 | 7.4 | 1.0 | [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) |

## Description

Schemagic helps search engines understand your business: its name, address, phone number, opening hours and more. Fill in a form and Schemagic adds clean JSON-LD structured data to your pages.

There is no Pro version. Nothing is locked, there are no upsells, and the plugin makes no external requests.

### Features

- Every schema.org LocalBusiness type, from Dentist to Winery, with search
- Unlimited locations, each shown on the front page, the whole site, or the pages you choose
- Opening hours with split shifts, 24-hour days and closing times after midnight
- Holiday and special hours
- Logo and photos from the Media Library
- Social profile links (`sameAs`)
- Menu, cuisine and reservations for restaurants, cafes and bars
- Service-area businesses that don't show a street address
- A health check showing what's required and what Google recommends
- Live preview of the generated code, with a copy button and links to Google's Rich Results Test and the Schema.org Validator
- Import existing schema: paste JSON-LD from another plugin or site and the form fills itself in
- A `[schemagic]` shortcode to show the same details to visitors
- A notice when another SEO plugin might add duplicate schema
- Developer filters for the schema, fields, business types and capability

### What Schemagic doesn't do

Schemagic focuses on LocalBusiness data. It doesn't add Product, Article, FAQ or review schema. Structured data helps search engines understand your site, but no plugin can guarantee rich results or better rankings.

## Installation

### From WordPress

1. Install and activate Schemagic from **Plugins → Add New**.
2. Go to **Schemagic → Add New**.
3. Enter your business details, working through the tabs.
4. Check the **Schema health** box, then click **Publish**.
5. Use the **Rich Results Test** button to confirm Google can read your markup.

### From GitHub

Download the [latest code as a ZIP](https://github.com/jaymingxyz/schemagic/archive/refs/heads/main.zip) and upload it in **Plugins → Add New → Upload Plugin**, or clone it into your plugins folder:

```bash
git clone https://github.com/jaymingxyz/schemagic.git wp-content/plugins/schemagic
```

Then activate it and follow steps 2–5 above.

## Frequently asked questions

### Where does the schema appear?

In the page's `<head>`, as a `<script type="application/ld+json">` tag. By default a single location is shown on your front page. You can show it on every page or only on pages you choose.

### I have more than one location.

Add one location for each. Point each one at the page about that location using **Display on → Selected pages only**.

### Do empty fields cause problems?

No. Empty fields are left out of the output.

### How do I show my address and hours on a page?

Use the shortcode. This shows everything for the location on the current page, or your first location:

```
[schemagic]
```

This shows chosen parts for a specific location. The location ID is in the address bar when you edit it.

```
[schemagic show="address,phone,hours" location="123"]
```

### I also use Yoast SEO, Rank Math or another SEO plugin.

Many SEO plugins add Organization schema, and some have local business add-ons. Turn off any local business schema feature in the other plugin so the markup isn't duplicated, then check a page with the Rich Results Test.

### Can I import schema from another plugin or website?

Yes. On a location's edit screen, open **Import existing schema**. Paste the JSON-LD, a whole `<script type="application/ld+json">` tag, or a page's HTML source, then click **Fill in fields**.

Schemagic reads the business type, name, contact details, address, coordinates, opening hours, holiday hours, social links and more. Images are matched to your Media Library; images hosted elsewhere are skipped. If the code describes several businesses, choose which one to import. Check each tab, then click **Publish** or **Update**.

### What happens to my data if I delete the plugin?

Nothing is deleted unless you turn on **Remove data on uninstall** in **Schemagic → Settings**.

### Can developers change the output?

Yes. Filters include:

| Filter | Purpose |
|---|---|
| `schemagic_schema_data` | Change a location's final schema array |
| `schemagic_should_output` | Stop output on a request |
| `schemagic_fields` | Register extra fields |
| `schemagic_business_types` | Add or remove business types |
| `schemagic_capability` | Change the capability required to manage Schemagic |

### Where do I report a bug?

Open an issue at [github.com/jaymingxyz/schemagic/issues](https://github.com/jaymingxyz/schemagic/issues). Please include your WordPress and PHP versions and the steps to reproduce the problem.

## Changelog

### 1.0

- First release.

## Credits

Schemagic is developed by Jay. Source code: [github.com/jaymingxyz/schemagic](https://github.com/jaymingxyz/schemagic).
