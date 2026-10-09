#!/usr/bin/env bash
# Sets up an Ubuntu machine (24.04 or newer; we run 26.04) to run Office Contest.
# Safe to run more than once.
#
# Usage:
#   sudo bash setup-ubuntu.sh --domain officevote.example.com              # public server: certificate over HTTP (port 80 must be reachable)
#   sudo bash setup-ubuntu.sh --domain officevote.example.com --dns-cert   # private/office server: certificate by adding a DNS TXT record
#   sudo bash setup-ubuntu.sh --domain 172.20.174.66 --dev --from ~/office-contest   # local VM, self-signed HTTPS, code copied from a folder
set -euo pipefail

DOMAIN=""
DEV=0
DNS_CERT=0
FROM=""
APP_DIR="/var/www/officevote"
REPO="https://github.com/kc9mne/office-contest.git"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="$2"; shift 2 ;;
    --dev) DEV=1; shift ;;
    --dns-cert) DNS_CERT=1; shift ;;
    --app-dir) APP_DIR="$2"; shift 2 ;;
    --from) FROM="$2"; shift 2 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done
[[ -n "$DOMAIN" ]] || { echo "Pass --domain your.domain" >&2; exit 1; }
[[ $EUID -eq 0 ]] || { echo "Run with sudo" >&2; exit 1; }

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
# Unversioned PHP packages pull in whatever PHP this Ubuntu release ships (8.5 on 26.04, 8.3 on 24.04).
apt-get install -yq apache2 mariadb-server ffmpeg git unzip openssl ssl-cert \
  php libapache2-mod-php php-mysql php-sqlite3 php-gd php-mbstring \
  php-curl php-xml php-zip php-intl
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
echo "    PHP $PHP_VER"
[[ $DEV -eq 1 ]] || apt-get install -yq certbot python3-certbot-apache

echo "==> PHP limits (300 MB videos, slow AI calls)"
cat > "/etc/php/${PHP_VER}/apache2/conf.d/90-officevote.ini" <<'INI'
upload_max_filesize = 320M
post_max_size = 330M
max_execution_time = 120
max_input_time = 300
memory_limit = 256M
expose_php = Off
; Newer Ubuntu blocks PCRE's JIT memory and PHP logs a warning on every request
pcre.jit = 0
INI

echo "==> App code"
if [[ -n "$FROM" ]]; then
  mkdir -p "$APP_DIR"
  cp -a "$FROM/." "$APP_DIR/"
elif [[ ! -d "$APP_DIR/.git" ]]; then
  git clone -q "$REPO" "$APP_DIR"
fi
mkdir -p "$APP_DIR/storage/media" "$APP_DIR/storage/tmp"
if [[ $DEV -eq 1 && -n "${SUDO_USER:-}" ]]; then
  # Dev VM: let the login user update the code without sudo
  chown -R "$SUDO_USER:www-data" "$APP_DIR"
fi
chown -R www-data:www-data "$APP_DIR/storage"

echo "==> Database"
ENV_FILE="$APP_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  DB_PASS="$(openssl rand -hex 16)"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS officevote CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'officevote'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'officevote'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON officevote.* TO 'officevote'@'localhost';
FLUSH PRIVILEGES;
SQL
  cat > "$ENV_FILE" <<ENV
APP_URL=https://${DOMAIN}
DB_DSN=mysql:host=localhost;dbname=officevote;charset=utf8mb4
DB_USER=officevote
DB_PASS=${DB_PASS}
# Photobooth (step 5). Keep this file out of git.
OPENAI_API_KEY=
ENV
  chown root:www-data "$ENV_FILE"
  chmod 640 "$ENV_FILE"
  echo "    Wrote $ENV_FILE"
else
  echo "    $ENV_FILE already exists, leaving it alone"
fi

echo "==> Apache site"
a2enmod -q rewrite headers ssl expires >/dev/null
CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
if [[ -f "${CERT_DIR}/fullchain.pem" ]]; then
  SSL_CERT="${CERT_DIR}/fullchain.pem"; SSL_KEY="${CERT_DIR}/privkey.pem"
