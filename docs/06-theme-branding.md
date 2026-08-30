# ٠٦ — الثيم وهوية كود المستقبل

## الهدف

نبعد تماماً عن شكل Filament الافتراضي. اللوحة لازم تبان **بتاعتنا**: بهيجة، احترافية، هادية، وشغّالة في الوضعين الفاتح والداكن، وعربي وإنجليزي.

المرجع الأساسي: وثيقة **«توكنز الخطوط والألوان»** في قاعدة معرفة المشروع. الملف ده بيترجمها لكود.

---

## ١. القاعدة الحاكمة: طبقتان

| الطبقة | يتغيّر؟ | المصدر |
|---|---|---|
| **طبقة العلامة** | ✅ لكل مستأجر | `AppearanceSettings::primary_color` — قيمة واحدة بس (الدرجة 600) |
| **طبقة المنتج** | ❌ ثابتة | المحايدات، ألوان الحالة، ألوان التصنيف، الخطوط |

> **ممنوع** استخدام لون العلامة كلون حالة أو العكس. فيه مستأجر لونه الأساسي أحمر — لو الأحمر عندك يعني «خطر»، هتبقى كارثة.
>
> **ممنوع** أي لون مكتوب مباشرة في مكوّن. كله من متغيّرات CSS.

---

## ٢. سُلَّم لون العلامة

الافتراضي = بترولي كود المستقبل:

| 50 | 100 | 200 | 300 | 400 | 500 | **600** | 700 | 800 | 900 |
|---|---|---|---|---|---|---|---|---|---|
| `#EAF1F2` | `#CFE0E2` | `#A9C6CA` | `#7FA5AB` | `#4E7A80` | `#2D616A` | **`#12454F`** | `#0E3841` | `#0A2B32` | `#071E23` |

المؤسسة بتضبط **600 بس**؛ الباقي مُشتَق برمجياً.

### مولّد السُّلَّم

```php
namespace Src\Support\Infrastructure\Theming;

final class ColorScaleGenerator
{
    /** يولّد سُلَّم 50→900 من درجة 600 باستخدام OKLCH */
    public function generate(string $hex): array
    {
        $base = $this->hexToOklch($hex);

        // القيم دي معايرة على البترولي — راجعها لو غيّرنا الأساس
        $lightness = [
            50 => 0.96, 100 => 0.90, 200 => 0.80, 300 => 0.68,
            400 => 0.55, 500 => 0.46, 600 => $base['l'],
            700 => $base['l'] * 0.83, 800 => $base['l'] * 0.66, 900 => $base['l'] * 0.48,
        ];

        $chromaFalloff = [
            50 => 0.18, 100 => 0.32, 200 => 0.52, 300 => 0.72,
            400 => 0.92, 500 => 1.0, 600 => 1.0,
            700 => 0.94, 800 => 0.86, 900 => 0.74,
        ];

        return collect($lightness)
            ->map(fn (float $l, int $shade) => $this->oklchToHex(
                $l,
                $base['c'] * $chromaFalloff[$shade],
                $base['h'],
            ))
            ->all();
    }
}
```

> **معيار قبول:** الدرجات 600 و700 لازم يعدّوا تباين ≥ 4.5:1 على أبيض. اكتب اختبار Pest بيفحص ده لأي لون المستخدم يدخله، ويرفض الحفظ لو رسب.

---

## ٣. المحايدات وألوان الحالة (ثابتة — متغيّرهاش)

### فاتح
| الدور | القيمة | التباين |
|---|---|---|
| خلفية | `#F2F5F4` | — |
| سطح | `#FFFFFF` | — |
| غائر | `#E9EFEE` | — |
| حدّ | `#D8E1E0` | — |
| حدّ خفيف | `#E8EEED` | — |
| نص | `#16292B` | 15.1:1 |
| نص ٢ | `#4E6260` | 6.5:1 |
| نص خافت | `#667877` | 4.7:1 |

### داكن
| الدور | القيمة | التباين |
|---|---|---|
| خلفية | `#0C1415` | — |
| سطح | `#141F20` | — |
| غائر | `#0F1A1B` | — |
| حدّ | `#263536` | — |
| حدّ خفيف | `#1D2A2B` | — |
| نص | `#E7EEED` | 14.2:1 |
| نص ٢ | `#A6B8B7` | 8.1:1 |
| نص خافت | `#7E9291` | 5.1:1 |

### ألوان الحالة
| الحالة | فاتح | داكن |
|---|---|---|
| نجاح | `#0F7A3D` (5.4:1) | `#4ADE80` (9.6:1) |
| تحذير | `#96550A` (5.8:1) | `#FBBF24` (10.0:1) |
| خطر | `#B4232E` (6.5:1) | `#F87171` (6.0:1) |
| معلومة | `#0E6E8C` (5.8:1) | `#38BDF8` (7.8:1) |

