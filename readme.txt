=== SEO Assistant by Nazari Technologies ===
Contributors: sajjadnazari
Tags: elementor, seo, image optimization, html tags, page speed
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: elementor
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO Assistant (دستیار سئو): right-click a section in the Elementor editor and get an ordered, scored review of HTML tags, image sizes, mobile fit and speed.

== Description ==

SEO Assistant helps you design pages correctly the first time. In the Elementor editor, right-click any section, container or widget and choose **Review structure**. A window opens, like Elementor's Navigator, and lists the structure in order. Under each element it tells you exactly what to change.

**دستیار سئو (فارسی):** در ویرایشگر المنتور روی هر سکشن راست‌کلیک کنید و «بررسی ساختار» را بزنید. پنجره‌ای راهنمای مرتب تگ HTML، اندازه و رزولوشن تصویر، تناسب با موبایل و سرعت را همراه با نمره نشان می‌دهد. رابط افزونه فارسی و راست‌چین است.

**What it checks**

* HTML tags: header, footer, main, section; one H1 per page; heading order; empty or too long headings.
* Images: file size, pixel size against the size shown on screen, blurry or oversized images, WebP/AVIF, alt text.
* Mobile fit: fixed widths wider than a phone, huge minimum heights, large padding, rows that do not wrap.
* Speed: heavy sliders, videos, maps, iframes, animations, deep nesting and total image weight.
* Links and buttons: empty, vague or missing links.

**The review window**

* Elements in order, each with what to do and where to find the setting.
* A final summary with points per category (HTML tags 20, Dimensions 20, Loading speed 30, Content and accessibility 30) and a "Fix these first" list.
* A **Check again** button to run the review again after you fix things.
* Review one element or the whole page.

**Built for developers**

* PHP 8.2+, strict types, enums.
* Add your own checks with the `nzsa_rules` filter.
* Nothing is sent to any outside service. The review runs on your own site.

Sizes shown on screen are estimated from your Elementor settings, not measured in the browser, so treat them as a guide.

== Installation ==

1. Install and activate Elementor.
2. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
3. Activate the plugin.
4. Open a page in Elementor, right-click a section and choose "Review structure".

== Frequently Asked Questions ==

= Does it need Elementor Pro? =
No. It works with free Elementor.

= Does it change my pages? =
No. It only reads the page and gives advice.

= Is anything sent to an external server? =
No. The review is done on your own site, by a protected REST endpoint that only users who can edit the page may call.

= Which languages are included? =
English and Persian (fa_IR), with RTL support.

== Changelog ==

= 1.0.1 =
* Fix: the "Review structure" right-click item now appears on elements already on the page when the editor opens.

= 1.0.0 =
* First release: right-click "Review structure" in the Elementor editor, ordered guidance, scored summary and Check again.

== Upgrade Notice ==

= 1.0.1 =
Fixes the missing right-click menu item in the Elementor editor.

= 1.0.0 =
First release.

== Screenshots ==

1. The review window in the Elementor editor.
2. The dashboard.
3. Settings.
