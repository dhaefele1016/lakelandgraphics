# Lakeland Graphics CMS Conversion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing hand-built Lakeland Graphics site owner-editable via CloudCannon, add full SEO/AEO markup, and finish wiring the existing PHP quote form — all while keeping the current design and InMotion hosting.

**Architecture:** Plain static HTML/CSS/JS (no build step) + one PHP form handler, hosted on InMotion (PHP/Apache). CloudCannon (git-based visual CMS) edits the HTML directly and publishes to InMotion via SFTP. The existing `send-quote.php` handles form submissions with PHPMailer over domain SMTP.

**Tech Stack:** HTML5, CSS, vanilla JS, PHP 8 + PHPMailer, JSON-LD (schema.org), CloudCannon, InMotion (cPanel/Apache), Git/GitHub.

---

## Conventions & Notes

- **This is not a classic unit-tested codebase.** "Verification" here means: HTML/schema validators, `curl`, browser checks, and Google's Rich Results Test — not `pytest`. Each task still ends with an explicit verification step and a commit.
- **Task tags:** `[DEV]` = code/content work an engineer or Claude can do locally. `[ACCOUNT]` = requires InMotion/CloudCannon credentials and must be done by Doug or Melissa (Claude must not enter passwords or credentials).
- **Canonical base URL:** `https://lakelandgraphics.com` is assumed throughout. **Before Phase 2, confirm whether the live site resolves to `www.` or the bare domain, and whether a trailing filename is kept (e.g. `/faq.html`).** If it resolves to `www.`, do a global find/replace of `https://lakelandgraphics.com` → `https://www.lakelandgraphics.com` across all inserted tags. Pick one and use it consistently.
- **Source of truth:** the `dist/` build under `Website Builds/`. All paths below are relative to the new repo root created in Task 1.1 (seeded from `dist/`).
- **Repo root target:** `Website Builds/lakeland-graphics-site/` (referred to below as the repo root).
- **12 pages:** `index` (canonical home), `about`, `process`, `industries`, `faq`, `fuel`, `decals`, `fleet`, `specialty`, `quote`, `privacy`, `terms`.

---

## File Structure

**Created in this plan:**
- `robots.txt` — crawl directives + sitemap pointer
- `sitemap.xml` — all 12 canonical URLs
- `config.example.php` — SMTP config template (committed; real `config.php` is gitignored)
- `.gitignore` — excludes `config.php`, OS cruft
- `vendor/PHPMailer/` — PHPMailer library (PHPMailer.php, SMTP.php, Exception.php)
- `cloudcannon.config.yml` — CloudCannon editable-region + input config
- `docs/EDITING-GUIDE.md` — Melissa's one-page "how to edit" guide

**Modified in this plan:**
- All 12 `*.html` — add `<head>` SEO/OG/canonical tags; add JSON-LD where specified; add CloudCannon `editable` classes to content regions
- `config.php` — repoint SMTP from SiteGround to InMotion (ACCOUNT task, on server only)

---

## Phase 1 — Repository & Workspace Setup

### Task 1.1: Create the site git repository `[DEV]`

**Files:**
- Create: repo root `Website Builds/lakeland-graphics-site/` (seeded from `dist/`)
- Create: `.gitignore`

- [ ] **Step 1: Seed the repo from the canonical build**

```bash
cd "/Users/doughaefele/Desktop/Claude Projects/LG Information/Website Builds"
mkdir -p lakeland-graphics-site
cp -R dist/. lakeland-graphics-site/
cd lakeland-graphics-site
rm -f Icon$'\r' Icon   # remove macOS Icon cruft if present
```

- [ ] **Step 2: Add `.gitignore`**

Create `.gitignore`:

```gitignore
# Real mail credentials — never commit
config.php

# macOS
.DS_Store
Icon?
._*

# Editor
.vscode/
```

- [ ] **Step 3: Move the design docs into the repo**

```bash
mkdir -p docs/superpowers
cp -R "../docs/superpowers/specs" docs/superpowers/specs
cp -R "../docs/superpowers/plans" docs/superpowers/plans
```

- [ ] **Step 4: Initialize git and make the first commit**

