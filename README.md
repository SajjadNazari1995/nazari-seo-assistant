# SEO Assistant (دستیار سئو)

By **Nazari Technologies**. Right-click a section in the Elementor editor, choose **Review structure**, and get an ordered, scored review of HTML tags, image sizes, mobile fit and loading speed, with a **Check again** button.

- PHP 8.2+, WordPress 6.5+, Elementor
- English and Persian (RTL)
- Review runs on your own site through a nonce- and capability-protected REST endpoint

## Developer hooks

```php
add_filter( 'nzsa_rules', fn( $rules ) => array_merge( $rules, array( new My_Rule() ) ) );
```

Extend `NZSA_Rule` and implement `id()`, `check()` and/or `check_page()`.

License: GPL-2.0-or-later
