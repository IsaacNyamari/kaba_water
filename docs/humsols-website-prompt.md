# Task: Build the Human Solutions (HumSols) company website — Next.js + Node.js + MongoDB

## Instructions for Claude Code

- Work end-to-end: project setup, data models, API, public pages, admin dashboard, emails, tests and deployment notes. Do not stop at a UI mock-up or leave TODOs in place of features.
- Before writing code, briefly state the plan (folders, models, pages) and confirm the installed versions with `node -v`, `npm -v` and `package.json`. Use the APIs that match those versions.
- When something is ambiguous, choose the simplest reasonable option and list it under "Decisions" in your final summary.
- After each major step (models, API, public pages, admin, emails), run the type-check, lint and tests, and fix failures before continuing.
- **Never invent facts about the company.** Where real content is missing (client names, case-study numbers, testimonials, team bios, package prices), use clearly marked placeholders such as `[PLACEHOLDER: client name]` and keep them in the database seed so they can be edited from the admin. Never present a made-up testimonial or client as real.

---

## 1. The company

| Item | Value |
|---|---|
| Legal / trading name | Human Solutions (short brand: **HumSols**) |
| Tagline | Human Solutions |
| Location | Nairobi, Kenya (office: Westlands, Nairobi) |
| Email | info@humsols.com |
| Phone | +254 725 742 737 / +254 711 777 992 |
| Website | https://humsols.com (this project) |
| Existing product | **HS Water Billing** — https://water-billing.humsols.com (multi-tenant water billing and borehole project management SaaS; link to it, do not rebuild it) |

### What we do (the services the site must sell)

1. **Web application development** — custom web apps, portals, dashboards, SaaS platforms.
2. **Custom software development** — business systems, automation, integrations, APIs.
3. **Billing & management systems** — utility/water billing, subscription billing, invoicing, POS, inventory, M-Pesa integration; flagship product HS Water Billing.
4. **Website design & development** — company websites, e-commerce, landing pages, hosting and maintenance.
5. **CCTV installation** — site survey, IP/analog cameras, NVR/DVR setup, remote viewing on phone, maintenance contracts.
6. **Internet services (ISP)** — home and business internet, installation, packages by speed, coverage check.

Audience: small and medium businesses, estates and landlords, water vendors, schools, churches, and homes in Kenya. Tone: plain, confident, practical. No jargon on the public pages; prices in **KES**.

---

## 2. Brand

Logo files are supplied. Copy them into `public/brand/`:

- `humsols-logo.png` — full logo, transparent, for light backgrounds
- `humsols-logo-dark.png` — full logo, transparent, brighter blue, for dark backgrounds
- `humsols-mark.png` / `humsols-mark-dark.png` — the "two people" mark (square)
- `favicon.ico`, `favicon.svg`, `apple-touch-icon.png`

(Source: `public/images/brand/` and `public/` in the `kaba_water` repository.)

Colours (from the logo):

| Token | Hex | Use |
|---|---|---|
| `navy` | `#010C1B` | dark backgrounds, footer |
| `royal` | `#0B58AD` | primary brand colour ("Hum") |
| `royal-bright` | `#4D8CE6` | royal on dark backgrounds |
| `aqua` | `#00DACE` | accent ("Sols"), highlights, focus rings |

- Support **light and dark mode**: follow the system setting, with a toggle. Use the dark logo variant in dark mode.
- Font: a clean geometric sans (e.g. Inter or Plus Jakarta Sans via `next/font`). Headings bold, generous spacing.
- Style: modern, clean, card-based, subtle motion (respect `prefers-reduced-motion`), mobile-first.

---

## 3. Tech stack (use exactly this unless a version check forces otherwise)

- **Next.js** (latest stable, App Router, React Server Components, Server Actions where they simplify forms), **TypeScript** strict mode.
- **Node.js** 22 runtime. Route handlers under `app/api/**` for the public JSON API.
- **MongoDB** with **Mongoose** (typed schemas, indexes, timestamps). One shared connection helper that caches the connection across hot reloads.
- **Tailwind CSS v4**, with the colours above as theme tokens. Small in-house component set (Button, Card, Badge, Input, Select, Textarea, Modal, Toast, Tabs, Table). No heavy UI kit.
- **Zod** for every input (forms, API bodies, query strings) — shared schemas between client and server.
- **Auth.js (NextAuth)** with a credentials provider for the admin (bcrypt password hashes in MongoDB), JWT sessions, role-based access.
- **Nodemailer** over SMTP for emails, configured from environment variables (our mail server is `mail.humsols.com`).
- Images: `next/image`; admin uploads stored on local disk under `uploads/` (served through a route handler) with type and size checks. Keep the storage behind a small interface so it can later move to S3.
- Testing: **Vitest** (unit + API with an in-memory MongoDB such as `mongodb-memory-server`) and **Playwright** (key user journeys).
- Tooling: ESLint, Prettier, `npm run typecheck`.

