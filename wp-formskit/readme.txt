=== WP FormsKit — Contact Form & Form Builder ===
Contributors: wpformskit
Tags: contact form, form builder, forms, contact, custom form
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast, lightweight form builder. Create contact forms, collect entries, get email notifications and block spam — for free.

== Description ==

**WP FormsKit** is a modern, lightweight form builder for WordPress. Build a form
in minutes, drop it anywhere with a shortcode or block, and start collecting
submissions — with entry storage, email notifications and spam protection
included in the free version.

= Free features =

* Visual form builder with reorderable fields
* Core field types: single line text, paragraph, email, number, phone, URL, date, name, dropdown, multiple choice, checkboxes, hidden, HTML block
* Embed anywhere with the `[formskit id="1"]` shortcode or the block editor block
* **Entry storage** — every submission saved, viewable and exportable to CSV
* **Email notifications** with smart tags (`{all_fields}`, `{field:key}`, `{admin_email}` …)
* **Spam protection** — honeypot + time-trap, no third-party service required
* AJAX submission (no page reload) with graceful non-JS fallback
* Confirmation message or redirect
* Assets load only on pages that render a form — keeps your site fast
* Fully translatable

= Go further with WP FormsKit Pro =

WP FormsKit Pro keeps **all** the free features and adds:

* Conditional logic (show/hide fields based on answers)
* Multi-step forms with a progress bar
* File upload field
* Advanced fields (rating, range slider, password, section divider)
* reCAPTCHA v3 / hCaptcha
* Integrations: webhooks, Mailchimp, Slack
* Stripe payments

== Installation ==

1. Upload the `wp-formskit` folder to `/wp-content/plugins/`, or install through
   the Plugins screen in WordPress.
2. Activate **WP FormsKit** through the *Plugins* menu.
3. Go to **FormsKit → Add New** to build your first form.
4. Copy the shortcode shown in the *Embed* box into any post or page.

== Frequently Asked Questions ==

= Where are submissions stored? =

In a dedicated database table. View them under **FormsKit → Entries**, and export
to CSV at any time.

= Does it slow down my site? =

No. CSS and JavaScript are only enqueued on pages that actually render a form.

= How do I stop spam? =

A honeypot field and a submission time-trap are enabled by default. For
reCAPTCHA/hCaptcha, upgrade to WP FormsKit Pro.

== Screenshots ==

1. The drag-to-reorder form builder.
2. Entry management with CSV export.
3. A rendered form on the front end.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP FormsKit.
