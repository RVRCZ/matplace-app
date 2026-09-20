#!/usr/bin/env bash
# One-time server preparation for the new matplace application (Ubuntu 24.04, Hetzner).
# Safe to re-run. Does NOT touch /var/www/matplace (legacy app) or its database.
#
#   sudo bash deploy/server-setup.sh
set -euo pipefail

APP_DIR=/var/www/matplace-app
DB_NAME=matplace_app
DB_USER=matplace_app

echo "== packages"
apt-get update -qq
apt-get install -y -qq php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath \
    xvfb python3 python3-pip python3-venv git unzip >/dev/null

echo "== python tools (trimesh + pymeshfix in a venv)"
python3 -m venv /opt/matplace-py
/opt/matplace-py/bin/pip install -q --upgrade pip
/opt/matplace-py/bin/pip install -q trimesh numpy scipy networkx pymeshfix pillow || /opt/matplace-py/bin/pip install -q trimesh numpy pillow

echo "== node (for vite build)"
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null
    apt-get install -y -qq nodejs >/dev/null
fi

echo "== database (password printed once, put it into .env)"
if ! mysql -e "USE ${DB_NAME}" 2>/dev/null; then
    DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
    mysql <<SQL
CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    echo "DB_DATABASE=${DB_NAME}"
    echo "DB_USERNAME=${DB_USER}"
    echo "DB_PASSWORD=${DB_PASS}"
fi

echo "== directories"
mkdir -p "${APP_DIR}" /var/log
chown -R www-data:www-data "${APP_DIR}"

echo "== OrcaSlicer check"
if [ -x /opt/orca/squashfs-root/AppRun ]; then
    echo "OrcaSlicer present at /opt/orca (shared with legacy app)"
    # the worker runs as www-data: make the AppImage tree readable and its own HOME writable
    chmod -R a+rX /opt/orca/squashfs-root
else
    echo "!! OrcaSlicer not found at /opt/orca — slicing will fail until installed"
fi

echo "== FreeCAD (optional, STEP/IGES)"
if ! command -v freecadcmd >/dev/null; then
    echo "FreeCAD not installed. To enable STEP/IGES: apt-get install -y freecad   (≈ 1 GB) then set FREECAD_BIN=freecadcmd in .env"
fi

echo "== systemd worker"
cp "${APP_DIR}/deploy/matplace-worker.service" /etc/systemd/system/matplace-worker.service
systemctl daemon-reload
systemctl enable matplace-worker >/dev/null

echo "== nginx"
DOMAIN=${BETA_DOMAIN:-beta.matplace.com}
if [ -f /etc/letsencrypt/live/${DOMAIN}/fullchain.pem ]; then
    cp "${APP_DIR}/deploy/nginx-matplace-app.conf" /etc/nginx/sites-available/matplace-app
else
    # No certificate yet (DNS not pointed / certbot not run): serve plain HTTP so the app can be smoke-tested.
    cat > /etc/nginx/sites-available/matplace-app <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 120M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
    echo "HTTP-only vhost for ${DOMAIN}. After DNS points here: certbot --nginx -d ${DOMAIN} && cp deploy/nginx-matplace-app.conf /etc/nginx/sites-available/matplace-app && nginx -t && systemctl reload nginx"
fi
ln -sf /etc/nginx/sites-available/matplace-app /etc/nginx/sites-enabled/matplace-app
nginx -t && systemctl reload nginx

echo "done. Next: deploy/deploy.sh"