```bash
git init
git add -A
git commit -m "chore: seed Lakeland Graphics site repo from dist build + design docs"
```

- [ ] **Step 5: Verify**

Run: `git log --oneline && ls`
Expected: one commit; site HTML files, `assets/`, `docs/`, `.gitignore` all present. `config.php` is present on disk but **not** tracked — confirm with `git status --ignored | grep config.php`.

---

### Task 1.2: Push the repo to GitHub `[ACCOUNT]`

- [ ] **Step 1:** Create a new **private** repo on Doug's personal GitHub (e.g. `lakeland-graphics-site`). Do not initialize it with a README (the local repo already has content).

- [ ] **Step 2:** Connect and push:

```bash
git remote add origin git@github.com:<doug-username>/lakeland-graphics-site.git
git branch -M main
git push -u origin main
```

- [ ] **Step 3: Verify** — the repo appears on GitHub with all files and **no `config.php`** in the file list.

---

## Phase 2 — SEO / AEO Markup (no external accounts needed)

> Do this phase first among the "real work" — it's pure code and unblocks nothing else.

### Task 2.1: Add per-page meta + Open Graph + canonical tags `[DEV]`

**Files:** Modify all 12 `*.html`. Insert the block **immediately after the last `<link rel="stylesheet" ...>` line** in each page's `<head>`.

Per-page values (title stays as-is; use these descriptions and images):

| Page | Meta description | OG image |
|---|---|---|
| index | Lakeland Graphics prints custom decals, overlays, and labels engineered to outlast the sun — for fuel pumps, fleets, factories, and storefronts. Durable graphics since 1987. | `assets/hero-print.jpg` |
| about | Since 1987, Lakeland Graphics has built durable custom graphics for the harshest environments. Woman-owned, 100% made in the USA. Learn our story. | `assets/hero-print.jpg` |
| process | From your first photo to final proof and production, see how Lakeland Graphics makes ordering durable custom graphics simple and fast. | `assets/color-proofing.jpg` |
| industries | Durable graphics for petroleum & retail, industrial & manufacturing, fleet & transport, and government/municipal. See the industries Lakeland Graphics serves. | `assets/hero-print.jpg` |
| faq | Lead times, durability, materials, design help, and minimums — answers to the most common questions about ordering custom graphics from Lakeland Graphics. | `assets/hero-print.jpg` |
| fuel | Pump overlays, dispenser graphics, canopy fascia, and station branding engineered to survive retail's harshest UV. Fuel & c-store graphics from Lakeland Graphics. | `assets/fuel-graphics.jpg` |
| decals | Industrial labels, safety and warning decals, product ID, reflective and clear films — custom decals and labels built to work as hard as your equipment. | `assets/decals-labels.jpg` |
| fleet | High-visibility vehicle graphics, door decals, wall and floor graphics, and building signage — consistent fleet and environmental graphics for multi-location brands. | `assets/fleet-graphics.jpg` |
| specialty | Domed decals, vinyl lettering, magnets, and short-run custom jobs. If it's printable and it has to last, Lakeland Graphics can make it. | `assets/window-graphics.jpg` |
| quote | Request a quote from Lakeland Graphics. Upload a photo of your project and we'll send a quote and a recommendation within 1–2 business days. | `assets/hero-print.jpg` |
| privacy | Lakeland Graphics privacy policy — how we collect, use, and protect your information. | `assets/hero-print.jpg` |
| terms | Lakeland Graphics terms of service. | `assets/hero-print.jpg` |

- [ ] **Step 1: Insert the head block (worked example: `faq.html`)**

After `<link rel="stylesheet" href="assets/pages.css">` in `faq.html`, insert:

