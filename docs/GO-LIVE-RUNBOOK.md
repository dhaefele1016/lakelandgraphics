# Lakeland Graphics — Go-Live Runbook & Project State

Quick-start context for resuming work (e.g. the cutover) in a fresh session.

## Architecture (as built)

- **Repo:** `github.com/dhaefele1016/lakelandgraphics` (private, branch `main`).
  Local: `…/LG Information/Website Builds/lakeland-graphics-site`.
- **Hosting:** InMotion, cPanel (`secure292`, home `/home/lakela11`). Primary
  domain `lakelandgraphics.com`, document root `public_html`.
- **CMS:** CloudCannon — SSG = **Static**, Output Path = **`.`**, **Hosted Mode**,
  Building **unlocked**; pages open in the **Visual Editor** (`_enabled_editors: visual`
  in `cloudcannon.config.yml`). Owner (Melissa) edits → CloudCannon commits to
  `main` → GitHub Action deploys.
- **Deploy:** `.github/workflows/deploy.yml` → **FTPS** to `ftp.lakelandgraphics.com`
  (port 21), FTP user `deploy@lakelandgraphics.com` scoped to `public_html`.
  Secrets in GitHub repo settings: `FTP_USERNAME`, `FTP_PASSWORD`.
  **Currently deploys to `server-dir: ./staging/`** (i.e. `public_html/staging`).
- **Staging:** `staging.lakelandgraphics.com` (subdomain, docroot `public_html/staging`) —
  the full new site is live here for review.
- **Quote form email:** sends via **Brevo** SMTP (`smtp-relay.brevo.com:587`, TLS).
  Server-only `config.php` (gitignored; a `config.example.php` template is in the repo).
  From/To = `sales@lakelandgraphics.com`. The domain is **authenticated in Brevo**
  (DKIM), and InMotion's outbound IP (`104.244.122.87`) is **authorized in Brevo**
  (Security → Authorized IPs). Mail lands in the real `sales@` inbox on **Microsoft 365**
  (domain MX → Outlook).
- **Current live site:** the **new static site** in `public_html` (cut over 2026-08-14).
  Old WordPress moved to `/home/lakela11/old-wordpress/` (backed up first, not deleted).
  Kept in `public_html`: `staging`, `.well-known`, `cgi-bin`, `config.php`.

## Status: DONE (on staging)

Full site + SEO/AEO (meta, OG, canonical, LocalBusiness/FAQPage/Service schema,
sitemap, robots); CloudCannon on-page editing (text, images, headline accent via
**Bold**, FAQ, privacy/terms fully editable); auto-deploy pipeline; quote form with
file upload + branded emails delivering to M365.

## DONE: the cutover (2026-08-14) — WordPress retired, static site live

Steps 1–6 below were completed. Homepage + all 11 pages, CSS/images, sitemap,
robots return 200; SSL valid (Sectigo DV, good through 2027-01-30); `/wp-login.php`
and `/wp-admin/` now 404; `config.php` returns 403. Deploy target is now `./`.
**Still to do:** the post-cutover items in step 7, and a real end-to-end form test
(submit the live quote form and confirm the email + branded auto-reply land in the
M365 `sales@` inbox).

### Original cutover checklist (kept for reference)

1. **Back up WordPress** — cPanel Backup Wizard (files) + phpMyAdmin (database export).
2. **Move WP out of `public_html`** — e.g. to `/home/lakela11/old-wordpress/` (move,
   don't delete, so it's reversible). Leave `public_html` empty.
3. **Point the deploy at the live root** — in `.github/workflows/deploy.yml` change
   `server-dir: ./staging/` → `server-dir: ./`.
4. **Create `config.php` in `public_html`** (the live root) with the Brevo settings
   (copy from `public_html/staging/config.php`). It's gitignored, so the deploy never
   touches it.
5. **Deploy** — push to `main` (or Actions → Run workflow). New site goes live at
   `lakelandgraphics.com`.
6. **Verify** — homepage + quote form (with attachment) on the real domain; run
   Google Rich Results Test; check SSL.
7. **Post-cutover** — submit `sitemap.xml` in Google Search Console; remove the
   `staging` subdomain (optional); later tighten DMARC from `p=none` to
   `p=quarantine`; re-add Brevo IP restriction if desired.

## Also outstanding
- Invite Melissa to CloudCannon (Site Settings → Sharing) if not done; send her the
  PDF guide (`…/Website Builds/Lakeland Graphics - Website Editing Guide.pdf`).

## Hard-won gotchas (so we don't repeat them)
- **CloudCannon "Unsupported data"** was caused by the site never building — fix was
  SSG=Static + Output Path `.` + unlock Building + `_enabled_editors: visual`.
