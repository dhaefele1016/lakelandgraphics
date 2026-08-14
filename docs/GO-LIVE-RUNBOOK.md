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
