=== SEO Assistant for Elementor ===
Contributors: sajjadnazari
Tags: elementor, seo, image optimization, html tags, page speed
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: elementor
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO Assistant for Elementor (دستیار سئو المنتور): right-click a section in the Elementor editor and get an ordered, scored review of HTML tags, image sizes, mobile fit and speed.

== Description ==

SEO Assistant helps you design pages correctly the first time. In the Elementor editor, right-click any section, container or widget and choose **Review structure**. A window opens, like Elementor's Navigator, and lists the structure in order. Under each element it tells you exactly what to change.

**دستیار سئو المنتور (فارسی):** در ویرایشگر المنتور روی هر سکشن راست‌کلیک کنید و «بررسی ساختار» را بزنید. پنجره‌ای راهنمای مرتب تگ HTML، اندازه و رزولوشن تصویر، تناسب با موبایل و سرعت را همراه با نمره نشان می‌دهد. رابط افزونه فارسی و راست‌چین است.

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

In the desktop view, sizes are measured in the editor preview when the window is wide enough. Otherwise they are estimated from your Elementor settings, so treat them as a guide.

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

= 1.2.3 =
* New: settings are grouped in categories (Images, Page speed, Screens, Structure, Data).
* New: image weight, sharpness, top-image weight and screen width can be set separately for desktop, tablet and mobile. The standard behind each default is written above its fields.
* New: the review uses the limits of the device shown in the editor, and the window says which limits were used.
* Settings saved by earlier versions are kept and used for desktop.
* Persian translation updated.

= 1.1.0 =
* New: page roles. The review knows if the page is the home page, a post or product, an archive, or a header, footer or popup template, and adjusts its checks. Detected automatically, and you can change it in the window.
* New: sizes are measured in the editor preview instead of estimated. In tablet and mobile view, elements wider than the screen are reported.
* New: Core Web Vitals hints. The first image on the page (LCP: weight, background image, entrance animation) and iframes or images without a size (CLS).
* New setting: "Top image: improve above (KB)".

= 1.0.1 =
* Fix: the "Review structure" right-click item now appears on elements already on the page when the editor opens.

= 1.0.0 =
* First release: right-click "Review structure" in the Elementor editor, ordered guidance, scored summary and Check again.

== Upgrade Notice ==

= 1.2.3 =
Adds separate limits for desktop, tablet and mobile. Your saved settings are kept.

= 1.1.0 =
Adds page roles, measured sizes and Core Web Vitals checks.

= 1.0.1 =
Fixes the missing right-click menu item in the Elementor editor.

= 1.0.0 =
First release.

== Screenshots ==

1. Reviewing the whole page: findings per element, in order, with what to do.
2. Reviewing one element, with the first-image (LCP) hint.
3. A footer template: the review knows the page role and adjusts its checks.