```html
<meta name="description" content="Lead times, durability, materials, design help, and minimums — answers to the most common questions about ordering custom graphics from Lakeland Graphics.">
<link rel="canonical" href="https://lakelandgraphics.com/faq.html">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Lakeland Graphics">
<meta property="og:title" content="FAQ — Lakeland Graphics">
<meta property="og:description" content="Lead times, durability, materials, design help, and minimums — answers to the most common questions about ordering custom graphics from Lakeland Graphics.">
<meta property="og:url" content="https://lakelandgraphics.com/faq.html">
<meta property="og:image" content="https://lakelandgraphics.com/assets/hero-print.jpg">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="FAQ — Lakeland Graphics">
<meta name="twitter:description" content="Lead times, durability, materials, design help, and minimums — answers to the most common questions about ordering custom graphics from Lakeland Graphics.">
<meta name="twitter:image" content="https://lakelandgraphics.com/assets/hero-print.jpg">
```

- [ ] **Step 2:** Repeat for the other 11 pages, substituting each page's `<title>` text, description from the table, canonical `.../<page>.html`, `og:url`, and OG/twitter image from the table.

- [ ] **Step 3: Verify** — grep each page has exactly one description and one canonical:

Run: `for f in *.html; do echo "$f: desc=$(grep -c 'name=\"description\"' $f) canon=$(grep -c 'rel=\"canonical\"' $f)"; done`
Expected: every page prints `desc=1 canon=1`.

- [ ] **Step 4: Commit**

```bash
git add *.html && git commit -m "feat(seo): add meta descriptions, canonical, and Open Graph/Twitter tags to all pages"
```

---

### Task 2.2: Add `LocalBusiness` JSON-LD to the homepage `[DEV]`

**Files:** Modify `index.html`.

- [ ] **Step 1: Insert before `</head>` in `index.html`**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "@id": "https://lakelandgraphics.com/#business",
  "name": "Lakeland Graphics",
  "url": "https://lakelandgraphics.com/",
  "image": "https://lakelandgraphics.com/assets/hero-print.jpg",
  "logo": "https://lakelandgraphics.com/assets/logo.svg",
  "telephone": "+1-800-495-8107",
  "email": "sales@lakelandgraphics.com",
  "foundingDate": "1987",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "9444 Deerwood Lane N",
    "addressLocality": "Maple Grove",
    "addressRegion": "MN",
    "postalCode": "55369",
    "addressCountry": "US"
  },
  "openingHoursSpecification": [{
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
    "opens": "08:00",
    "closes": "16:30"
  }],
  "sameAs": ["https://www.linkedin.com/company/lakeland-graphics-inc./"]
}
</script>
```

- [ ] **Step 2: Verify** — paste the JSON into <https://validator.schema.org/> (or Google Rich Results Test). Expected: `LocalBusiness` detected, 0 errors.

- [ ] **Step 3: Commit**

```bash
git add index.html && git commit -m "feat(seo): add LocalBusiness JSON-LD to homepage"
```

---

### Task 2.3: Add `FAQPage` JSON-LD to `faq.html` `[DEV]`

**Files:** Modify `faq.html`. The content below mirrors the 7 existing Q&As verbatim (keep in sync if the FAQ copy changes).

- [ ] **Step 1: Insert before `</head>` in `faq.html`**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {"@type":"Question","name":"What's your typical lead time?","acceptedAnswer":{"@type":"Answer","text":"Orders ship within 5–10 business days. In-stock items ship within 24 hours. Have a firm deadline? Let us know when requesting a quote."}},
    {"@type":"Question","name":"How long does shipping take?","acceptedAnswer":{"@type":"Answer","text":"Transit time depends on your location and the carrier — we'll let you know what to expect when your order is ready."}},
    {"@type":"Question","name":"How durable are your graphics, really?","acceptedAnswer":{"@type":"Answer","text":"Durability is the whole point here. Most overlays fade within a few years — the reds, oranges, and yellows go first. Ours hold their color years longer. Nearly four decades of printing for high-UV environments — fuel pumps, fleets, factory floors, storefronts — is exactly why."}},
    {"@type":"Question","name":"What materials do you print on?","acceptedAnswer":{"@type":"Answer","text":"We match the material to where the graphic lives — the surface it goes on, the wear it'll take, and how long it needs to last. That includes durable outdoor films, reflective and clear materials, and specialty options for demanding applications."}},
    {"@type":"Question","name":"Do you offer design help, or do I need to supply artwork?","acceptedAnswer":{"@type":"Answer","text":"We have an in-house design team that can create, adjust, or prepare your artwork for production. You're also welcome to provide your own print-ready files. Standard design support is included with most projects."}},
    {"@type":"Question","name":"Is there a minimum order?","acceptedAnswer":{"@type":"Answer","text":"No formal minimum. Short runs are a specialty of ours — a single replacement overlay is just as welcome as a full fleet rollout. If it's printable and it has to last, we'll quote it."}},
    {"@type":"Question","name":"Do you work with customers outside the petroleum industry?","acceptedAnswer":{"@type":"Answer","text":"Absolutely. Petroleum and convenience stores are where we earned our reputation, but that same durability now shows up on fleets, factory floors, retail interiors, and municipal and government signage."}}
  ]
}
</script>
```

