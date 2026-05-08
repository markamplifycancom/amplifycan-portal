# Production deploy — portal.amplifycan.com

## Prerequisites

- A small VPS with public IP (DigitalOcean $6/mo, Hetzner $4/mo, Linode $5/mo all work)
- Ubuntu 22.04 LTS
- DNS A record for `portal.amplifycan.com` pointing at the VPS IP (must be in place before requesting the SSL cert)

## Deploy steps

```bash
# 1. From your laptop, copy the portal folder to the server
rsync -avz --exclude=storage --exclude=node_modules portal/ root@SERVER_IP:/opt/portal/

# 2. SSH in and run the installer
ssh root@SERVER_IP
PORTAL_MONDAY_API_KEY="your_token_here" bash /opt/portal/deploy/install.sh

# 3. Verify
curl -I https://portal.amplifycan.com/login
```

## Environment variables (set in nginx fastcgi_param)

| Variable | Required | Notes |
|---|---|---|
| `PORTAL_MONDAY_API_KEY` | Yes (for live Monday push) | Get from Monday → Profile → Developers → My access tokens |
| `PORTAL_MONDAY_ESTIMATES_BOARD_ID` | Override only | Default: 8483187264 (current Estimates board) |
| `PORTAL_MONDAY_SUBITEMS_BOARD_ID` | Override only | Default: 8483469691 (Estimates subitems board) |
| `PORTAL_DEBUG` | Recommended `false` | Hides PHP error display |
| `PORTAL_FROM_EMAIL` | If sending email | e.g., `noreply@amplifycan.com` |
| `PORTAL_EMAIL_DRYRUN` | Default `true` | Set to `false` once real SMTP is wired up |

## Updating the portal in place

```bash
# Local
rsync -avz --exclude=storage portal/ root@SERVER_IP:/opt/portal/

# Server
sudo systemctl reload nginx
```

The SQLite database lives at `/opt/portal/storage/portal.sqlite` — back it up before deploying schema changes.

## Backups

The installer adds a nightly cron at 2:30 AM:

```
/usr/local/bin/portal-backup.sh
```

It writes to `/var/backups/portal/`, keeps 14 daily snapshots of the SQLite DB and a synced copy of uploaded files. Pull these to S3 / a NAS / wherever for offsite.

## Smoke test after deploy

1. Visit `https://portal.amplifycan.com/login`
2. Sign in as `admin@amplifycan.com` / `demo` (change this immediately in /admin)
3. Sign in as `lashworth@founders3.com` / `demo`
4. Reprint a real PDF — confirm the order lands in Monday's Estimates board with `Lead Source = Portal`
5. Sign back in as admin and toggle email touchpoints, change pricing, add a customer

## Rollback

If a deploy goes sideways:

```bash
# Restore SQLite from last backup
cp /var/backups/portal/portal-YYYYMMDD-HHMM.sqlite /opt/portal/storage/portal.sqlite
chown www-data:www-data /opt/portal/storage/portal.sqlite

# Restore code
rsync -avz portal-previous/ root@SERVER_IP:/opt/portal/
sudo systemctl reload nginx
```
