[![pipeline status](https://gitlab.com/wpdesk/wp-notice/badges/master/pipeline.svg)](https://gitlab.com/wpdesk/wp-notice/pipelines)
[![coverage report](https://gitlab.com/wpdesk/wp-notice/badges/master/coverage.svg?job=integration+test+lastest+coverage)](https://gitlab.com/wpdesk/wp-notice/commits/master)
[![Latest Stable Version](https://poser.pugx.org/wpdesk/wp-notice/v/stable)](https://packagist.org/packages/wpdesk/wp-notice)
[![Total Downloads](https://poser.pugx.org/wpdesk/wp-notice/downloads)](https://packagist.org/packages/wpdesk/wp-notice)
[![Latest Unstable Version](https://poser.pugx.org/wpdesk/wp-notice/v/unstable)](https://packagist.org/packages/wpdesk/wp-notice)
[![License](https://poser.pugx.org/wpdesk/wp-notice/license)](https://packagist.org/packages/wpdesk/wp-notice)

# wp-notice

A simple library for WordPress plugins that displays admin notices. This package is a Composer library intended to be loaded by a plugin; it is not a standalone WordPress plugin.

It can be used to:

* Display simple error, warning, success and info notices.
* Display non-persistent WordPress dismissible notices.
* Display permanently dismissible notices.
* Handle permanent dismiss actions with AJAX requests.
* Display notices in the block editor when the notice is created with Gutenberg support enabled.

## Requirements

* PHP 7.4 or later.
* WordPress admin context.
* Composer is recommended for installation and autoloading.

## Installation via Composer

Install the package with [Composer](https://getcomposer.org/):

```bash
composer require wpdesk/wp-notice
```

Load Composer's [autoload](https://getcomposer.org/doc/01-basic-usage.md#autoloading) in your plugin:

```php
require_once 'vendor/autoload.php';
```

## Manual installation

If you prefer not to use Composer in the target plugin, download a built library release, for example the [latest library artifact](https://gitlab.com/wpdesk/wp-notice/-/jobs/artifacts/master/download?job=library), and include `init.php`:

```php
require_once '/path/to/wp-notice/init.php';
```

The `init.php` file loads `vendor/autoload.php`. If you install from a source checkout instead of a built artifact, run `composer install` first so the `vendor` directory exists.

## Getting Started

### Notices usage example

```php
$notice = wpdesk_wp_notice('Notice text goes here');

// Is equivalent to:
$notice = WPDeskWpNotice('Notice text goes here');

// Is equivalent to:
$notice = \WPDesk\Notice\Factory::notice('Notice text goes here');

// Is equivalent to:
$notice = new \WPDesk\Notice\Notice('Notice text goes here');
```

A notice object registers itself on `admin_notices` and `admin_footer`. Create it before WordPress runs `admin_notices`; for example, create it during your plugin bootstrap or on an earlier admin hook such as `admin_init`. The object removes its hooks after output, so the same object is rendered once.

### Notice types

The supported notice types are `info`, `error`, `warning` and `success`.

```php
wpdesk_wp_notice_info('Information message');
wpdesk_wp_notice_error('Error message');
wpdesk_wp_notice_warning('Warning message');
wpdesk_wp_notice_success('Success message');
```

The generic helper accepts the type, dismissible flag and priority:

```php
wpdesk_wp_notice('Notice text goes here', 'warning', true, 20);
```

The parameters are:

* `$noticeContent` - notice content.
* `$noticeType` - one of `info`, `error`, `warning`, `success`; defaults to `info`.
* `$dismissible` - adds the WordPress `is-dismissible` class; defaults to `false`.
* `$priority` - hook priority for displaying the notice; defaults to `10`.

Notice content is wrapped in a `<p>` tag unless it already starts with `<p>` or `<div>`. The final output is passed through `wp_kses_post()`.

### Custom attributes

When you need custom attributes or classes, use the returned object:

```php
$notice = wpdesk_wp_notice_warning('Notice text goes here');
$notice->addAttribute('id', 'my-notice');
$notice->addAttribute('class', 'my-custom-class');
```

The `class` attribute is appended to the generated WordPress notice classes.

## Permanently dismissible notices

### AJAX handler

To use permanently dismissible notices, initialize the AJAX handler before admin scripts are enqueued:

```php
wpdesk_init_wp_notice_ajax_handler();

// Is equivalent to:
( new \WPDesk\Notice\AjaxHandler() )->hooks();
```

You can pass a custom assets URL if your plugin bundles the library assets in a non-standard location:

```php
wpdesk_init_wp_notice_ajax_handler('https://example.com/wp-content/plugins/my-plugin/vendor/wpdesk/wp-notice/assets/');
```

The assets URL must point to the directory containing `js/notice.js`, `js/gutenberg.js` and `css/admin.css`.

### Displaying the permanently dismissible notices

Use the following code to display a permanently dismissible notice:

```php
wpdesk_permanent_dismissible_wp_notice( 'Notice text goes here', 'notice-name' );

// Is equivalent to
$notice = new \WPDesk\Notice\PermanentDismissibleNotice( 'Notice text goes here', 'notice-name' );
```

The helper accepts notice content, a stable notice name, notice type and priority:

```php
wpdesk_permanent_dismissible_wp_notice(
    'Notice text goes here',
    'notice-name',
    'warning',
    20
);
```

The notice name is used to build the WordPress option name `wpdesk_notice_dismiss_{noticeName}`. When the notice is dismissed, the AJAX handler validates the nonce, requires `current_user_can('edit_posts')`, stores the option value `1` and fires the `wpdesk_notice_dismissed_notice` action with the notice name and optional source.

```php
add_action('wpdesk_notice_dismissed_notice', function ($noticeName, $source) {
    // React to a permanently dismissed notice.
}, 10, 2);
```

Dismissal is stored in a WordPress option, so it is site-wide rather than per-user. To show a dismissed notice again, call `undoDismiss()` on the `PermanentDismissibleNotice` object.

### Gutenberg notices

When constructing notices directly, the last constructor argument enables block editor support:

```php
new \WPDesk\Notice\Notice(
    'Notice text goes here',
    'info',
    true,
    10,
    [],
    true
);
```

The AJAX handler always enqueues `js/notice.js`. It additionally enqueues `js/gutenberg.js` in the block editor and `css/admin.css` outside the block editor. Gutenberg-targeted notices receive the `wpdesk-notice-gutenberg` class.

## Development

Install dependencies:

```bash
composer install
```

Available Composer scripts:

```bash
composer phpcs
composer phpunit-unit
composer phpunit-unit-fast
composer phpunit-integration
composer phpunit-integration-fast
```

The test suites use the PHPUnit configuration files included in the repository.

## Project documentation

PHPDoc: https://wpdesk.gitlab.io/wp-notice/index.html