> ⚠️ **تحذير مهم:** القيم `#4ADE80 / #FBBF24 / #F87171` هي قيم **الوضع الداكن**. تباينها على الأبيض 1.74:1 و1.67:1 و2.77:1 — والحد الأدنى 3:1. **متستخدمهاش في الفاتح إطلاقاً.**

### ألوان التصنيف (٦ درجات مثبّتة)

| | فيروزي | قرميدي | نيلي | عنبري | برقوقي | زيتوني |
|---|---|---|---|---|---|---|
| فاتح | `#00908A` | `#A32E3C` | `#2E5AAC` | `#A55A00` | `#9B2C6F` | `#5D7A00` |
| داكن | `#189B95` | `#D1555F` | `#5B85D6` | `#BE7A2E` | `#C55C9C` | `#7B9C33` |

كلها مرّت على فحص عمى الألوان (بروتان/ديوتان/تريتان) وتباين ≥3:1 في الوضعين.

**شرط:** اللون ما يقفش لوحده — دايماً جنبه اسم التصنيف.
**شارة التصنيف:** خلفية درجة 50 + نص درجة 700. **مش** نفس اللون بشفافية 20% (غير مقروء مع الألوان الفاتحة).

---

## ٤. الخطوط

| الدور | الخط | الأوزان | الاستخدام |
|---|---|---|---|
| الواجهة | **IBM Plex Sans Arabic** | 300/400/500/600/700 | كل اللوحة — عربي ولاتيني بنفس العائلة |
| الوثائق | **Noto Naskh Arabic** | 400/600/700 | المستندات المطبوعة فقط |
| الأرقام | **IBM Plex Mono** | 400/500 + `tabular-nums` | الأكواد، الفواتير، الأرقام، العدّادات |

### سُلَّم المقاسات
```
display 32/44 · h1 24/35 · h2 20/30 · h3 17/26
body 15/27 · small 13.5/23 · label 12/18 · mono 13/20
```

### قواعد عربية إلزامية
- ❌ **ممنوع** `letter-spacing` على العربي إطلاقاً (بيقطع اتصال الحروف)
- ❌ **ممنوع** وزن 300 تحت 15px بالعربي (النقاط بتختفي)
- ✅ الارتفاع السطري أعلى بـ 0.15–0.2 من اللاتيني
- ✅ أرقام لاتينية (0–9) في الواجهة، هندية (٠–٩) في الوثائق فقط
- ✅ `start`/`end` مش `left`/`right`
- ✅ الأكواد اللاتينية جوه نص عربي محتاجة `unicode-bidi: isolate`

---

## ٥. إنشاء الثيم

```bash
php artisan make:filament-theme admin
npm install
```

بيولّد `resources/css/filament/admin/theme.css` ويظبط Vite تلقائياً.

في `AdminPanelProvider`:

```php
use Filament\FontProviders\GoogleFontProvider;
use Filament\Support\Colors\Color;

return $panel
    ->viteTheme('resources/css/filament/admin/theme.css')
    ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
    ->monoFont('IBM Plex Mono', provider: GoogleFontProvider::class)
    ->colors(fn () => app(ThemeColorResolver::class)->palette())
    ->brandName(fn () => app(GeneralSettings::class)->app_name[app()->getLocale()])
    ->brandLogo(fn () => app(BrandLogoResolver::class)->url())
    ->brandLogoHeight('2.25rem')
    ->favicon(fn () => app(AppearanceSettings::class)->favicon_path)
    ->darkMode(fn () => app(AppearanceSettings::class)->allow_theme_switch)
    ->defaultThemeMode(fn () => ThemeMode::from(app(AppearanceSettings::class)->default_theme));
```

`ThemeColorResolver` بيرجّع مصفوفة Filament متوقعها:

```php
final class ThemeColorResolver
{
    public function palette(): array
    {
        $scale = app(ColorScaleGenerator::class)
            ->generate(app(AppearanceSettings::class)->primary_color);

        return [
            'primary' => $scale,
            'gray'    => $this->brandNeutrals(),   // المحايدات البترولية بتاعتنا
            'success' => Color::hex('#0F7A3D'),
            'warning' => Color::hex('#96550A'),
            'danger'  => Color::hex('#B4232E'),
            'info'    => Color::hex('#0E6E8C'),
        ];
    }
}
```

---

## ٦. ملف الثيم

`resources/css/filament/admin/theme.css`:

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

/* Tailwind v4: نحدد المصادر صراحةً — Filament مبقاش بيمسح views بتاعتنا تلقائياً */
@source '../../../../app/**/*.php';
@source '../../../../src/**/*.php';
@source '../../../../resources/views/**/*.blade.php';
@source '../../../../vendor/filament/**/*.blade.php';

