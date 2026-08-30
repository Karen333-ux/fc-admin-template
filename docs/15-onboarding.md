# ١٥ — نظام الأونبوردنج (الأهم)

## ليه ده أهم جزء

القالب ده هيتبني عليه أنظمة كتير. كل نظام جديد بيتسلّم لعميل، والعميل بيدخل أول مرة ويلاقي لوحة فاضية ومش عارف يبدأ منين. النتيجة: تذاكر دعم، تدريب متكرر، وانطباع سيئ.

**الحل:** باكدج داخلي واحد نضيفه لأي مشروع، فيبقى فيه أونبوردنج كامل من غير ما نعيد كتابته كل مرة.

> الأونبوردنج مش «جولة إرشادية». هو **٤ طبقات** مختلفة، كل واحدة بتحل مشكلة مختلفة.

---

## ١. الطبقات الأربع

| الطبقة | بتحل | متى تظهر |
|---|---|---|
| **١. معالج الإعداد** (Setup Wizard) | النظام فاضي ومحتاج بيانات أساسية | أول دخول للمؤسسة |
| **٢. قائمة المهام** (Checklist) | المستخدم مش عارف الخطوات الباقية | دايماً لحد ما تكتمل |
| **٣. الجولات الإرشادية** (Tours) | مش عارف الشاشة دي بتعمل إيه | أول زيارة لكل شاشة |
| **٤. المساعدة السياقية** (Contextual Help) | سؤال محدد عن حقل محدد | عند الطلب |

---

## ٢. القرار: باكدج داخلي

بدل ما نعتمد على باكدج خارجي صيانته ضعيفة، نبني **`future-code/filament-onboarding`** كباكدج داخلي.

> `jibaymcs/filament-tour` موجود ويشتغل، بس المطوّر أعلن إنه مش قادر يكمل صيانته. ممكن نستخدمه كمرجع للأفكار، بس **مش** كاعتماد أساسي في نظام هنسلّمه لعملاء.

### بنية الباكدج

```
packages/future-code/filament-onboarding/
├── composer.json
├── config/fc-onboarding.php
├── database/migrations/
│   ├── create_onboarding_progress_table.php
│   └── create_onboarding_dismissals_table.php
├── resources/
│   ├── views/
│   │   ├── wizard.blade.php
│   │   ├── checklist-widget.blade.php
│   │   ├── tour-driver.blade.php
│   │   └── help-popover.blade.php
│   ├── js/tour.js
│   ├── css/onboarding.css
│   └── lang/{ar,en}/onboarding.php
├── src/
│   ├── FilamentOnboardingPlugin.php
│   ├── OnboardingServiceProvider.php
│   ├── Contracts/
│   │   ├── OnboardingStep.php
│   │   └── OnboardingTour.php
│   ├── Steps/                        # خطوات جاهزة
│   ├── Tours/
│   │   ├── Tour.php
│   │   └── TourStep.php
│   ├── Widgets/OnboardingChecklistWidget.php
│   ├── Pages/SetupWizard.php
│   ├── Livewire/TourRunner.php
│   ├── Models/OnboardingProgress.php
│   └── Facades/Onboarding.php
└── tests/
```

`composer.json` للباكدج:

```json
{
  "name": "future-code/filament-onboarding",
  "description": "نظام أونبوردنج كامل للوحات Filament — كود المستقبل",
  "require": {
    "php": "^8.4",
    "filament/filament": "^5.0"
  },
  "autoload": {
    "psr-4": { "FutureCode\\Onboarding\\": "src/" }
  },
  "extra": {
    "laravel": {
      "providers": ["FutureCode\\Onboarding\\OnboardingServiceProvider"]
    }
  }
}
```

في المشروع الرئيسي:

```json
"repositories": [
  { "type": "path", "url": "packages/future-code/filament-onboarding" }
],
"require": { "future-code/filament-onboarding": "@dev" }
```

---

## ٣. الطبقة ١ — معالج الإعداد

أول ما مؤسسة جديدة تتعمل، أول دخول بيوجّه لمعالج إلزامي.

