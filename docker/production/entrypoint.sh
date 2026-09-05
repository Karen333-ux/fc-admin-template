#!/bin/sh
#
# بيتنفّذ عند كل إقلاع للكونتينر، قبل ما أي عملية تشتغل.
#
# ⚠️ الترتيب هنا هو نفس عقد deploy/deploy.sh (docs/14 بند ٣) ولا يتغيّر:
#      migrate قبل authorization:sync   (الجداول لازم تكون موجودة)
#      optimize:clear قبل بناء الكاش     (وإلا الكاش القديم بيفضل)
#
# اللي مش هنا بالقصد: `artisan down/up` — الكونتينر ده لسه ما استقبلش أي
# طلب، فمفيش حد يحتاج يشوف صفحة صيانة. و`git pull` و`composer install`
# و`npm run build` كلهم اتعملوا وقت بناء الصورة.
set -e

echo "→ انتظار قاعدة البيانات"
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int)(getenv("DB_PORT") ?: 5432)) ? 0 : 1);' 2>/dev/null; do
    sleep 2
done

echo "→ الميجريشنز"
php artisan migrate --force

echo "→ مزامنة الصلاحيات"
php artisan authorization:sync

echo "→ رابط التخزين"
php artisan storage:link || true

echo "→ مسح الكاش القديم"
php artisan optimize:clear

echo "→ بناء الكاش"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:optimize
php artisan icons:cache

echo "✔ جاهز — تشغيل العمليات"
exec supervisord -c /etc/supervisord.conf
