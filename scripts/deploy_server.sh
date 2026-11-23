#!/bin/bash
# Deploy of main (PHP + Blade + lang + config + routes + TS): no migration, so no maintenance mode.
# opcache.validate_timestamps=0 on production: optimize + reload php-fpm + queue:restart are what makes PHP changes live.
set -u
cd /var/www/matplace-app || exit 1
git fetch -q origin
git status --short | grep -vE "__pycache__|result.json|:memory:.ses" | grep -q . && { echo "LOCAL CHANGES, stop"; exit 1; }
git merge-tree --write-tree HEAD origin/main >/dev/null || { echo "MERGE CONFLICT, stop"; exit 1; }
echo "server before: $(git log --oneline -1)"; echo "origin/main:   $(git log --oneline -1 origin/main)"
# the dump is kept compressed (17 Nov 2026: 36 plain dumps of 350 MB took 12 GB of a 75 GB disk; gzip makes a sixth of them)
TS=$(date +%Y%m%d-%H%M%S); cp .env /root/env-$TS.bak; ( set -o pipefail; mysqldump matplace_app | gzip -6 > /root/matplace_app-$TS.sql.gz ) || { echo "DUMP FAILED, stop"; rm -f /root/matplace_app-$TS.sql.gz; exit 1; }
# retention (Roman 1 Nov 2026: old deploy dumps may go): the 5 newest dumps stay, whatever their age - ten deploys
# in one night left 71 dumps (4.6 GB) and the disk ran full on 23 Nov 2026
ls -1t /root/matplace_app-*.sql.gz /root/matplace_app-*.sql 2>/dev/null | tail -n +6 | xargs -r rm -f; find /root -maxdepth 1 -name 'env-*.bak' -mtime +60 -delete
echo "dumps kept: $(ls /root/matplace_app-*.sql* | wc -l), disk: $(df -h / | awk 'NR==2 {print $4" free, "$5}')"
git -c user.name="matplace deploy" -c user.email="deploy@matplace.com" merge -q --no-edit origin/main || { echo "MERGE FAILED"; git merge --abort; exit 1; }
composer install --no-dev -q -n || { echo "COMPOSER FAILED"; exit 1; }
npm ci --silent && npm run build 2>&1 | grep -E "error|built in|bundle ok"
php artisan migrate --force 2>&1 | tail -3
php artisan view:clear -q; php artisan optimize -q
chown -R www-data:www-data storage bootstrap/cache public/build public/img
systemctl reload php8.2-fpm
php artisan queue:restart
echo "server after:  $(git log --oneline -1)"
for p in / /tools /models /model /en/ /es/ /farm /csp-report; do printf "%s %s\n" "$(curl -s -o /dev/null -w '%{http_code}' -X $([ "$p" = /csp-report ] && echo POST || echo GET) https://matplace.com$p)" "$p"; done
curl -s -I https://matplace.com/ | grep -i "content-security\|permissions-policy\|reporting-endpoints" | cut -c1-120