@theme {
  /* ═══ الخطوط ═══ */
  --font-sans: 'IBM Plex Sans Arabic', 'Segoe UI', system-ui, sans-serif;
  --font-mono: 'IBM Plex Mono', 'SFMono-Regular', monospace;

  /* ═══ سُلَّم المقاسات ═══ */
  --text-display: 2rem;      --text-display--line-height: 2.75rem;
  --text-h1: 1.5rem;         --text-h1--line-height: 2.1875rem;
  --text-h2: 1.25rem;        --text-h2--line-height: 1.875rem;
  --text-h3: 1.0625rem;      --text-h3--line-height: 1.625rem;
  --text-body: 0.9375rem;    --text-body--line-height: 1.6875rem;
  --text-small: 0.84375rem;  --text-small--line-height: 1.4375rem;
  --text-label: 0.75rem;     --text-label--line-height: 1.125rem;

  /* ═══ الانحناءات والظلال ═══ */
  --radius-fc-sm: 0.375rem;
  --radius-fc:    0.625rem;
  --radius-fc-lg: 0.875rem;
  --shadow-fc-card: 0 1px 2px 0 rgb(22 41 43 / 0.04),
                    0 4px 12px -2px rgb(22 41 43 / 0.06);
}

/* ═══ طبقة المنتج — الوضع الفاتح ═══ */
:root {
  --fc-bg:            #F2F5F4;
  --fc-surface:       #FFFFFF;
  --fc-sunken:        #E9EFEE;
  --fc-border:        #D8E1E0;
  --fc-border-subtle: #E8EEED;
  --fc-text:          #16292B;
  --fc-text-2:        #4E6260;
  --fc-text-muted:    #667877;

  --fc-success: #0F7A3D;
  --fc-warning: #96550A;
  --fc-danger:  #B4232E;
  --fc-info:    #0E6E8C;

  --fc-cat-teal:   #00908A;
  --fc-cat-brick:  #A32E3C;
  --fc-cat-indigo: #2E5AAC;
  --fc-cat-amber:  #A55A00;
  --fc-cat-plum:   #9B2C6F;
  --fc-cat-olive:  #5D7A00;
}

/* ═══ الوضع الداكن ═══ */
.dark {
  --fc-bg:            #0C1415;
  --fc-surface:       #141F20;
  --fc-sunken:        #0F1A1B;
  --fc-border:        #263536;
  --fc-border-subtle: #1D2A2B;
  --fc-text:          #E7EEED;
  --fc-text-2:        #A6B8B7;
  --fc-text-muted:    #7E9291;

  --fc-success: #4ADE80;
  --fc-warning: #FBBF24;
  --fc-danger:  #F87171;
  --fc-info:    #38BDF8;

  --fc-cat-teal:   #189B95;
  --fc-cat-brick:  #D1555F;
  --fc-cat-indigo: #5B85D6;
  --fc-cat-amber:  #BE7A2E;
  --fc-cat-plum:   #C55C9C;
  --fc-cat-olive:  #7B9C33;
}

/* ═══ قواعد عربية ═══ */
[dir='rtl'] {
  letter-spacing: normal !important;   /* يقطع أي letter-spacing من Tailwind */
}

[dir='rtl'] body {
  line-height: 1.75;
}

/* الأرقام والأكواد جوه نص عربي */
.fc-code, .fc-number {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  unicode-bidi: isolate;
  direction: ltr;
  display: inline-block;
}

/* ═══ لمسات الشكل ═══ */
.fi-sidebar { background: var(--fc-surface); border-inline-end: 1px solid var(--fc-border-subtle); }
.fi-main    { background: var(--fc-bg); }
.fi-section, .fi-ta-ctn { border-radius: var(--radius-fc-lg); box-shadow: var(--shadow-fc-card); }
.fi-btn     { border-radius: var(--radius-fc); font-weight: 500; }
.fi-input   { border-radius: var(--radius-fc-sm); }

/* شارة التصنيف — خلفية 50 + نص 700، مش شفافية */
.fc-badge {
  border-radius: 9999px;
  padding-inline: 0.625rem;
  padding-block: 0.1875rem;
  font-size: var(--text-label);
  font-weight: 600;
}
```

### حقن اللون الديناميكي

لون العلامة بيتغيّر لكل مستأجر — فبيتحقن كـ CSS متغيّرات في `<head>` عبر render hook:

```php
FilamentView::registerRenderHook(
    PanelsRenderHook::HEAD_END,
    fn (): string => view('theme.brand-variables', [
        'scale' => app(ThemeColorResolver::class)->scale(),
    ])->render(),
);
```

```blade
{{-- resources/views/theme/brand-variables.blade.php --}}
<style>
  :root {
    @foreach ($scale as $shade => $hex)
      --fc-brand-{{ $shade }}: {{ $hex }};
    @endforeach
  }
