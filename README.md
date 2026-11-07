<p align="center">
  <img src="screenshots/banner.png" alt="SEO Assistant by Nazari Technologies" width="100%">
</p>

# SEO Assistant by Nazari Technologies

**دستیار سئو** · an Elementor add-on that reviews your page structure while you build it.

Right-click any section, container or widget in the Elementor editor, choose **Review structure** (بررسی ساختار), and a floating window lists every element in order with exactly what to change: the HTML tag to use, image size and resolution, mobile fit and loading speed. The last item is a scored summary with a **Check again** button.

![Version](https://img.shields.io/badge/version-1.1.0-2f6bff)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b)
![Elementor](https://img.shields.io/badge/Elementor-required-92003b)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

---

## Why this plugin

Most SEO tools audit a page after it is published. By then, an oversized hero image, a second H1 or a fixed-width row that overflows on phones is already live, and fixing it means going back through the whole design.

SEO Assistant moves the review into the editor. You get the advice at the moment you are building the element, next to the setting you need to change, so the page is built to standard the first time.

- **No separate SEO pass.** The review lives inside Elementor.
- **Nothing leaves your site.** No external service, no account, no tracking.
- **Read-only.** It never changes your page; it only gives advice.
- **Persian and English.** The interface follows the site language, with full RTL support.

## Screenshots

| Dashboard | Settings |
|---|---|
| ![Dashboard](screenshots/dashboard.png) | ![Settings](screenshots/settings.png) |

## How it works

1. **Right-click.** In the Elementor editor, right-click a section, container or widget and pick **Review structure**.
2. **Read the guide.** A draggable window, laid out like Elementor's Navigator, lists each element in document order. Click an element's name to select it and scroll to it in the editor. Under each element you see what is wrong and what to do about it.
3. **Fix and check again.** The final item is a summary with the score and a **Fix these first** list. After your changes, press **Check again**.

You can review just the element you clicked or switch to the **whole page** from the window's toolbar. A checkbox shows or hides elements that have no problems.

### Page roles

The review adapts to what the page is for. The plugin detects the role automatically, shows it in the window ("Automatic: Home page"), and lets you change it from the dropdown.

| Role | How it is detected | What changes |
|---|---|---|
| Home page | The static front page set in Settings → Reading | The first image is checked as the likely LCP element |
| Page | Any other page | Standard checks |
| Single post or product | Posts, custom post types and Elementor Pro single templates | Standard checks |
| Archive | Elementor Pro archive, search-results and loop templates | Standard checks |
| Header template | Elementor Pro header template | The block must use the `header` tag; no "missing H1"; an H1 inside is flagged |
| Footer template | Elementor Pro footer template | The block must use the `footer` tag; no "missing H1"; an H1 inside is flagged |
| Popup | Elementor Pro popup | No landmark tag is required; no "missing H1"; an H1 inside is flagged |

If your site has an assigned header or footer template, the first and last block of an ordinary page are no longer guessed to be the header and footer.

### Measured sizes

In the desktop view the window measures the real drawn width of every element and of each widget's first image in the editor preview, instead of estimating it from Elementor settings. Measured sizes are used only when the preview is within about 15% of the "Desktop width" setting, otherwise a narrow editor window would squeeze every element; the window tells you which case applies.

In the tablet and mobile views the window checks that nothing is wider than the screen and reports elements that stick out of the page.

## What it checks

| Area | Examples |
|---|---|
| **HTML tags** | Landmarks (header, footer, main, section); one H1 per page; heading order; empty or overly long headings |
| **Image dimensions** | File size; pixel size compared with the size shown on screen; blurry or oversized images; WebP/AVIF; alt text |
| **Mobile fit** | Fixed widths wider than a phone; huge minimum heights; large padding; rows that do not wrap; elements measured wider than the screen in tablet or mobile view |
| **Loading speed** | Heavy sliders, videos, maps, iframes and animations; deep nesting; total image weight |
| **Core Web Vitals** | LCP: the first image on the page (weight, CSS background, entrance animation). CLS: iframes and images written in HTML without a size |
| **Links and buttons** | Empty, vague or missing links |

Every finding has a severity, a short message and a "What to do" line that names the setting to change.

| Severity | Label in the window | Penalty |
|---|---|---|
| Error | Fix | 3 |
| Warning | Improve | 1.5 |
| Info | Tip | 0.5 |

## Scoring

The summary gives a score out of 100, split into four categories:

| Category | Points |
|---|---|
| HTML tags | 20 |
| Dimensions | 20 |
| Loading speed | 30 |
| Content and accessibility | 30 |

Each category has a penalty budget. Findings add penalty by severity, and the category's points are `round(max × max(0, 1 − penalty / budget))`. The window then grades the total as Excellent, Good, Needs work or Poor.

## Requirements

- WordPress 6.5 or newer
- PHP 8.2 or newer
- Elementor (free is enough; Elementor Pro is not required)

## Installation

**From a zip**

1. Install and activate Elementor.
2. In WordPress go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **SEO Assistant by Nazari Technologies**.
4. Open any page in Elementor, right-click a section and choose **Review structure**.

**From source**

```bash
cd wp-content/plugins
git clone https://github.com/SajjadNazari1995/nazari-seo-assistant.git
```

Then activate it from the Plugins screen. There is no build step.

## Settings

Open **SEO Assistant → Settings**.

| Setting | Default | What it does |
|---|---|---|
| Image size: improve above (KB) | 200 | Heavier images get an *Improve* notice |
| Image size: fix above (KB) | 500 | Heavier images get a *Fix* notice |
| Top image: improve above (KB) | 150 | The first image on a page gets a lower limit because it decides how fast the page feels (allowed 30 to 2000) |
| Sharpness factor | 2 | How many times the displayed width an image may be (2 suits retina screens; allowed 1 to 3) |
| Maximum nesting depth | 6 | Containers nested deeper than this are flagged (allowed 3 to 15) |
| Desktop width used for estimates (px) | 1440 | Used to work out how wide each element appears on desktop (allowed 1024 to 2560) |
| Delete data on uninstall | Off | Also removes the plugin's settings when you delete it |

The **Rules** tab lists the built-in checks, and **About** shows the changelog.

## Security

- The review runs on your own site through `POST /wp-json/nazari-seo/v1/analyze`.
- Every request needs a valid REST nonce and a user who is allowed to edit the page.
- Requests larger than 1 MB are rejected.
- All text in the editor window is written with `textContent`, never as HTML.
- No data is sent to an outside server.

## For developers

### Add your own check

Extend `NZSA_Rule` and register it with the `nzsa_rules` filter.

```php
<?php
defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'NZSA_Rule' ) ) {
		return;
	}

	final class My_Button_Rule extends NZSA_Rule {

		public function id(): string {
			return 'my-button';
		}

		/** Called once for every element, in document order. */
		public function check( NZSA_Context $ctx, NZSA_Node $node ): array {
			if ( 'button' !== $node->widget || '' !== (string) $node->get( 'text' ) ) {
				return array();
			}

			return array(
				$this->issue(
					$node,
					NZSA_Category::Access,
					NZSA_Severity::Warning,
					'empty-text',
					__( 'This button has no text.', 'my-plugin' ),
					__( 'Add a short label in Content > Text.', 'my-plugin' )
				),
			);
		}
	}

	add_filter( 'nzsa_rules', fn( array $rules ): array => array_merge( $rules, array( new My_Button_Rule() ) ) );
} );
```

| Method | When it runs |
|---|---|
| `id()` | Required. A unique id used in reports |
| `check( $ctx, $node )` | Once per element, in document order |
| `check_page( $ctx )` | Once per review, for checks that need the whole tree (for example "one H1 per page") |

`NZSA_Node` exposes `id`, `type`, `widget`, `settings`, `parent`, `depth` and `index`, plus helpers such as `get()`, `size()`, `unit()`, `has_device()` and `descendants()`. `NZSA_Context` gives access to plugin settings and page-wide data such as the heading list.

### Project structure

```
seo-assistant-for-elementor/
├── seo-assistant-for-elementor.php     Plugin header and PHP version guard
├── uninstall.php
├── includes/
│   ├── class-nzsa-plugin.php    Bootstrap and loading
│   ├── class-nzsa-types.php     Enums (severity, category), Issue, Node
│   ├── class-nzsa-settings.php  Settings and sanitising
│   ├── class-nzsa-rule.php      Abstract base class for checks
│   ├── class-nzsa-tree.php      Builds the node tree from Elementor data
│   ├── class-nzsa-roles.php     Page roles (home, header, footer, popup, ...)
│   ├── class-nzsa-measure.php   Sizes measured in the editor preview
│   ├── class-nzsa-context.php   Shared data for rules
│   ├── class-nzsa-analyzer.php  Runs the rules and calculates the score
│   ├── class-nzsa-rest.php      REST endpoint
│   ├── class-nzsa-editor.php    Loads the window into the Elementor editor
│   ├── class-nzsa-admin.php     Dashboard and settings pages
│   └── rules/                   tags, images, weight, responsive, links, vitals
├── assets/                      editor.js, editor.css, admin.css, images
├── languages/                   fa_IR translation
└── .wordpress-org/              Icons, banners, screenshots
```

### Editor hook

The right-click item is added through Elementor's `elements/base/behaviors` filter. The window can also be opened from code:

```js
window.NZSA_EDITOR_API.review( elementor.getContainer( 'element-id' ) );
window.NZSA_EDITOR_API.close();
```

## Known limits

- Measured sizes are used only in the desktop view with a wide enough editor window. Otherwise widths are **estimated** from Elementor settings and the desktop width in the plugin settings, so treat them as a guide.
- The LCP check assumes the first image in the first block is the largest visible element. A heading or video can be the real LCP element.
- Tested on Elementor 4.5 (free). Page-role detection for Elementor Pro templates (header, footer, single, archive, popup) and the atomic editor variants, and very large pages, have not been tested in a live Pro editor yet.
- The official WordPress.org Plugin Check has not been run yet.

## Roadmap

- Contrast and touch-target size checks
- Interaction hints (INP) for heavy scripts and widgets
- A score history per page
- More built-in rules

Ideas and bug reports are welcome.

## Translations

English and Persian (fa_IR) are included, with RTL support. The language follows your WordPress site language.

## Changelog

### 1.1.0
- New: **page roles**. The review knows whether the page is the home page, a post or product, an archive, or a header, footer or popup template, and adjusts its checks. Detected automatically and changeable from the window.
- New: **measured sizes**. Widths are measured in the editor preview instead of estimated. In tablet and mobile view, elements wider than the screen are reported.
- New: **Core Web Vitals hints**: LCP for the first image on the page, CLS for iframes and images without a size.
- New setting: **Top image: improve above (KB)**.
- Persian translation updated.

### 1.0.1
- Fix: the **Review structure** right-click item now appears on elements that are already on the page when the editor opens.

### 1.0.0
- First release: right-click **Review structure** in the Elementor editor, ordered guidance per element, a scored summary and a **Check again** button.
- English and Persian, with RTL support.

## Support

Questions, bugs and feature requests: sajjadnazari.ir@gmail.com, or [open an issue on GitHub](https://github.com/SajjadNazari1995/nazari-seo-assistant/issues).

WordPress.org profile: [sajjadnazari](https://profiles.wordpress.org/sajjadnazari/)

---

## دستیار سئو (فارسی)

**دستیار سئو** افزونه‌ای برای المنتور است که ساختار صفحه را همان لحظه‌ای که می‌سازید بررسی می‌کند، نه بعد از انتشار.

### چطور کار می‌کند

1. در ویرایشگر المنتور روی هر سکشن، کانتینر یا ویجت **راست‌کلیک** کنید و **بررسی ساختار** را بزنید.
2. پنجره‌ای شناور، شبیه ناویگیتور المنتور، همه المان‌ها را به ترتیب نشان می‌دهد. زیر هر المان دقیقاً نوشته شده چه چیزی را کجا تغییر دهید. با کلیک روی نام المان، همان المان در ویرایشگر انتخاب می‌شود.
3. آخرین بخش، **خلاصه و نمره** است. بعد از اصلاحات دکمه **بررسی دوباره** را بزنید.

### چه چیزهایی بررسی می‌شود

- **تگ‌های HTML:** header، footer، main و section؛ فقط یک H1 در صفحه؛ ترتیب تیترها؛ تیترهای خالی یا خیلی طولانی.
- **ابعاد تصویر:** حجم فایل، اندازه پیکسلی در برابر اندازه نمایش، تصاویر محو یا بزرگ‌تر از نیاز، فرمت WebP/AVIF و متن جایگزین (alt).
- **تناسب با موبایل:** عرض ثابت بزرگ‌تر از گوشی، ارتفاع حداقل خیلی زیاد، پدینگ زیاد و ردیف‌هایی که در موبایل شکسته نمی‌شوند.
- **سرعت:** اسلایدر، ویدیو، نقشه، iframe و انیمیشن سنگین، تودرتویی زیاد و مجموع حجم تصاویر.
- **لینک‌ها و دکمه‌ها:** لینک خالی، مبهم یا فراموش‌شده.
- **Core Web Vitals:** اولین تصویر صفحه (LCP) از نظر حجم، پس‌زمینه بودن و انیمیشن ورود؛ و iframe یا تصویرهای HTML بدون اندازه که باعث پرش محتوا (CLS) می‌شوند.

### نقش صفحه

افزونه نقش صفحه را خودکار تشخیص می‌دهد (صفحه اصلی، صفحه، نوشته یا محصول تکی، آرشیو، قالب هدر، قالب فوتر، پاپ‌آپ) و در پنجره نشان می‌دهد. می‌توانید آن را از منوی کشویی تغییر دهید. قانون‌ها بر اساس نقش فرق می‌کنند؛ مثلاً در قالب فوتر «نبودن H1» خطا نیست و H1 داخل فوتر هشدار می‌گیرد.

### اندازه‌گیری واقعی

در نمایش دسکتاپ، عرض واقعی المان‌ها و تصاویر از پیش‌نمایش ویرایشگر خوانده می‌شود، نه تخمین. در نمایش تبلت و موبایل المان‌هایی که از عرض صفحه بیرون می‌زنند گزارش می‌شوند.

### نمره‌دهی

نمره از ۱۰۰: تگ‌های HTML ۲۰، ابعاد ۲۰، سرعت بارگذاری ۳۰، محتوا و دسترس‌پذیری ۳۰.

### ویژگی‌ها

- رابط کاملاً فارسی و راست‌چین
- هیچ داده‌ای به سرور بیرونی ارسال نمی‌شود و بررسی روی خود سایت انجام می‌شود
- فقط راهنمایی می‌دهد و هیچ تغییری در صفحه شما نمی‌دهد
- با المنتور رایگان کار می‌کند
- قابل توسعه با فیلتر `nzsa_rules` (بخش «For developers» بالا)

### نیازمندی‌ها

وردپرس ۶.۵+، PHP ‎۸.۲+ و افزونه المنتور.

### نکته

اندازه‌گیری واقعی فقط در نمایش دسکتاپ و با پنجره ویرایشگر به‌اندازه کافی عریض استفاده می‌شود؛ در غیر این صورت عرض‌ها از روی تنظیمات المنتور **تخمین زده می‌شوند** و باید آن‌ها را راهنما بدانید.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Made by [Nazari Technologies](https://github.com/SajjadNazari1995).
