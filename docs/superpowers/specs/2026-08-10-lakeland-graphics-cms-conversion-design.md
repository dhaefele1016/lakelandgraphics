# Lakeland Graphics — CMS Conversion Design

**Date:** 2026-08-10
**Author:** Doug Haefele
**Status:** Approved design — pending spec review

---

## 1. Overview & Goals

Convert the hand-built Lakeland Graphics marketing site (static HTML/CSS/JS + one PHP
form handler) into a setup the business owner can maintain herself, without paying a
developer for routine copy and photo changes.

**Primary goal:** The site owner (non-technical) can edit page copy and swap images on
her own, safely, from a browser.

**Secondary goals:**
- Close the site's SEO gaps and add AEO (answer-engine) structured data.
- Keep the existing custom design pixel-for-pixel.
- Reuse the existing, working PHP quote form rather than re-engineering it.
- Keep hosting simple and low-maintenance.

**Non-goals (YAGNI):**
- No redesign. The current design is the design.
- No blog / news system (not requested; nothing in the site implies one).
- No WordPress. Rejected: it would require rebuilding the design as a theme, add
  hosting/security/maintenance overhead, risk regressing the site's speed, and solve a
  problem the owner does not have (frequently adding new pages).
- No move to a JS framework (Astro/Next). Not needed for a 12-page brochure site and
  would break the PHP form.

---

## 2. Current State Assessment