- [ ] **Step 2: Verify** — Rich Results Test on `faq.html`. Expected: `FAQ` detected, all 7 Q&As, 0 errors.

- [ ] **Step 3: Commit**

```bash
git add faq.html && git commit -m "feat(seo): add FAQPage JSON-LD to FAQ page"
```

---

### Task 2.4: Add `Service` JSON-LD to the four category pages `[DEV]`

**Files:** Modify `fuel.html`, `decals.html`, `fleet.html`, `specialty.html`.

- [ ] **Step 1: Insert before `</head>` on each page**, substituting `serviceType` and `description`:

`fuel.html`:
```html
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Service","serviceType":"Fuel & Convenience Store Graphics","provider":{"@type":"LocalBusiness","name":"Lakeland Graphics","@id":"https://lakelandgraphics.com/#business"},"areaServed":"US","description":"Pump overlays, dispenser graphics, canopy fascia, and station branding engineered to survive the harshest UV in retail."}
</script>
```

`decals.html`: same block with
`"serviceType":"Custom Decals & Labels"`,
`"description":"Industrial labels, safety and warning decals, product ID, regulation decals, and reflective and clear films built for demanding environments."`

`fleet.html`: same block with
`"serviceType":"Fleet, Vehicle & Environmental Graphics"`,
`"description":"High-visibility vehicle graphics, door decals, wall and floor graphics, and building signage for multi-location brand consistency."`

`specialty.html`: same block with
`"serviceType":"Custom & Specialty Work"`,
`"description":"Domed decals, vinyl lettering, magnets, and short-run custom jobs — if it's printable and it has to last, we can make it."`

- [ ] **Step 2: Verify** — Rich Results Test on each; `Service` detected, 0 errors.

- [ ] **Step 3: Commit**

```bash
git add fuel.html decals.html fleet.html specialty.html && git commit -m "feat(seo): add Service JSON-LD to category pages"
```

---

### Task 2.5: Add `sitemap.xml` and `robots.txt` `[DEV]`

**Files:** Create `sitemap.xml`, `robots.txt` at repo root.

- [ ] **Step 1: Create `robots.txt`**

```text
User-agent: *
Allow: /

Sitemap: https://lakelandgraphics.com/sitemap.xml
```

- [ ] **Step 2: Create `sitemap.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://lakelandgraphics.com/</loc></url>
  <url><loc>https://lakelandgraphics.com/about.html</loc></url>
  <url><loc>https://lakelandgraphics.com/process.html</loc></url>
  <url><loc>https://lakelandgraphics.com/industries.html</loc></url>
  <url><loc>https://lakelandgraphics.com/fuel.html</loc></url>
  <url><loc>https://lakelandgraphics.com/decals.html</loc></url>
  <url><loc>https://lakelandgraphics.com/fleet.html</loc></url>
  <url><loc>https://lakelandgraphics.com/specialty.html</loc></url>
  <url><loc>https://lakelandgraphics.com/faq.html</loc></url>
  <url><loc>https://lakelandgraphics.com/quote.html</loc></url>
  <url><loc>https://lakelandgraphics.com/privacy.html</loc></url>
  <url><loc>https://lakelandgraphics.com/terms.html</loc></url>
</urlset>
```

- [ ] **Step 3: Verify** — `xmllint --noout sitemap.xml` returns no error (or open in a browser; it renders as a tree). Confirm 12 URLs.

- [ ] **Step 4: Commit**

