# Flint Forms

A modern, lightweight WordPress form builder shipped in two editions:

| Edition | Folder | Distribution |
|---------|--------|--------------|
| **Flint Forms** (Free) | [`flint-forms/`](./flint-forms) | WordPress.org plugin directory |
| **Flint Forms Pro** (Pro) | [`flint-forms-pro/`](./flint-forms-pro) | Sold as a premium add-on |

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

## Download

**⬇️ One-click direct downloads** (pre-built, ready to upload to WordPress):

- **Flint Forms (Free):** [`downloads/flint-forms.zip`](https://github.com/Towfique-Ahmed/WP-FormsKit/raw/main/downloads/flint-forms.zip)
- **Flint Forms Pro:** [`downloads/flint-forms-pro.zip`](https://github.com/Towfique-Ahmed/WP-FormsKit/raw/main/downloads/flint-forms-pro.zip)

Clicking either link downloads the installable ZIP straight away — no build step needed.

You install a WordPress plugin as a **ZIP file**, not as a raw folder. If you'd
rather build fresh copies yourself, there are three other ways:

### 1. Build them yourself (recommended)

```bash
./build.sh          # builds both plugins into ./dist
./build.sh free     # only the free plugin
./build.sh pro      # only the pro plugin
```

This produces installable packages in `dist/`:

```
dist/flint-forms.zip           dist/flint-forms-1.0.0.zip
dist/flint-forms-pro.zip       dist/flint-forms-pro-1.0.0.zip
```

(Requires the `zip` command — preinstalled on macOS/Linux; on Windows use WSL or Git Bash.)

### 2. Download from GitHub Actions

Every push to `main` runs the **Build plugin packages** workflow. Open the latest
run under the repo's **Actions** tab and download the `flint-forms-plugins`
artifact — it contains both ZIPs.

### 3. Download from a Release

Tag a version and the ZIPs are attached to the GitHub Release automatically:

```bash
git tag v1.0.0 && git push origin v1.0.0
```

The release page will then list `flint-forms-1.0.0.zip` and `flint-forms-pro-1.0.0.zip`.

## Install

**In WordPress admin:**

1. Go to **Plugins → Add New → Upload Plugin**.
2. Upload `flint-forms.zip` and click **Install Now**, then **Activate**.
3. Repeat for `flint-forms-pro.zip` (activate it *after* the free plugin).
4. Build your first form under **Flint Forms → Add New**.

**For local development**, you can instead copy (or symlink) the folders straight
into `wp-content/plugins/`:

```
wp-content/plugins/flint-forms/
wp-content/plugins/flint-forms-pro/
```

Activate **Flint Forms** first, then **Flint Forms Pro**.

## Requirements

- WordPress 6.0+
- PHP 7.4+
