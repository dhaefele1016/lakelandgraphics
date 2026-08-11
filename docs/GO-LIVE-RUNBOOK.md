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
- **Current live site:** still the old **WordPress** install in `public_html` (untouched).

## Status: DONE (on staging)

Full site + SEO/AEO (meta, OG, canonical, LocalBusiness/FAQPage/Service schema,
sitemap, robots); CloudCannon on-page editing (text, images, headline accent via
**Bold**, FAQ, privacy/terms fully editable); auto-deploy pipeline; quote form with
file upload + branded emails delivering to M365.

## Remaining: the cutover (retire WordPress, go live)

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