```php
namespace FutureCode\Onboarding\Contracts;

interface OnboardingStep
{
    public static function key(): string;
    public function label(): string;
    public function description(): string;
    public function icon(): string;

    /** هل الخطوة دي مكتملة؟ */
    public function isComplete(): bool;

    /** هل هي إلزامية للمتابعة؟ */
    public function isRequired(): bool;

    /** لينك الشاشة اللي بتكمّل الخطوة */
    public function url(): ?string;

    /** الصلاحية المطلوبة — لو مالوش، الخطوة مش بتظهر له */
    public function permission(): ?string;

    /** ترتيب العرض */
    public function sort(): int;
}
```

خطوة نموذجية:

```php
namespace Src\Contexts\Tenancy\Application\Onboarding;

final class ConfigureBrandingStep implements OnboardingStep
{
    public static function key(): string { return 'branding'; }

    public function label(): string { return __('onboarding.steps.branding.label'); }

    public function description(): string { return __('onboarding.steps.branding.description'); }

    public function icon(): string { return 'heroicon-o-paint-brush'; }

    public function isComplete(): bool
    {
        $appearance = app(AppearanceSettings::class);

        return $appearance->logo_light_path !== null
            && $appearance->primary_color !== '#12454F';   // غيّر اللون الافتراضي
    }

    public function isRequired(): bool { return false; }

    public function url(): ?string { return ManageAppearance::getUrl(); }

    public function permission(): ?string { return 'settings.manage_appearance'; }

    public function sort(): int { return 20; }
}
```

التسجيل في `config/fc-onboarding.php`:

```php
return [
    'enabled' => true,

    'wizard' => [
        'enabled'  => true,
        'route'    => '/setup',
        'skippable'=> false,     // لو false: مايقدرش يدخل اللوحة قبل ما يخلص الإلزامي
    ],

    'steps' => [
        \Src\Contexts\Tenancy\Application\Onboarding\CreateOrganizationStep::class,
        \Src\Contexts\Tenancy\Application\Onboarding\ConfigureBrandingStep::class,
        \Src\Contexts\Identity\Application\Onboarding\InviteTeamStep::class,
        \Src\Contexts\Settings\Application\Onboarding\ConfigureMailStep::class,
        \Src\Contexts\Settings\Application\Onboarding\ConfigureStorageStep::class,
    ],

    'checklist' => [
        'enabled'          => true,
        'widget_sort'      => -10,          // فوق كل الودجتس
        'hide_when_complete'=> true,
        'dismissible'      => true,
    ],

    'tours' => [
        'enabled'     => true,
        'auto_start'  => true,              // تبدأ تلقائياً أول زيارة
        'show_replay' => true,              // زرار «أعد الجولة» في قائمة المساعدة
    ],
];
```

### شاشة المعالج

- شريط تقدّم واضح («٢ من ٥»)
- خطوة واحدة في الشاشة
- زرار «تخطّي» للخطوات الاختيارية بس
- كل خطوة بتتحقق من اكتمالها فعلياً — مش مجرد «ضغط التالي»
- شاشة تهنئة في الآخر مع اقتراح أول ٣ حاجات يعملها

---

## ٤. الطبقة ٢ — ودجت قائمة المهام

بعد المعالج، الودجت بتفضل في لوحة المعلومات لحد ما كل الخطوات تكتمل.

```php
namespace FutureCode\Onboarding\Widgets;

final class OnboardingChecklistWidget extends Widget
{
    protected static string $view = 'fc-onboarding::checklist-widget';
    protected static ?int $sort = -10;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (! config('fc-onboarding.checklist.enabled')) {
            return false;
        }

        $progress = Onboarding::progress();

        if ($progress->isDismissed()) {
            return false;
        }

        return ! (config('fc-onboarding.checklist.hide_when_complete') && $progress->isComplete());
    }

    public function getSteps(): Collection
    {
        return Onboarding::stepsFor(auth()->user());
    }

    public function dismiss(): void
    {
        Onboarding::progress()->dismiss();
        $this->dispatch('$refresh');
    }
}
```

العرض:
- شريط تقدّم بنسبة مئوية
- كل خطوة: أيقونة + عنوان + وصف قصير + زرار «ابدأ» أو علامة ✔
- الخطوات المكتملة تحت والباقية فوق
- زرار «إخفاء» (بيتخزّن لكل مستخدم)
- رسالة تهنئة عند الاكتمال