</style>
```

> **كاش:** خزّن الـ HTML الناتج في Redis بمفتاح فيه `tenant_id` + hash للون. امسحه في `afterSave()` بتاع صفحة المظهر.

---

## ٧. الفوتر والحقوق (مطلب صريح)

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

FilamentView::registerRenderHook(
    PanelsRenderHook::FOOTER,
    fn (): string => view('theme.footer')->render(),
);
```

```blade
{{-- resources/views/theme/footer.blade.php --}}
@php
    $appearance = app(\Src\Contexts\Settings\Domain\Settings\AppearanceSettings::class);
    $locale     = app()->getLocale();
@endphp

<footer class="fc-footer">
    <div class="fc-footer__inner">
        <span class="fc-footer__copy">
            {{ $appearance->footer_text[$locale] ?? $appearance->footer_text['en'] }}
        </span>

        <span class="fc-footer__by">
            {{ __('common.built_by') }}
            <a href="https://futurecode.example" target="_blank" rel="noopener"
               class="fc-footer__link">{{ __('common.company') }}</a>
        </span>

        <span class="fc-footer__version fc-code">v{{ config('app.version') }}</span>
    </div>
</footer>
```

```css
.fc-footer {
  border-block-start: 1px solid var(--fc-border-subtle);
  padding-block: 1rem;
  padding-inline: 1.5rem;
  background: var(--fc-surface);
}
.fc-footer__inner {
  display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem;
  align-items: center; justify-content: space-between;
  font-size: var(--text-small); color: var(--fc-text-muted);
}
.fc-footer__link { color: var(--fc-brand-600); font-weight: 500; }
.fc-footer__link:hover { text-decoration: underline; }
```

> نص الحقوق **من الإعدادات ومترجم** — مش مكتوب في الـ Blade.

---

## ٨. اتجاه الصفحة (RTL)

> ⚠️ **مفيش دالة `->direction()` على الـ Panel.** لو لقيت حد كتبها، هي غلط. Filament بيحدد الاتجاه من **ملف ترجمة اللغة**.

انشر ملفات ترجمة Filament واضبط الاتجاه فيها:

```bash
php artisan vendor:publish --tag=filament-panels-translations
```

```php
// lang/vendor/filament-panels/ar/layout.php
return [
    'direction' => 'rtl',
    // ...
];
```

نفس الحاجة لباقي حزم Filament المستخدمة (`filament-forms`, `filament-tables`, `filament-actions`, `filament-notifications`) — كل واحدة فيها `direction` في ملف `layout.php` أو ما يقابله. راجع الملفات المنشورة وتأكد.

Filament v4/v5 بيدعم RTL جاهز بعد كده. المشاكل المتبقية بتيجي من كودنا إحنا:

**افحص بنفسك:**
- كل الأيقونات اللي فيها اتجاه (سهم رجوع، سهم التالي) لازم تنعكس
- الـ `border-l` / `pl-4` في أي مكوّن مخصص → `border-s` / `ps-4`
- الرسوم البيانية: المحور الرأسي على اليمين في العربي
- التواريخ والأرقام: راجع `docs/10-i18n.md`

---

## ٩. الممنوعات (من دليل الهوية)

- ❌ تدرّج أزرق → بنفسجي
- ❌ قبعة تخرّج، كتاب، لمبة، قلم كأيقونات
- ❌ صور Stock لأشخاص مبتسمين
- ❌ شبكات، عُقد، دوائر إلكترونية، أدمغة، أيقونات AI
- ❌ أي إشارة فرعونية
- ❌ أي لون مكتوب مباشرة في مكوّن

---

## ١٠. معايير القبول

- [ ] اللوحة مبتشبهش Filament الافتراضي إطلاقاً — الفرق واضح من أول نظرة
- [ ] `grep -rn "#[0-9a-fA-F]\{6\}" src/ app/ resources/views/` بيرجّع صفر نتيجة (بره ملف الثيم)
- [ ] `grep -rnE "\b(pl|pr|ml|mr|border-l|border-r|text-left|text-right)-" resources/` = صفر
- [ ] تغيير `primary_color` من الإعدادات بيغيّر كل اللوحة
- [ ] الوضع الداكن كامل ومفيش عنصر بيختفي
- [ ] الفوتر ظاهر في كل الصفحات، مترجم، وفيه رقم الإصدار
- [ ] لقطات شاشة: عربي فاتح، عربي داكن، إنجليزي فاتح، إنجليزي داكن
- [ ] اختبار تباين آلي بيرفض أي لون أساسي تباينه < 4.5:1
- [ ] الخطوط بتتحمّل من Google Fonts والـ fallback شغّال لو النت وقف
