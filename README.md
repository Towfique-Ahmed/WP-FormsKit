# WP FormsKit

A modern, lightweight WordPress form builder shipped in two editions:

| Edition | Folder | Distribution |
|---------|--------|--------------|
| **WP FormsKit** (Free) | [`wp-formskit/`](./wp-formskit) | WordPress.org plugin directory |
| **WP FormsKit Pro** (Pro) | [`wp-formskit-pro/`](./wp-formskit-pro) | Sold as a premium add-on |

The Pro edition is an **add-on** that requires the free plugin to be installed and
active. It keeps **every free feature** and layers advanced functionality on top,
so upgrading never removes anything.

---

## Why another form plugin?

See [`RESEARCH.md`](./RESEARCH.md) for the full competitive analysis of WPForms,
Gravity Forms, Fluent Forms, Ninja Forms and Forminator that shaped this feature
split. In short:

- **Free tier is genuinely useful** — real entry storage, email notifications and
  spam protection out of the box (the things Ninja Forms/WPForms tend to gate).
- **Clean extension API** — the free plugin exposes hooks (`formskit_field_types`,
  `formskit_after_submission`, `formskit_render_field`, …) so Pro adds features
  without patching core.
- **Performance first** — no bloated framework, assets load only on pages that
  render a form.

## Feature comparison

| Capability | Free | Pro |
|---|:---:|:---:|
| Drag-free field builder (CPT based) | ✅ | ✅ |
| Core fields (text, email, textarea, select, radio, checkbox, number, tel, url, date, name, hidden, HTML) | ✅ | ✅ |
| Shortcode `[formskit id="1"]` + Gutenberg block | ✅ | ✅ |
| Entry storage + CSV export | ✅ | ✅ |
| Email notifications with smart tags | ✅ | ✅ |
| Spam protection (honeypot + time-trap) | ✅ | ✅ |
| AJAX submission | ✅ | ✅ |
| Confirmation message / redirect | ✅ | ✅ |
| **Conditional logic** | — | ✅ |
| **Multi-step forms + progress bar** | — | ✅ |
| **File upload field** | — | ✅ |
| **Advanced fields** (rating, range/slider, section divider, password) | — | ✅ |
| **reCAPTCHA v3 / hCaptcha** | — | ✅ |
| **Integrations** (webhook, Mailchimp, Slack) | — | ✅ |
| **Stripe payments** | — | ✅ |
| License updates | — | ✅ |

## Install (development)

Copy (or symlink) both folders into `wp-content/plugins/` of a WordPress site:

```
wp-content/plugins/wp-formskit/
wp-content/plugins/wp-formskit-pro/
```

Activate **WP FormsKit** first, then **WP FormsKit Pro**.

## Requirements

- WordPress 6.0+
- PHP 7.4+