> **معيار قبول:** الودجت بتقرا الحالة الفعلية من النظام — مش من عمود `completed` المستخدم ضغط عليه. لو حد حذف اللوجو، الخطوة ترجع غير مكتملة.

---

## ٥. الطبقة ٣ — الجولات الإرشادية

```php
namespace Src\Contexts\Identity\Presentation\Tours;

use FutureCode\Onboarding\Tours\Tour;
use FutureCode\Onboarding\Tours\TourStep;

final class UsersListTour extends Tour
{
    public static function key(): string { return 'users.list'; }

    /** الصفحة اللي الجولة بتشتغل عليها */
    public static function page(): string { return ListUsers::class; }

    public function steps(): array
    {
        return [
            TourStep::make('search')
                ->element('.fi-ta-search-field')
                ->title(__('onboarding.tours.users.search.title'))
                ->body(__('onboarding.tours.users.search.body'))
                ->placement('bottom'),

            TourStep::make('filters')
                ->element('.fi-ta-filters')
                ->title(__('onboarding.tours.users.filters.title'))
                ->body(__('onboarding.tours.users.filters.body'))
                ->placement('bottom-start'),

            TourStep::make('columns')
                ->element('[data-fc-tour="column-toggle"]')
                ->title(__('onboarding.tours.users.columns.title'))
                ->body(__('onboarding.tours.users.columns.body')),

            TourStep::make('create')
                ->element('[data-fc-tour="create-user"]')
                ->title(__('onboarding.tours.users.create.title'))
                ->body(__('onboarding.tours.users.create.body'))
                ->permission('create.users'),      // ← الخطوة بتتخطى لو مالوش صلاحية
        ];
    }
}
```

### قواعد الجولات

1. **٥ خطوات كحد أقصى.** أكتر من كده محدش بيكمّل.
2. **الخطوات بتحترم الصلاحيات** — لو الزرار مش ظاهر، الخطوة بتتخطى.
3. **قابلة للإيقاف في أي وقت** — `Esc` أو زرار «تخطّي».
4. **متتكررش** — تظهر مرة واحدة، والحالة في DB مش localStorage (عشان تنتقل بين الأجهزة).
5. **قابلة للإعادة** من قائمة المساعدة.
6. **RTL كامل** — السهم والموضع ينعكسوا في العربي.
7. **العنصر لازم يكون موجود** — لو مش موجود، الخطوة تتخطى بصمت مش تكسر الجولة.

### التنفيذ التقني

استخدم `driver.js` (مكتبة صغيرة، بدون اعتماديات، ~5KB) مغلّفة في مكوّن Livewire.

> ⚠️ **RTL غير مؤكد:** `driver.js` **مفيهاش خيار `rtl` أو `direction` موثّق**. `jibaymcs/filament-tour` مبني عليها فبيرث نفس القيد. يعني دعم RTL شغلنا إحنا:
> - فئة `.fc-tour--rtl` بتقلب موضع الـ popover والسهم بـ CSS
> - أزرار «التالي/السابق» بتتبدّل أماكنها
> - **مهمة أسبوع ٥:** اختبار عملي للـ RTL على ٣ شاشات مختلفة قبل ما نعتمد المكتبة. لو رسبت، البديل: نكتب الـ tour runner بنفسنا (~١٥٠ سطر بـ Floating UI) — مش صعب والتحكم أفضل.


```js
// resources/js/tour.js
import { driver } from 'driver.js';

window.fcStartTour = (steps, { rtl = false } = {}) => {
    driver({
        showProgress: true,
        allowClose: true,
        nextBtnText: window.fcTrans.next,
        prevBtnText: window.fcTrans.prev,
        doneBtnText: window.fcTrans.done,
        popoverClass: rtl ? 'fc-tour fc-tour--rtl' : 'fc-tour',
        steps: steps.filter(s => document.querySelector(s.element)),
        onDestroyed: () => Livewire.dispatch('fc-tour-completed', { key: steps.key }),
    }).drive();
};
```

> ✅ نزّل `driver.js` عبر npm واحزمه مع Vite — **مش** من CDN. أي اعتماد على CDN بيكسر التطبيق في بيئة مقفولة.

