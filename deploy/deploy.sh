#!/usr/bin/env bash
# Deploy the current git branch to the server (run locally, Git Bash).
#   bash deploy/deploy.sh            # deploys HEAD of the current branch
#
# Flow: git push → server git pull → composer → npm build → migrate → restart worker + php-fpm.
# Never touches /var/www/matplace (legacy) or its DB.
set -euo pipefail

HOST=${DEPLOY_HOST:-root@178.104.162.164}
KEY=${DEPLOY_KEY:-~/.ssh/hetzner_matplace}
APP_DIR=/var/www/matplace-app
BRANCH=$(git rev-parse --abbrev-ref HEAD)
REPO=${DEPLOY_REPO:-$(git remote get-url origin 2>/dev/null || echo "")}

if [ -z "$REPO" ]; then
    echo "No git remote 'origin'. Create the GitHub repo first (e.g. git@github.com:RVRCZ/matplace-app.git) and add it as origin."
    exit 1
fi

git push -u origin "$BRANCH"

ssh -i "$KEY" -o StrictHostKeyChecking=no "$HOST" bash -s <<EOF
set -euo pipefail
if [ ! -d ${APP_DIR}/.git ]; then
    git clone --branch ${BRANCH} ${REPO} ${APP_DIR}
fi
cd ${APP_DIR}
git fetch --all --quiet
git checkout --quiet ${BRANCH}
git reset --hard --quiet origin/${BRANCH}
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent && npm run build --silent
[ -f .env ] || { cp .env.example .env; php artisan key:generate --force; echo "!! fill in .env (DB, ORCA_*, PYTHON_BIN=/opt/matplace-py/bin/python)"; }
php artisan migrate --force
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
systemctl restart matplace-worker
systemctl restart php8.2-fpm
php artisan about --only=environment | head -5
EOF
echo "deployed ${BRANCH} to ${HOST}:${APP_DIR}"