else
  SSL_CERT="/etc/ssl/certs/ssl-cert-snakeoil.pem"; SSL_KEY="/etc/ssl/private/ssl-cert-snakeoil.key"
fi
cat > /etc/apache2/sites-available/officevote.conf <<CONF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public

    SSLEngine on
    SSLCertificateFile ${SSL_CERT}
    SSLCertificateKeyFile ${SSL_KEY}

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Uploaded photos and videos: served directly by Apache, never run as code
    Alias /media ${APP_DIR}/storage/media
    <Directory ${APP_DIR}/storage/media>
        Require all granted
        Options -Indexes
        php_admin_flag engine off
        Header set X-Content-Type-Options nosniff
        ExpiresActive On
        ExpiresDefault "access plus 7 days"
    </Directory>

    # A little above PHP's post_max_size, so PHP (not Apache) answers oversized uploads with a clear message
    LimitRequestBody 419430400
    Header always set Referrer-Policy same-origin
    Header always set Permissions-Policy "camera=(self)"

    ErrorLog \${APACHE_LOG_DIR}/officevote-error.log
    CustomLog \${APACHE_LOG_DIR}/officevote-access.log combined
</VirtualHost>
CONF
if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
  echo "==> Opening web ports in the firewall"
  ufw allow 'Apache Full' >/dev/null
fi
a2ensite -q officevote >/dev/null
a2dissite -q 000-default >/dev/null 2>&1 || true
apache2ctl configtest
systemctl reload apache2

if [[ $DEV -eq 0 && $DNS_CERT -eq 1 ]]; then
  echo "==> HTTPS certificate (DNS check)"
  if [[ -f "${CERT_DIR}/fullchain.pem" ]] && openssl x509 -checkend $((20 * 86400)) -noout -in "${CERT_DIR}/fullchain.pem" >/dev/null; then
    echo "    Certificate already in place, valid until $(openssl x509 -enddate -noout -in "${CERT_DIR}/fullchain.pem" | cut -d= -f2)"
  else
    cat <<MSG

    Let's Encrypt will show you a TXT record to add at your DNS provider (GoDaddy: Domain > DNS > Add New Record).
      Type: TXT    Name: the part before .${DOMAIN#*.} (e.g. _acme-challenge.${DOMAIN%%.*})    Value: the long code it shows
    Add it, wait a minute or two, then press Enter. You can delete the TXT record afterwards.

MSG
    if certbot certonly --manual --preferred-challenges dns -d "$DOMAIN" --agree-tos --register-unsafely-without-email; then
      sed -i -e "s#SSLCertificateFile .*#SSLCertificateFile ${CERT_DIR}/fullchain.pem#" \
             -e "s#SSLCertificateKeyFile .*#SSLCertificateKeyFile ${CERT_DIR}/privkey.pem#" /etc/apache2/sites-available/officevote.conf
      apache2ctl configtest && systemctl reload apache2
      echo "    Certificate installed, valid until $(openssl x509 -enddate -noout -in "${CERT_DIR}/fullchain.pem" | cut -d= -f2)"
      echo "    It lasts 90 days. To renew, run this script again (with --dns-cert) and add the new TXT record."
    else
      echo "    The certificate step failed. The site still works with a self-signed certificate."
      echo "    Run this script again with --dns-cert to retry."
    fi
  fi
elif [[ $DEV -eq 0 ]]; then
  echo "==> HTTPS certificate"
  certbot --apache -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email --redirect || \
    echo "    certbot failed. Check that ${DOMAIN} points at this server, then run: sudo certbot --apache -d ${DOMAIN}"
fi

echo
echo "Done. Open https://${DOMAIN}"
if [[ $DEV -eq 1 ]]; then
  echo "(Dev mode uses a self-signed certificate, so your browser will warn once.)"
fi
