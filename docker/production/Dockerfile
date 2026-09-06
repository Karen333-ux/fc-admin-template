# صورة الإنتاج — nginx + php-fpm + Horizon + المجدول في كونتينر واحد.
#
# ⚠️ دي مش docker/php/Dockerfile. اللي هناك للتطوير: بياخد الكود من الـ
#    bind mount في docker-compose.yml، ومفيهوش لا nginx ولا بناء أصول ولا
#    عمال. الصورة دي بتنسخ الكود جواها وبتتبني في Coolify.

# ─── ١) الأساس: PHP بامتداداته ──────────────────────────────────────
#
# المرحلة دي مشتركة بين تثبيت الحزم والصورة النهائية عن قصد. صورة
# composer الرسمية فيها PHP من غير intl ولا pcntl ولا exif، وحزم المشروع
# بتطلبهم كـ platform requirements — فcomposer بيرفض التثبيت عليها.
#
# البديل الشائع هو --ignore-platform-reqs، لكنه بيلغي التحقق أصلاً بدل ما
# يرضيه. تثبيت الحزم بنفس الـ PHP اللي هتشغّلها بيخلي التحقق حقيقي: لو
# امتداد ناقص، البناء بيقع هنا بدل ما التطبيق يقع وقت التشغيل.
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        nginx supervisor postgresql-client \
        postgresql-dev icu-dev libzip-dev oniguruma-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql intl zip gd bcmath exif pcntl opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# ─── ٢) الاعتماديات ─────────────────────────────────────────────────
FROM base AS vendor
WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# طبقة منفصلة للاعتماديات عشان ما تتعادش مع كل تعديل في الكود.
# --no-scripts هنا لأن سكربتات Laravel محتاجة الكود كامل وهو لسه مش منسوخ.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
# المرة دي بالسكربتات: post-autoload-dump بيشغّل filament:upgrade اللي بينشر
# أصول Filament في public/. الأصول دي متجاهلة في .gitignore بالقصد لأنها
# مولّدة، فلو ما اتنشرتش هنا مش هتبقى موجودة في الصورة خالص.
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ─── ٣) الأصول ──────────────────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
# vite بيقرا مسارات من جوه vendor (Filament و Tailwind)، فلازم يكون موجود
# قبل البناء وإلا الـ manifest بيطلع ناقص.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ─── ٤) الصورة النهائية ─────────────────────────────────────────────
FROM base

COPY docker/production/php-fpm.conf     /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/production/opcache.ini      /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/production/nginx.conf       /etc/nginx/http.d/default.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh    /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/public ./public
COPY . .
COPY --from=assets /app/public/build ./public/build

# الكونتينر بيشتغل بـ www-data، فالمجلدين اللي Laravel بيكتب فيهم لازم
# يكونوا ملكه. الباقي بيفضل ملك root — الكود مش المفروض يعدّل نفسه.
RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
