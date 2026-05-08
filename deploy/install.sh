#!/usr/bin/env bash
# AmplifyCan Customer Portal — one-shot installer for Ubuntu 22.04 LTS.
# Usage:
#   1. Spin up a $6/mo DigitalOcean droplet (Ubuntu 22.04, 1 vCPU / 1 GB RAM is fine)
#   2. Point an A record for portal.amplifycan.com at the droplet's IP
#   3. SCP the portal/ folder to /opt/portal (or git clone)
#   4. SSH in as root and run:  bash /opt/portal/deploy/install.sh
#
# This script is idempotent — safe to re-run.

set -euo pipefail

DOMAIN="${DOMAIN:-portal.amplifycan.com}"
PORTAL_DIR="${PORTAL_DIR:-/opt/portal}"
APP_USER="${APP_USER:-www-data}"
LE_EMAIL="${LE_EMAIL:-mark@amplifycan.com}"

echo "==> Installing system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
  nginx \
  php-cli php-fpm php-sqlite3 php-curl php-mbstring php-xml php-zip \
  poppler-utils ghostscript \
  certbot python3-certbot-nginx \
  cron rsync

echo "==> Locating PHP-FPM version"
PHP_VER="$(ls /etc/php/ | sort -V | tail -1)"
PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
echo "    Using PHP $PHP_VER, socket $PHP_FPM_SOCK"

echo "==> Configuring PHP for large uploads"
PHP_CONF="/etc/php/${PHP_VER}/fpm/conf.d/99-portal.ini"
cat > "$PHP_CONF" <<EOF
upload_max_filesize = 200M
post_max_size = 200M
memory_limit = 256M
max_execution_time = 120
date.timezone = America/Chicago
EOF
systemctl restart "php${PHP_VER}-fpm"

echo "==> Setting permissions on $PORTAL_DIR"
chown -R "$APP_USER:$APP_USER" "$PORTAL_DIR"
mkdir -p "$PORTAL_DIR/storage/uploads" "$PORTAL_DIR/storage/sessions"
chmod -R u+rwX "$PORTAL_DIR/storage"

echo "==> Writing nginx site config"
NGINX_SITE="/etc/nginx/sites-available/${DOMAIN}"
cat > "$NGINX_SITE" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${PORTAL_DIR}/public;
    index index.php;

    client_max_body_size 200M;

    # Front controller pattern
    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_read_timeout 120;

        # Portal env vars — set these to real values before going live
        fastcgi_param PORTAL_DEBUG               "false";
        fastcgi_param PORTAL_BASE_URL            "https://${DOMAIN}";
        fastcgi_param PORTAL_STORAGE             "${PORTAL_DIR}/storage";
        fastcgi_param PORTAL_MONDAY_API_KEY      "${PORTAL_MONDAY_API_KEY:-}";
        fastcgi_param PORTAL_MONDAY_ESTIMATES_BOARD_ID "${PORTAL_MONDAY_ESTIMATES_BOARD_ID:-8483187264}";
        fastcgi_param PORTAL_MONDAY_SUBITEMS_BOARD_ID  "${PORTAL_MONDAY_SUBITEMS_BOARD_ID:-8483469691}";
        fastcgi_param PORTAL_FROM_EMAIL          "${PORTAL_FROM_EMAIL:-noreply@amplifycan.com}";
        fastcgi_param PORTAL_EMAIL_DRYRUN        "${PORTAL_EMAIL_DRYRUN:-true}";
    }

    # Don't expose storage/, db/, src/, deploy/
    location ~ ^/(storage|db|src|deploy|config\.php) {
        deny all;
    }

    access_log /var/log/nginx/${DOMAIN}.access.log;
    error_log  /var/log/nginx/${DOMAIN}.error.log;
}
EOF

ln -sf "$NGINX_SITE" "/etc/nginx/sites-enabled/${DOMAIN}"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> Requesting Let's Encrypt cert (skipped if cert already exists)"
if [ ! -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]; then
  certbot --nginx --non-interactive --agree-tos --email "$LE_EMAIL" -d "$DOMAIN" --redirect
fi

echo "==> Setting up nightly backup of SQLite + uploads"
BACKUP_SCRIPT="/usr/local/bin/portal-backup.sh"
cat > "$BACKUP_SCRIPT" <<EOF
#!/usr/bin/env bash
set -e
DEST=/var/backups/portal
mkdir -p "\$DEST"
DATE=\$(date +%Y%m%d-%H%M)
sqlite3 ${PORTAL_DIR}/storage/portal.sqlite ".backup '\$DEST/portal-\$DATE.sqlite'"
rsync -aq ${PORTAL_DIR}/storage/uploads/ "\$DEST/uploads-latest/"
# keep last 14 daily snapshots
find "\$DEST" -name 'portal-*.sqlite' -type f -mtime +14 -delete
EOF
chmod +x "$BACKUP_SCRIPT"
( crontab -l 2>/dev/null | grep -v portal-backup; echo "30 2 * * * $BACKUP_SCRIPT" ) | crontab -

echo
echo "==> Done."
echo
echo "Next steps:"
echo "  1. Edit ${NGINX_SITE} and set PORTAL_MONDAY_API_KEY in the fastcgi_param block."
echo "  2. systemctl reload nginx"
echo "  3. Visit https://${DOMAIN}/login and sign in (admin@amplifycan.com / demo)."
echo "  4. Set up real customers in /admin and replace the demo logins."
echo "  5. Set PORTAL_EMAIL_DRYRUN=false once an MTA / SMTP is configured."
