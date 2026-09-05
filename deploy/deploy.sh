#!/usr/bin/env bash
#
# سكربت النشر للإنتاج. (docs/14 بند ٣)
#
# ⚠️ $DEPLOY_SECRET متغيّر بيئة shell بيضبطه سيرفر النشر وقت التنفيذ —
#    مش مفتاح Laravel .env، ومفيش تسجيل ليه في .env.example بالقصد.
#
# ⚠️ الترتيب ده عقد لا يتغيّر (docs/14 بند ٣):
#    - migrate قبل authorization:sync (الجداول لازم تكون موجودة)
#    - optimize:clear قبل بناء الكاش الجديد
#    - horizon:terminate بعد كل حاجة (عشان العمال ياخدوا الكود الجديد)
#    - php artisan up آخر حاجة
set -euo pipefail

echo "→ وضع الصيانة"
php artisan down --render="errors::503" --retry=60 --secret="$DEPLOY_SECRET"

echo "→ سحب الكود"
git pull origin main

echo "→ الاعتماديات"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ الأصول"
npm ci
npm run build

echo "→ الميجريشنز"
php artisan migrate --force

echo "→ مزامنة الصلاحيات"
php artisan authorization:sync

echo "→ مسح الكاش القديم"
php artisan optimize:clear

echo "→ بناء الكاش"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:optimize
php artisan icons:cache

echo "→ إعادة تشغيل العمال"
php artisan horizon:terminate
php artisan queue:restart

echo "→ إنهاء الصيانة"
php artisan up

echo "✔ تم النشر"
