=== Schemagic ===
Contributors: Jaymingxyz
Tags: schema, local business, structured data, json-ld, local seo
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add LocalBusiness structured data (JSON-LD) to your site by filling in a simple form. Every feature is free.

== Description ==

Schemagic helps search engines understand your business: its name, address, phone number, opening hours and more. Fill in a form and Schemagic adds clean JSON-LD structured data to your pages.

There is no Pro version. Nothing is locked, there are no upsells, and the plugin makes no external requests.

**Features**

* Every schema.org LocalBusiness type, from Dentist to Winery, with search
* Unlimited locations, each shown on the front page, the whole site, or the pages you choose
* Opening hours with split shifts, 24-hour days and closing times after midnight
* Holiday and special hours
* Logo and photos from the Media Library
* Social profile links (sameAs)
* Menu, cuisine and reservations for restaurants, cafes and bars
* Service-area businesses that don't show a street address
* A health check showing what's required and what Google recommends
* Live preview of the generated code, with a copy button and links to Google's Rich Results Test and the Schema.org Validator
* A [schemagic] shortcode to show the same details to visitors
* A notice when another SEO plugin might add duplicate schema
* Developer filters for the schema, fields, business types and capability

**What Schemagic doesn't do**

Schemagic focuses on LocalBusiness data. It doesn't add Product, Article, FAQ or review schema. Structured data helps search engines understand your site, but no plugin can guarantee rich results or better rankings.

== Installation ==

1. Install and activate Schemagic from Plugins → Add New.
2. Go to **Schemagic → Add New**.
3. Enter your business details, working through the tabs.
4. Check the Schema health box, then click **Publish**.
5. Use the Rich Results Test button to confirm Google can read your markup.

== Frequently Asked Questions ==

= Where does the schema appear? =

In the page's `<head>`, as a `<script type="application/ld+json">` tag. By default a single location is shown on your front page. You can show it on every page or only on pages you choose.

= I have more than one location. =

Add one location for each. Point each one at the page about that location using "Display on → Selected pages only".

= Do empty fields cause problems? =

No. Empty fields are left out of the output.

= How do I show my address and hours on a page? =

Use the shortcode:

`[schemagic]` shows everything for the location on the current page, or your first location.

`[schemagic show="address,phone,hours" location="123"]` shows chosen parts for a specific location. The location ID is in the address bar when you edit it.

= I also use Yoast SEO, Rank Math or another SEO plugin. =

Many SEO plugins add Organization schema, and some have local business add-ons. Turn off any local business schema feature in the other plugin so the markup isn't duplicated, then check a page with the Rich Results Test.

= What happens to my data if I delete the plugin? =

Nothing is deleted unless you turn on **Remove data on uninstall** in Schemagic → Settings.

= Can developers change the output? =

Yes. Filters include `schemagic_schema_data`, `schemagic_should_output`, `schemagic_fields`, `schemagic_business_types` and `schemagic_capability`.

= Where do I report a bug? =

Open an issue at [github.com/jaymingxyz/schemagic/issues](https://github.com/jaymingxyz/schemagic/issues). Please include your WordPress and PHP versions and the steps to reproduce the problem.

== Credits ==

Schemagic is developed by Jay. Source code: [github.com/jaymingxyz/schemagic](https://github.com/jaymingxyz/schemagic).

== Screenshots ==

1. Business details, organized in tabs.
2. Weekly opening hours and holiday hours.
3. Schema health check and live code preview.

== Changelog ==

= 0.1.0 =
* First release.