```bash
git add sitemap.xml robots.txt && git commit -m "feat(seo): add sitemap.xml and robots.txt"
```

---

## Phase 3 — Quote Form Provisioning & Test

### Task 3.1: Add PHPMailer to the repo `[DEV]`

**Files:** Create `vendor/PHPMailer/PHPMailer.php`, `SMTP.php`, `Exception.php`.

- [ ] **Step 1:** Download PHPMailer (latest 6.x) and copy the three required class files into `vendor/PHPMailer/`:

```bash
cd /tmp && curl -L -o phpmailer.tar.gz https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.tar.gz
tar xzf phpmailer.tar.gz
mkdir -p "<repo-root>/vendor/PHPMailer"
cp PHPMailer-6.9.1/src/PHPMailer.php PHPMailer-6.9.1/src/SMTP.php PHPMailer-6.9.1/src/Exception.php "<repo-root>/vendor/PHPMailer/"
```

- [ ] **Step 2: Verify** — `send-quote.php` `require`s match the files present:

Run: `ls vendor/PHPMailer/` → expect `Exception.php  PHPMailer.php  SMTP.php`. Confirm `send-quote.php` lines 32–34 reference exactly these.

- [ ] **Step 3: Commit**

```bash
git add vendor/PHPMailer && git commit -m "chore: vendor PHPMailer 6.9.1 for quote form"
```

---

### Task 3.2: Add `config.example.php` template `[DEV]`

**Files:** Create `config.example.php`.

- [ ] **Step 1:** Copy the existing `config.php` to `config.example.php`, scrub secrets, and point comments at InMotion:

```php
<?php
/**
 * Lakeland Graphics — mail configuration TEMPLATE.
 * Copy to config.php on the server and fill in real values.
 * config.php is gitignored and must never be committed.
 */
return [
    // --- InMotion SMTP (cPanel > Email Accounts > Connect Devices) ---
    'smtp_host'   => 'mail.lakelandgraphics.com', // confirm exact host in cPanel
    'smtp_port'   => 465,                          // 465 with 'ssl', or 587 with 'tls'
    'smtp_secure' => 'ssl',
    'smtp_user'   => 'website@lakelandgraphics.com',
    'smtp_pass'   => 'CHANGE_ME',

    'from_email'  => 'website@lakelandgraphics.com', // must be a real mailbox on the domain
    'from_name'   => 'Lakeland Graphics Website',
    'to_email'    => 'sales@lakelandgraphics.com',
    'to_name'     => 'Lakeland Graphics Sales',
    'cc_emails'   => [],
    'send_confirmation' => true,
];
```

- [ ] **Step 2: Verify** — `git status` shows `config.example.php` staged and `config.php` still ignored.

- [ ] **Step 3: Commit**

```bash
git add config.example.php && git commit -m "docs: add config.example.php template for mail settings"
```

---

### Task 3.3: Provision the mailbox and live `config.php` on InMotion `[ACCOUNT]`

> Requires InMotion cPanel access. Claude must not enter the password — Doug performs these steps.

- [ ] **Step 1:** In cPanel → **Email Accounts**, create `website@lakelandgraphics.com` with a strong password.
- [ ] **Step 2:** In that mailbox's **Connect Devices** screen, note the exact outgoing SMTP host, port, and security (SSL 465 or TLS 587).
- [ ] **Step 3:** On the server, copy `config.example.php` → `config.php` and fill in the real `smtp_host`, `smtp_pass`, and confirmed port/secure values.
- [ ] **Step 4:** Confirm `.htaccess` `config.php` protection is active — visiting `https://lakelandgraphics.com/config.php` must **not** return its contents (should 403).
- [ ] **Step 5: Verify** — `curl -I https://lakelandgraphics.com/config.php` → expect `403 Forbidden`.

---

### Task 3.4: Deploy the current build to InMotion and test the form end-to-end `[ACCOUNT]`

> Interim manual deploy (via cPanel File Manager or SFTP) to validate the form before CloudCannon is connected. Automated publishing comes in Phase 4.