---

## 4. Public website — pages

| Route | Content |
|---|---|
| `/` | Hero ("We build the systems your business runs on" or similar), the six services as cards, why HumSols, featured projects, HS Water Billing spotlight, internet packages teaser, testimonials (from DB, placeholders allowed), CTA band (Get a quote / Call us / WhatsApp). |
| `/services` | Overview of all six services. |
| `/services/[slug]` | One page per service, content from the DB: problem it solves, what's included, process steps, related projects, FAQs, CTA. Slugs: `web-applications`, `custom-software`, `billing-systems`, `websites`, `cctv-installation`, `internet`. |
| `/products/hs-water-billing` | Product page for HS Water Billing with features and a link to https://water-billing.humsols.com and its request-access page. |
| `/internet` | Internet packages (home / business) from the DB with speed, price per month (KES), installation fee, fair-use notes; **coverage check** form (estate/area + location pin or address) and **installation request**. |
| `/cctv` | Redirect or alias to `/services/cctv-installation`, with a **free site survey** booking form (date preference, site type, number of cameras estimate). |
| `/portfolio` + `/portfolio/[slug]` | Projects/case studies from the DB, filterable by service. |
| `/about` | Story, mission, values, team (from DB, placeholders allowed). |
| `/blog` + `/blog/[slug]` | News and articles (Markdown/MDX content stored in DB, rendered safely). |
| `/careers` | Open roles from the DB (empty state when none). |
| `/contact` | Contact form, phones (tap-to-call), email, WhatsApp link (`https://wa.me/254725742737`), office address, opening hours, map embed. |
| `/quote` | **Multi-step quote request**: choose service(s) → service-specific questions (e.g. web: pages/features/deadline; CCTV: cameras, indoor/outdoor, site type; internet: location, home/business, speed) → contact details → review → submit. |
| `/support` | Support request for existing customers (internet fault, CCTV fault, software issue) with priority. |
| `/privacy`, `/terms` | Legal pages (Kenya Data Protection Act, 2019 wording; mark anything needing a lawyer's review). |
| `not-found`, `error` | Branded 404 and error pages. |

Global: sticky header with logo, services mega-menu, "Get a quote" button and theme toggle; footer with services, company links, contacts, social links (from settings), and a link to HS Water Billing. Floating WhatsApp button on mobile.

---

## 5. Data model (MongoDB / Mongoose)

Create these collections with sensible indexes, `timestamps: true`, and validation:

- **User** (admins): name, email (unique, lowercase), passwordHash, role (`owner` | `admin` | `editor` | `sales`), active, lastLoginAt.
- **Service**: slug (unique), title, summary, body (rich text/Markdown), icon, heroImage, features[], process[] (step, title, text), faqs[] (q, a), order, published, seo { title, description, ogImage }.
- **Project** (portfolio): slug, title, client (may be placeholder), services[] (refs), summary, body, images[], results[] (label, value), liveUrl, featured, published, order.
- **InternetPackage**: name, audience (`home` | `business`), speedMbps, priceKes, installationFeeKes, features[], popular, active, order.
- **Testimonial**: name, role, company, quote, rating, isPlaceholder, published.
- **TeamMember**: name, role, bio, photo, order, published.
- **Post** (blog): slug, title, excerpt, body (Markdown), coverImage, tags[], author (ref User), publishedAt, status (`draft` | `published`).
- **Job**: slug, title, type, location, description, open.
- **FAQ**: question, answer, category, order.
- **Lead** (every form submission): type (`contact` | `quote` | `internet_coverage` | `internet_installation` | `cctv_survey` | `support`), name, email, phone, company, services[], message, details (type-specific object validated by a per-type Zod schema), source page, utm params, status (`new` | `contacted` | `qualified` | `proposal` | `won` | `lost` | `closed`), assignedTo (ref User), notes[] (by, text, at), reference (human-readable, e.g. `HS-Q-2026-0001`), ip, userAgent, consent (bool + timestamp).
- **SiteSetting** (single document): company name, phones[], email, whatsapp, address, hours, social links, SEO defaults, announcement bar text, notification recipients.
- **AuditLog**: user, action, entity, entityId, changes, at.

Seed script (`npm run seed`): settings with the real contacts above, the six services with real descriptions written from section 1, starter internet packages and portfolio/testimonials/team marked as placeholders, and one owner admin whose email and password come from environment variables (never hard-coded).

---

## 6. Forms, leads and email

- Every public form: Zod validation on client and server, honeypot field, per-IP rate limiting (e.g. 5 submissions per 10 minutes), consent checkbox linking to the privacy page, accessible error messages, success screen showing the lead reference.
- On submission: save the **Lead**, then send (queued/asynchronous so the response is fast):
  1. a notification email to the recipients in SiteSetting (default `info@humsols.com`) with all details and a link to the lead in the admin;
  2. an auto-reply to the customer with their reference number, what happens next, our phones and WhatsApp.
- Branded HTML email templates (logo, navy/royal/aqua), with plain-text alternatives.
- Email failures must not lose the lead; log the failure and show it in the admin.

---

## 7. Admin dashboard (`/admin`)

- Login page (Auth.js credentials), session-protected layout via middleware, logout, password change, forgotten-password by email token.
- **Dashboard**: new leads today/this week, leads by type and status, conversion (won ÷ total), latest leads, quick links.
- **Leads**: table with search, filters (type, status, service, date range, assignee), pagination; lead detail with all fields, status pipeline, assign to user, internal notes, email/call/WhatsApp quick actions; export to CSV.
- **Content management** (CRUD with publish/unpublish, ordering, image upload, slug generation, preview): Services, Projects, Internet packages, Testimonials, Team, Blog posts, Jobs, FAQs.
- **Settings**: company details, contacts, social links, notification recipients, SEO defaults, announcement bar.
- **Users** (owner/admin only): invite, change role, deactivate.
- Roles: `owner` (everything), `admin` (everything except removing owners), `editor` (content only), `sales` (leads only). Enforce on the server for every route handler and server action, not only in the UI.
- Every create/update/delete writes an **AuditLog** entry.
- Responsive: usable on a phone.

---

## 8. SEO, performance, accessibility, security

- Metadata API for titles/descriptions/Open Graph per page (from DB SEO fields with fallbacks), `sitemap.xml` and `robots.txt` generated from published content, JSON-LD (`Organization`, `LocalBusiness`, `Service`, `BlogPosting`, `FAQPage`).
- Static generation / ISR for public pages, revalidated when content changes in the admin (`revalidatePath`/tags).
- Lighthouse targets on mobile: Performance ≥ 90, Accessibility ≥ 95, SEO ≥ 95.
- WCAG 2.1 AA: keyboard navigation, focus states, alt text required on uploads, colour contrast in both themes.
- Security: hashed passwords (bcrypt), secure cookies, CSRF protection for mutations (Auth.js/Server Actions defaults plus origin checks on route handlers), strict input validation, Markdown rendered with sanitisation, upload type/size limits, security headers (CSP, HSTS, X-Frame-Options, Referrer-Policy) in `next.config`, no secrets in the client bundle, `.env.example` with every variable documented.
- Analytics: optional, behind an env flag, privacy-friendly (no tracking without consent).

---

## 9. Environment variables (document all in `.env.example`)

```
MONGODB_URI=mongodb://127.0.0.1:27017/humsols
AUTH_SECRET=
AUTH_URL=https://humsols.com
SITE_URL=https://humsols.com
SMTP_HOST=mail.humsols.com
SMTP_PORT=465
SMTP_SECURE=true
SMTP_USER=
SMTP_PASSWORD=
MAIL_FROM="Human Solutions <info@humsols.com>"
LEAD_NOTIFY_TO=info@humsols.com
SEED_OWNER_EMAIL=
SEED_OWNER_PASSWORD=
UPLOAD_DIR=./uploads
MAX_UPLOAD_MB=10
```

---

## 10. Deployment target (write a `DEPLOYMENT.md` for this; do not run it without being asked)

The site replaces the holding page at **https://humsols.com** on our existing server:

- Ubuntu 24.04, nginx, certbot (a Let's Encrypt certificate for `humsols.com` and `www.humsols.com` already exists), BIND for DNS, Node.js 22 already installed.
- The server also runs other services — **do not use or change these ports**: 80/443 (nginx), 3306 (MySQL), 6001 (Reverb for HS Water Billing), 8080 (Stalwart mail admin), 25/465/587/993/995/4190 (mail).
**The server is already prepared.** Build the app to fit this setup; do not reinstall or reconfigure it:

| Item | Already in place |
|---|---|
| App folder | `/var/www/humsols` (repository root), owned by system user `humsols` |
| Uploads | `/var/www/humsols-data/uploads` (use `UPLOAD_DIR`) |
| Environment | `/etc/humsols/humsols.env`: all variables from section 9, with `MONGODB_URI`, `AUTH_SECRET`, `PORT=3000`, `HOSTNAME=127.0.0.1` filled in. Loaded by systemd and the deploy script; the app must not need a `.env` file in production. |
| MongoDB | 8.0, `127.0.0.1:27017` only, authentication on; app user `humsols` with `readWrite` on database `humsols` |
| Service | `humsols-web.service` runs `node node_modules/next/dist/bin/next start -H 127.0.0.1 -p 3000` as `humsols`, so the app must work with plain `next start` (no PM2, no custom server) |
| nginx | `humsols.com` / `www` proxy to `127.0.0.1:3000` over HTTPS, `/_next/static` cached for a year; falls back to the holding page when the app is down |
| Deploy | `sudo humsols-deploy` pulls `REPO_URL` (set in `/etc/humsols/deploy.conf`) as `humsols`, runs `npm ci` and `npm run build` with the env loaded, restarts the service and checks `:3000` responds |
| Backups | nightly `mongodump` of `humsols` to `/var/backups/humsols`, 14-day retention |

So the app must provide: `npm ci`, `npm run build`, `npm run start` (plain `next start`) and `npm run seed`. Write `DEPLOYMENT.md` around these existing pieces.

---

## 11. Tests (must pass before you finish)

- **Unit/API (Vitest)**: Zod schemas for every form type; lead creation saves the right type-specific details and a unique reference; rate limiting and honeypot reject spam; email failure keeps the lead and records the error; role checks (editor cannot see leads, sales cannot edit content); publishing toggles visibility on the public API.
- **E2E (Playwright)**: home page renders with logo in light and dark mode; submit a contact form and see the success reference; complete the multi-step quote for CCTV; internet coverage request; admin login → see the new lead → change its status → add a note; editor publishes a service change and it appears on the public page.

---

## 12. Execution order

1. Scaffold Next.js (TypeScript, Tailwind v4, ESLint), brand tokens, fonts, theme toggle, logo, layout (header/footer).
2. MongoDB connection helper, Mongoose models, Zod schemas, seed script.
3. Public pages reading from the DB (home, services, service detail, product page, internet, CCTV, portfolio, about, blog, careers, contact, legal).
4. Forms + Lead API + rate limiting + spam protection + email templates and sending.
5. Auth.js, admin layout, dashboard, leads management, content CRUD, settings, users, audit log.
6. SEO (metadata, sitemap, robots, JSON-LD), performance, accessibility pass, security headers.
7. Tests (Vitest + Playwright) — all green.
8. `README.md` (local setup) and `DEPLOYMENT.md` (section 10).

---

## 13. Acceptance criteria

- All six services, HS Water Billing, internet packages and CCTV survey are clearly presented and every page works in light and dark mode on phone, tablet and desktop.
- Every form stores a Lead in MongoDB, emails `info@humsols.com`, sends the customer an auto-reply with a reference, and resists spam and floods.
- Admins can sign in, manage leads through the pipeline, and edit all public content without touching code; permissions are enforced on the server.
- No invented facts: placeholders are clearly marked and editable.
- `npm run typecheck`, `npm run lint`, `npm test` and the Playwright suite pass; Lighthouse targets met on the home page.
- `README.md` and `DEPLOYMENT.md` let someone set up locally and deploy to the server described above.

---

## 14. When you finish, output

1. The file tree of what you created.
2. The commands to install, seed, run, test and build.
3. A list of placeholders that still need real content.
4. Decisions and deviations from this prompt, with reasons.
