# AmplifyCan Customer Portal

A small custom web app for transactional print customers to place orders, run repro jobs, reorder past orders, and check status without going through email. Designed to live at `portal.amplifycan.com`.

## What's working today

End-to-end customer flows + admin + Monday integration.

- **Auth & dashboard.** Login, session-based auth, CSRF-protected forms. Dashboard shows open orders, year-to-date spend, quick reorder, and recent orders.
- **Reprint flow** (the highest-volume use case). Drag-drop or click-upload one or more PDFs. The portal runs `pdfinfo` (page count + size) and `gs -sDEVICE=inkcov` (per-page color detection) to auto-generate the breakdown into editable ranges. Customer adjusts size/color/stock per range, sets quantity (sets) and finishing, sees a live quote computed against their saved per-page rates with volume discount kicked in over 500 pages. Approve places the order.
- **Catalog flow.** Per-customer saved products. Multi-line products (business cards: N recipients, one file per recipient). Single-line products (banners, foam boards) with optional saved-artwork toggle. File upload runs preflight (page-size match, RGB warning, min-DPI check) — pass / warn / fail with a confirm-and-place gate.
- **Order detail.** Status timeline (Submitted → In Production → Shipped → Delivered → Invoiced). Files attached. Monday item id displayed. If a real Monday API key is set, status auto-syncs from the Monday board on view.
- **Monday integration.** On approve, every order pushes to a configured Monday board with item name, status column, route (in-house vs 4over), total, project, and a long-form update note containing the line items, ship-to, notes, and file list. In dry-run mode (no API key set), the would-be payload is logged to `storage/monday_dryrun.log` so you can verify what would have been sent.
- **Admin.** Set up customers, login users, addresses, saved products (with pricing, multi-line flag, fulfillment route), and per-customer reprint pricing rules. New customers get a sensible default rate card pre-seeded.

## Running locally

You need PHP 8.1 or later. SQLite is included with PHP. The PDF tools live in `poppler-utils` and `ghostscript`:

```
sudo apt-get install -y php-cli php-sqlite3 poppler-utils ghostscript
```

Then from the project folder:

```
cd portal
php -S localhost:8000 -t public
```

Open http://localhost:8000.

The first request creates `storage/portal.sqlite` and seeds it. Delete that file to reset.

### Important PHP settings

The defaults won't allow large PDF uploads. Make sure your `php.ini` has:

```
upload_max_filesize = 200M
post_max_size = 200M
memory_limit = 256M
```

For local dev: pass `-d upload_max_filesize=200M -d post_max_size=200M` to `php -S`, or use a `php.ini` with those values.

## Demo logins (after seed)

| Email | Password | Role |
|---|---|---|
| `lashworth@founders3.com` | `demo` | Founders 3 customer (cards, letterhead, envelopes — multi-line, multi-address) |
| `rayek@buildics.com` | `demo` | ICSI customer (banner reorders with on-file artwork) |
| `admin@amplifycan.com` | `demo` | Admin |

## Environment variables

| Variable | Purpose |
|---|---|
| `PORTAL_STORAGE` | Override storage location (default `./storage`). Useful when source tree is on a filesystem that doesn't support SQLite WAL. |
| `PORTAL_BASE_URL` | Public base URL. |
| `PORTAL_MONDAY_API_KEY` | Monday.com API token. If unset, the portal runs in **dry-run mode** — payloads are logged to `storage/monday_dryrun.log` instead of being sent. |
| `PORTAL_MONDAY_BOARD_ID` | Monday.com board id where new orders should land. |
| `PORTAL_PDFINFO`, `PORTAL_PDFIMAGES`, `PORTAL_GHOSTSCRIPT` | Override the binary names if not on PATH. |
| `PORTAL_DEBUG` | Set to `false` in production to suppress error display. |

## Project layout

```
portal/
├── public/                          ← web root
│   └── index.php                    ← front controller
├── src/
│   ├── App.php                      ← bootstrap + routes
│   ├── Router.php                   ← tiny router
│   ├── Database.php                 ← SQLite + auto-seed
│   ├── Auth.php                     ← session auth + CSRF
│   ├── View.php                     ← template helper
│   ├── helpers.php                  ← global helpers (e(), pill_class())
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ReprintController.php    ← reprint flow
│   │   ├── CatalogController.php    ← catalog flow + preflight gate
│   │   ├── OrderController.php      ← order list + detail
│   │   └── AdminController.php      ← admin (customers/products/rules)
│   └── Services/
│       ├── PdfAnalyzer.php          ← pdfinfo + gs inkcov wrapper
│       ├── Preflight.php            ← spec validation against products
│       ├── Pricing.php              ← reprint quote engine
│       └── Monday.php               ← GraphQL push + status pull
├── views/                           ← PHP templates (auth, dashboard, catalog, reprint, orders, admin)
├── db/
│   ├── schema.sql                   ← schema
│   └── seed.php                     ← seed data
├── storage/                         ← runtime (SQLite, sessions, uploads, Monday log)
└── config.php
```

## Production deploy notes

Target: `portal.amplifycan.com` on a small VPS (DigitalOcean droplet, Linode, or similar).

- nginx serving `public/` with PHP-FPM 8.1+
- `poppler-utils` and `ghostscript` from apt
- Let's Encrypt cert
- A nightly backup of `storage/portal.sqlite` and `storage/uploads/orders/`
- `PORTAL_DEBUG=false`
- Real `PORTAL_MONDAY_API_KEY` and `PORTAL_MONDAY_BOARD_ID` env vars set in nginx fastcgi_param config

A first-cut deploy script: stage the project, install packages, point nginx at `public/`, set env vars in the systemd service or fastcgi config. Want me to write that out? It's a one-shot bash script.

## What's next

A few things that aren't built yet but would be the obvious next slices:

- **Email notifications** at order milestones (received, in production, shipped, invoiced).
- **CRM auto-estimate**: today the Monday card has the line items and total, but we don't yet write into the CRM. Easy to bolt on once we know the CRM API.
- **Status webhook from Monday** so we get push instead of polling on view.
- **Template-based ordering for cards** (the F3 name-swap path: customer types name into form, system generates proof from saved template). Phase 2.
- **4over API integration** (Phase 3, when manual ordering volume justifies it).