- [ ] **Step 1:** Upload the repo contents (HTML, `assets/`, `vendor/`, `.htaccess`, `send-quote.php`, `config.php`) to the InMotion web root.
- [ ] **Step 2:** In a browser, open `https://lakelandgraphics.com/quote.html`, fill the form, **attach a test photo**, and submit.
- [ ] **Step 3: Verify:**
  - The page shows its success state.
  - `sales@lakelandgraphics.com` receives the request email **with the photo attached** and reply-to set to the test address.
  - The test address receives the auto-confirmation email.
  - If sending fails, check the server error log for `Lakeland quote form SMTP error:` and re-check SMTP host/port/credentials.

---

## Phase 4 — CloudCannon Integration

### Task 4.1: Mark editable regions and add CloudCannon config `[DEV]`

**Files:** Create `cloudcannon.config.yml`; modify all 12 `*.html` (add `editable` classes to content regions).

CloudCannon edits plain HTML via **editable regions**: elements given the `editable` class (rich text) become click-to-edit in the Visual Editor; the config also exposes per-page front-matter-free inputs. Follow current CloudCannon "HTML / no SSG" docs for exact class/attribute names, using the structure below.

- [ ] **Step 1: Create `cloudcannon.config.yml`**

```yaml
# CloudCannon configuration for the Lakeland Graphics static HTML site
# Docs: https://cloudcannon.com/documentation/
source: /
collections_config:
  pages:
    path: ""
    glob:
      - "*.html"
    name: Pages
    icon: description
_editables:
  content:
    # Toolbar offered on editable rich-text regions
    bold: true
    italic: true
    link: true
    undo: true
    redo: true
    removeformat: true
_inputs:
  # Friendly labels for the per-page SEO fields exposed to editors
  description:
    type: text
    comment: The search-result / social description for this page (~155 chars).
```

- [ ] **Step 2: Tag content regions (worked example: `index.html` hero)**

Add the `editable` class to the text elements the owner should edit. Example — the hero copy in `index.html`:

```html
<h1 class="h1 reveal d1 editable">Graphics that <span class="accentword">outlast</span> the sun.</h1>
<p class="lead reveal d2 editable">Custom decals, overlays, and labels engineered for the harshest environments — fuel pumps, fleets, factories, and storefronts.</p>
```

Apply the same to: headings (`h1`/`h2`/`h3`), body/lead paragraphs (`.body`, `.lead`), tile descriptions, pillar text, FAQ questions/answers, testimonial quotes. **Do not** add `editable` to structural wrappers, nav, buttons, or the footer legal line — only to the copy blocks the owner should change.

- [ ] **Step 3: Make key images editable** — for each hero/tile/content `<img>` the owner may swap, add the `editable` class so CloudCannon offers image replacement, e.g.:

```html
<img class="editable" src="assets/hero-print.jpg" alt="Large-format printer producing vivid, full-color graphics">
```

- [ ] **Step 4: Verify** — grep confirms editable regions exist and structural elements were not touched:

Run: `grep -rc 'editable' *.html`
Expected: each content page reports a non-zero count; spot-check that no `<nav>` or footer legal element got the class.

- [ ] **Step 5: Commit**

```bash
git add cloudcannon.config.yml *.html && git commit -m "feat(cms): add CloudCannon config and mark editable content regions"
git push
```

---

### Task 4.2: Connect CloudCannon to the repo `[ACCOUNT]`