---

## ٦. الطبقة ٤ — المساعدة السياقية

أبسط طبقة وأكتر واحدة بتستخدم.

```php
TextInput::make('code')
    ->label(__('identity.fields.code'))
    ->helperText(__('identity.help.code'))
    ->hintIcon('heroicon-o-question-mark-circle', tooltip: __('identity.hint.code'));

Section::make(__('settings.sections.storage'))
    ->description(__('settings.help.storage'))
    ->icon('heroicon-o-server');
```

بالإضافة لـ:
- **قائمة مساعدة** في التوب‑بار: «إعادة الجولة» + «الأسئلة الشائعة» + «تواصل مع الدعم»
- **حالات فارغة تعليمية** — بدل «لا توجد بيانات»، اكتب «ابدأ بإضافة أول مستخدم» + زرار + لينك شرح

> **قاعدة:** أي حقل المستخدم ممكن يتردد فيه لازم له `helperText` أو `hintIcon`. لو مطوّر سأل «الحقل ده معناه إيه؟» — يبقى المستخدم هيسأل كمان.

---

## ٧. تتبّع التقدّم

```php
Schema::create('onboarding_progress', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
    $table->json('completed_steps')->default('[]');
    $table->json('completed_tours')->default('[]');
    $table->boolean('checklist_dismissed')->default(false);
    $table->timestamp('wizard_completed_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'tenant_id']);
});
```

### المقاييس المهمة

الباكدج بيسجّل الأحداث دي عشان نعرف الأونبوردنج شغّال ولا لأ:

- نسبة من أكمل المعالج
- متوسط الوقت لإكمال كل خطوة
- الخطوة اللي أكتر ناس بيقفوا عندها ← **دي أهم رقم**
- نسبة من أكمل كل جولة ونسبة من تخطاها
- نسبة من أخفى قائمة المهام قبل ما يكملها

صفحة Filament بتعرض المقاييس دي بصلاحية `access.onboarding_analytics`.

> لو ٧٠٪ بيقفوا عند خطوة معيّنة، الخطوة دي مش واضحة — مش المستخدمين غلط.

---

## ٨. أونبوردنج المطوّر (منفصل)

الباكدج للمستخدم النهائي. المطوّر الجديد له مسار تاني:

1. `README.md` في الجذر — الملف اللي إنت بتقراه أخوه
2. `docs/01-architecture-ddd.md` و `docs/18-conventions.md` — إلزامي
3. `docs/16-adding-a-feature.md` — الوصفة
4. **مهمة اليوم الأول:** يضيف ميزة صغيرة كاملة بالوثائق لوحده. لو قدر، الوثائق شغّالة. لو مقدرش، **الوثائق ناقصة** — يكتب فين وقف ونصلّحها.
5. `CLAUDE.md` — لو بيستخدم Claude Code

---

## ٩. معايير القبول

- [ ] باكدج `future-code/filament-onboarding` منفصل وقابل للتثبيت في أي مشروع
- [ ] المعالج شغّال ولا يمرّ إلا باكتمال الخطوات الإلزامية فعلياً
- [ ] الخطوات بتتحقق من الحالة الحقيقية مش من علامة يدوية
- [ ] ودجت قائمة المهام بتظهر وتختفي صح وبتحترم الصلاحيات
- [ ] الجولات ≤ ٥ خطوات، بتحترم الصلاحيات، وبتتخطى العناصر المفقودة بصمت
- [ ] الجولات شغّالة RTL بنفس جودة LTR
- [ ] حالة الجولات في DB مش localStorage
- [ ] `driver.js` محزوم مع Vite مش من CDN
- [ ] كل حقل ملتبس له `helperText` أو `hintIcon`
- [ ] كل الحالات الفارغة تعليمية مش «لا توجد بيانات»
- [ ] صفحة المقاييس شغّالة وبتعرض نقطة التوقّف
- [ ] كل نصوص الباكدج مترجمة عربي وإنجليزي
- [ ] **الاختبار النهائي:** مستخدم من بره الفريق يدخل النظام لأول مرة ويوصل لأول مهمة مفيدة في أقل من ١٠ دقايق من غير ما يسأل حد