**Source of truth:** the `dist/` build (in `Website Builds/dist/`), which is further along
than the original zip:
- Dev "tweaks panel" (React/Babel color-picker) already removed.
- `privacy.html` and `terms.html` present.
- Quote form really wired to `send-quote.php` (the zip's version only faked success).
- `.htaccess` present: forces HTTPS, caches static assets, denies access to `config.php`.

**Pages (12):** index (canonical home), about, process, industries, faq, fuel, decals,
fleet, specialty, quote, privacy, terms.

**Quote form — `send-quote.php`:** complete and production-grade. Honeypot + timing
anti-spam, validation, header-injection sanitizing, file attachments (12 MB/file, 25 MB
total, whitelisted to image/PDF/AI/EPS/SVG), emails `sales@lakelandgraphics.com` with
reply-to set to the customer, optional customer auto-confirmation. Config isolated in
`config.php`.

**What's missing (needs building):**
- **SEO:** zero meta descriptions, no Open Graph/Twitter tags, no canonical tags,
  no `sitemap.xml`, no `robots.txt` — on every page.
- **AEO / structured data:** no schema.org markup at all.
- **PHPMailer library:** `send-quote.php` requires `/vendor/PHPMailer/` — not yet present.
- **Mail config:** `config.php` currently points at SiteGround SMTP and has a
  `CHANGE_ME` password — must be repointed at the production host's mail server.

---

## 3. Architecture & Stack

**Hosting:** InMotion (PHP/Apache/cPanel) — where the site already lives. Retained
deliberately so the existing PHP form runs natively. Moving to a static host (e.g.
Vercel) was considered and rejected: it cannot execute PHP, which would force a rewrite
of a form that already works, for no meaningful benefit on a small brochure site.

**CMS / editing layer:** CloudCannon (git-based visual CMS). Host-agnostic; edits content
in a git repo and publishes the built output to InMotion via SFTP. Chosen over WordPress
(too heavy, ruled out above) and over form-based git CMSs (Decap/Tina) because CloudCannon
edits directly on the existing HTML with a visual click-to-edit experience, the best fit
for a non-technical owner and a hand-coded site.

**Form backend:** the existing `send-quote.php` on InMotion. No third-party form service
(Formspree) and no serverless function needed.

**Data flow:**

```
Owner edits in CloudCannon (browser)
      -> CloudCannon commits change to GitHub repo
      -> CloudCannon publishes built site to InMotion via SFTP
      -> Live site updates (~1 min)

Visitor submits quote form
      -> POST to send-quote.php on InMotion
      -> PHPMailer sends via domain SMTP to sales@lakelandgraphics.com (+ auto-reply)
```

**Account/ownership plan:**
- GitHub repo: under Doug's personal GitHub account. CloudCannon connected via GitHub OAuth.
- CloudCannon account owner/billing: client-owned — Melissa enters her card before the
  trial expires; Doug invited as admin, so the client retains her site on handoff.
- The owner/editor (Melissa) logs into CloudCannon with email + password only — never GitHub.

---

## 4. Work Breakdown

### 4.1 Repository & source of truth
- Create a git repository from the `dist/` build (the canonical source).
- Include HTML/CSS/JS, the PHP files (`send-quote.php`, `config.php`), and `.htaccess`.
- `config.php` (real credentials) must **not** be committed — add to `.gitignore`;
  commit a `config.example.php` template instead.

### 4.2 Quote form finalization (owner/host tasks + dev wiring)
- Create a `website@lakelandgraphics.com` mailbox in InMotion cPanel.
- Update `config.php`: real SMTP host (InMotion), port/secure, mailbox password.
- Upload the PHPMailer library to `/vendor/PHPMailer/`.
- Confirm `.htaccess` `config.php` protection is active on InMotion.
- Test end-to-end: submit with a photo; confirm delivery to sales@ + customer auto-reply.

### 4.3 SEO / AEO implementation (all pages)
Per-page:
- Unique meta description.
- Open Graph + Twitter card tags (title, description, image).
- Canonical tag.

Structured data (schema.org, JSON-LD):
- **`LocalBusiness`** (site-wide, e.g. in footer or home): 
  Lakeland Graphics · 9444 Deerwood Lane N, Maple Grove, MN 55369 ·
  Mon–Fri 8:00 AM–4:30 PM · sameAs linkedin.com/company/lakeland-graphics-inc. ·
  phone 800.495.8107 · email sales@lakelandgraphics.com.
- **`FAQPage`** on `faq.html` (highest-value AEO play — eligibility for AI Overviews and
  rich results).
- **`Service`** on the four category pages (fuel, decals, fleet, specialty).

Crawl/index:
- `sitemap.xml` covering all canonical pages.
- `robots.txt` referencing the sitemap.

### 4.4 CloudCannon editability
- Tag editable regions across pages (headings, body copy, images, testimonials, FAQ Q&As)
  so the owner gets visual click-to-edit; design/layout/structure stay locked (only tagged
  regions are editable — she cannot break the layout).
- Expose per-page SEO fields (title + meta description) as simple labeled inputs she can
  optionally edit; structured data stays baked in and hidden from her.
- Treat PHP files and `/vendor/` as pass-through so publishes never overwrite them.
- Connect CloudCannon to the repo; configure SFTP publish to InMotion.
- Invite the owner as an editor; set up her login.

### 4.5 Handoff
- Write a one-page "How to edit your site" guide.
- Short live walkthrough with the owner.

---

## 5. Client (Editor) Experience

**Skill required: none beyond email + Google-Docs-level editing.** No HTML, no code, no
FTP, no git — she works entirely in a browser.

**Editing a page:**
1. Go to CloudCannon, log in with email + password.
2. Pick a page from the list (Home, About, FAQ, …).
3. The real page appears as it looks live. Click a headline or paragraph and type; a
   small toolbar offers bold, italic, and links.
4. Swap a photo by clicking the image and choosing a new file from her computer.
5. Click Save, then Publish. Live in ~1 minute.

**Guardrails (a feature):** only regions tagged as editable can be changed. The design,
layout, and structure are locked, so she cannot drag things out of place, delete sections,
or break the page. This is a deliberate advantage over WordPress, where a non-technical
owner can more easily break a layout or the site via plugins/updates.

**Routes through the developer by design:** adding brand-new pages with new layouts, or
structural changes. Editing existing copy and images — all she asked for — she does
herself. (Duplicate-able page templates can be added later if she ever wants no-code new
pages.)

**Learning curve:** minutes, not weeks, plus the one-page guide and a short walkthrough.

**Cost:** CloudCannon ~$45/mo — the price of self-sufficiency vs. paying per edit.

---

## 6. What the Owner / Doug Must Provide (account access I can't do)
- InMotion cPanel access to create the `website@` mailbox and confirm SMTP settings.
- CloudCannon account + SFTP credentials for InMotion (for the publish connection).
- Melissa to enter her card in CloudCannon before the trial expires (billing owner).

## 7. Success Criteria
- Owner independently edits copy and swaps an image on a page and publishes it live,
  unaided, in a single sitting.
- Quote form delivers a real submission (with a photo attachment) to sales@ plus a
  customer auto-reply.
- Every page has a unique title + meta description; LocalBusiness, FAQPage, and Service
  schema validate in Google's Rich Results Test; sitemap.xml and robots.txt are live.
- Design is visually unchanged from the current `dist/` build.
- Site remains on InMotion with the PHP form working.

## 8. Resolved Decisions
- CloudCannon billing ownership: **client-owned** (Melissa's card), Doug as admin.
- OG/share image: **default to the hero image per page, falling back to the logo** where
  no obvious page image exists.
- Blog / new-page templates: **out of scope for now** (can be added later).