> Requires the CloudCannon account (Melissa's billing) + Doug as admin.

- [ ] **Step 1:** Sign in to CloudCannon **with GitHub** (Doug), create a site, and connect the `lakeland-graphics-site` GitHub repo on the `main` branch.
- [ ] **Step 2:** Confirm CloudCannon detects it as a plain HTML site (no SSG build) and the Visual Editor renders `index.html`.
- [ ] **Step 3:** Open a page in the Visual Editor and confirm the tagged regions are click-to-edit and structural areas are locked.
- [ ] **Step 4: Verify** — make a trivial text edit in CloudCannon, save, and confirm a commit appears on GitHub `main`.

---

### Task 4.3: Configure SFTP publishing to InMotion `[ACCOUNT]`

> Requires InMotion SFTP credentials. Ensures publishes never clobber `config.php`/`vendor/`.

- [ ] **Step 1:** In InMotion cPanel, create/confirm an SFTP user scoped to the web root.
- [ ] **Step 2:** In CloudCannon, configure **SFTP deployment** (host, user, key/password, target web-root path) so building/publishing uploads the site output to InMotion.
- [ ] **Step 3:** Ensure the deploy **excludes** `config.php` (server-only) so it is never overwritten. `vendor/` and `send-quote.php` are in the repo and deploy normally.
- [ ] **Step 4: Verify** — publish from CloudCannon; confirm the change is live on `https://lakelandgraphics.com` within ~1–2 minutes and the quote form still sends (i.e. `config.php` intact).

---

### Task 4.4: Invite Melissa and confirm billing `[ACCOUNT]`

- [ ] **Step 1:** Invite `melissa@...` to the CloudCannon site as an **editor**.
- [ ] **Step 2:** Melissa enters her card in CloudCannon **before the trial expires** (billing owner).
- [ ] **Step 3: Verify** — Melissa logs in with email/password (no GitHub), opens a page, and sees the Visual Editor.

---

## Phase 5 — Handoff

### Task 5.1: Write Melissa's editing guide `[DEV]`

**Files:** Create `docs/EDITING-GUIDE.md`.

- [ ] **Step 1: Create `docs/EDITING-GUIDE.md`**

```markdown
# How to Edit Your Website

## Logging in
1. Go to **app.cloudcannon.com** and sign in with your email and password.
2. Click the **Lakeland Graphics** site.

## Editing text
1. Click a page in the list (Home, About, FAQ, …).
2. The page appears just like it looks live. Click on any headline or paragraph
   you want to change and type. A small toolbar gives you **bold**, *italic*, and links.
3. Click **Save**.

## Changing a photo
1. Click the image you want to replace.
2. Choose a new picture from your computer.
3. Click **Save**.

## Publishing (making changes live)
1. After saving, click **Publish**.
2. Your changes appear on the real website within about a minute.

## What you can and can't change
- ✅ Wording, paragraphs, headlines, and photos inside the editable areas.
- 🔒 The layout, design, menus, and footer are locked so nothing can break.
- Need a brand-new page or a layout change? Contact Doug.

## Questions
Text or email Doug and he'll walk you through it.
```

- [ ] **Step 2: Commit**

```bash
git add docs/EDITING-GUIDE.md && git commit -m "docs: add editing guide for the site owner" && git push
```

---

### Task 5.2: Final verification pass `[ACCOUNT]` + `[DEV]`

- [ ] **Step 1 (SEO):** Run the live homepage and FAQ page through Google's **Rich Results Test**. Expected: `LocalBusiness` and `FAQPage` valid, 0 errors. Confirm `https://lakelandgraphics.com/sitemap.xml` and `/robots.txt` load.
- [ ] **Step 2 (Search Console):** Submit `sitemap.xml` in Google Search Console for the property.
- [ ] **Step 3 (Form):** Submit one more real quote with an attachment; confirm delivery + auto-reply.
- [ ] **Step 4 (Owner):** Melissa independently edits a page's copy, swaps an image, and publishes — unaided — and the change goes live. This satisfies the spec's primary success criterion.
- [ ] **Step 5 (Design):** Visually compare the live pages against the pre-conversion `dist/` build — confirm no visual regressions.

---

## Self-Review Notes (coverage check vs. spec)

- Spec §4.1 repo/source-of-truth → Tasks 1.1, 1.2, 3.2 (gitignore/config). ✅
- Spec §4.2 form finalization → Tasks 3.1–3.4. ✅
- Spec §4.3 SEO/AEO (meta, OG, LocalBusiness, FAQPage, Service, sitemap, robots) → Tasks 2.1–2.5. ✅
- Spec §4.4 CloudCannon editability + SFTP + editor invite → Tasks 4.1–4.4. ✅
- Spec §4.5 handoff guide → Task 5.1. ✅
- Spec §7 success criteria → Task 5.2. ✅
- Resolved decisions (client billing, OG defaults hero→logo) → reflected in Tasks 2.1 table + 4.4. ✅