- The on-page editor toolbar is fixed (Bold/Italic/Link/clear) — **no color picker**;
  we map **Bold → accent color in headlines** (see `.h1/.h2/.h3 strong` rule in
  `assets/styles.css`). FAQ question text is an editable `<span>` inside the button
  (buttons aren't editable elements).
- **Email:** InMotion delivered same-domain mail locally instead of routing to M365,
  and M365 SMTP was locked down — so we send via **Brevo**. First failure was Brevo's
  **IP authorization** blocking InMotion's IP; authorizing `104.244.122.87` fixed it.
- **PHP VERSION (the big one, cost us the go-live form).** The account **default PHP
  is 5.6.40**. WordPress's own `.htaccess` had a cPanel handler line forcing PHP 7.x;
  moving that `.htaccess` out of `public_html` at cutover dropped the live root back to
  the 5.6 default. `send-quote.php` is PHP 7.1+ (uses `declare(strict_types=1)`, scalar
  type hints, `: void`, `??`), so on 5.6 it **won't even compile → blank 500 with empty
  body** before any line runs (honeypot never short-circuits). Static pages were fine
  (PHP version only affects `.php`). **Fix: cPanel → MultiPHP Manager → set
  `lakelandgraphics.com` (and the staging subdomain) to `ea-php82` (PHP 8.2).** No
  redeploy needed — it's a live server setting. Confirmed working on PHP 8.2.33.
  Diagnosed with a temporary secrets-masked `diag.php` that isolated each `require`
  and printed `PHP_VERSION` (removed after use).
- **`send-quote.php` returns HTTP 500 to raw `curl`/direct hits** (empty body, no
  referer/multipart) — this is InMotion **ModSecurity**, NOT a broken form. Staging's
  proven-working handler does the same. Only a real browser submission (multipart
  FormData) exercises the true path. Likewise, ModSecurity **406** on plain `curl` to
  any page is just the default curl user-agent being blocked — use a browser UA to test.
- **cPanel File Manager (Jupiter):** no right-click menu; select rows in the RIGHT
  pane (not the left tree) to enable Move/Permissions. `.well-known` and `cgi-bin`
  must stay in `public_html` (SSL/AutoSSL + server), only WordPress files move out.

## 2026-08-18 — mobile image fix + SEO/performance pass

### Mobile horizontal-overflow bug (fixed)
`.shot` carries **both** `aspect-ratio:16/10` and a `min-height`. Inside a grid
item, a min-height on an aspect-ratio box transfers into an automatic **minimum
width** (`min-height × 1.6`) — e.g. `min-height:230px` implies a 368px minimum
width, wider than the ~335px of content a 375px phone has. The frame overflowed
its column and dragged the whole page sideways (headlines cut off mid-word).
Desktop was fine because the column was wide enough to absorb it.

Fixed by zeroing `min-height` on those frames below 900px, so the aspect ratio
alone sets the height. **The override is duplicated in all three stylesheets**
(`styles.css` → `pages.css` → `catalog.css` load in that order) — a single rule
in `styles.css` would lose to the later files on specificity/order. If you add a
new photo frame, add it to the matching guard block.

Affected 8 of 12 pages (worst: `about.html`, 71px). All 12 pages + `404.html` now
verified at 0px overflow from 320px to 1180px.

### Images: 56 MB → 2.9 MB
Source JPEGs were untouched camera originals (up to **6240×4160, 14.7 MB**) shown
at ~600px. Homepage was **27.5 MB**. Resized to **1400px wide, quality 80** with
`sips` — 94.8% smaller, no visible quality loss (1400px keeps it sharp at 2× retina
for the 605px widest frame on the site).

Originals are recoverable from git history (all 13 were committed) — see the commit
before this one. Re-run:
`sips -Z 1400 -s format jpeg -s formatOptions 80 <file> --out <file>`

**Watch out:** CloudCannon lets Melissa replace photos, and a phone upload will put
a 15 MB original straight back on the site. `docs/EDITING-GUIDE.md` now has resizing
instructions. Worth spot-checking image sizes after she publishes.

### Other SEO/perf changes
- `<img>`: added `width`/`height` (CLS), `decoding="async"`, `loading="lazy"` on
  below-fold images, `fetchpriority="high"` on each page's LCP image. Initial render
  payload is now ~350 KB per page.
- `.htaccess`: added **gzip/brotli** (server was sending HTML/CSS uncompressed —
  `styles.css` 24.5 KB → ~5 KB), **www → apex 301**, and `ErrorDocument 404`.
- **`404.html`** — branded, replaces the bare InMotion error page. All its asset and
  link paths are **root-relative** (`/assets/…`) because it gets served at whatever
  path was requested (e.g. `/services/foo/`); relative paths would 404.
- `faq.html` had **no `<h1>`** — its lead heading was an `<h2>`. Now `<h1 class="h2">`,
  which keeps the visual size identical (sizing comes from the class, not the element).
- Trimmed 4 meta descriptions that ran 165–173 chars (Google truncates ~160); og/twitter
  descriptions kept in sync.
- Added **BreadcrumbList** schema to the 10 pages with visible breadcrumbs, plus
  AboutPage / ContactPage / CollectionPage on about / quote / industries.
- `sitemap.xml`: added `lastmod`, `changefreq`, `priority`.

### Still outstanding
- **No redirect map from the old WordPress URLs** — they 404 (now onto the branded
  404 page). Search Console was only set up at go-live so there's no history to mine
  yet. Once it accumulates data, check **Pages → Not indexed → Not found (404)** and
  add 301s to `.htaccess` for anything with real traffic.
- Alt text: all 50 images have descriptive alt attributes. Verified, nothing to do.
- Optional next step: WebP/AVIF with `<picture>` fallback (another ~30% off the
  images) — skipped for now to avoid complicating the CloudCannon-editable markup.
