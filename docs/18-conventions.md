# ١٨ — الاتفاقيات (إلزامي — اقرأه يوم ١)

## ١. التسمية

### PHP

| العنصر | النمط | مثال |
|---|---|---|
| كلاس | PascalCase | `PublishAnnouncementAction` |
| الواجهة | PascalCase بدون `I` | `DiskResolver` مش `IDiskResolver` |
| الـ trait | PascalCase وصفي | `BelongsToTenant` |
| Enum | PascalCase مفرد | `AnnouncementStatus` |
| حالة Enum | PascalCase | `case Published = 'published';` |
| الدالة | camelCase فعل | `handle()`, `isComplete()` |
| المتغيّر | camelCase | `$publishedAt` |
| الثابت | SCREAMING_SNAKE | `MAX_RETRIES` |
| Action | فعل + `Action` | `InviteUserAction` |
| DTO | اسم + `Data` | `InviteUserData` |
| Job | فعل + `Job` | `GenerateReportJob` |
| Event | ماضي | `AnnouncementPublished` |
| Listener | فعل وصفي | `NotifyUsersOfAnnouncement` |
| Exception | وصف + `Exception` | `MissingTenantContextException` |

### قاعدة البيانات

| العنصر | النمط | مثال |
|---|---|---|
| جدول | snake_case جمع | `announcements` |
| عمود | snake_case | `published_at` |
| مفتاح أجنبي | مفرد + `_id` | `tenant_id` |
| جدول وسيط | مفرد_مفرد أبجدياً | `tenant_user` |
| منطقي | `is_` أو `has_` | `is_pinned`, `has_2fa` |
| تاريخ | `_at` | `created_at`, `expires_at` |
| فهرس | `{table}_{cols}_index` | `announcements_tenant_id_status_index` |

### الترجمة

```
{context}::{context}.{model}.{group}.{key}
```
مثال: `content::content.announcement.fields.title`

المجموعات المعيارية: `fields`, `actions`, `status`, `sections`, `confirm`, `notifications`, `empty`, `help`, `hint`.

### الصلاحيات

```
{action}.{resource}
```
مثال: `publish.announcements`

للصفحات: `access.{page}` · للودجتس: `widget.{name}`

### CSS

- فئات مخصصة بادئتها `fc-`: `.fc-badge`, `.fc-code`, `.fc-footer`
- متغيّرات: `--fc-*`
- **ممنوع** تعديل فئات `fi-*` بتاعة Filament مباشرة إلا في ملف الثيم

---

## ٢. قواعد الكود

```php
declare(strict_types=1);       // ← في كل ملف PHP، أول سطر بعد <?php
```

- **كل كلاس `final`** إلا لو مصمّم للوراثة صراحةً
- **كل خاصية ودالة عليها type hint** — مفيش `mixed` من غير سبب مكتوب
- **الاعتماديات بالـ constructor injection** — مش `app()` جوه الدالة (إلا في Filament closures)
- **`readonly` لأي كلاس بياناته ما بتتغيّرش** (DTOs, Actions, Events)
- **`match` بدل `switch`**
- **الـ early return** بدل التداخل العميق
- **دالة أطول من ٢٠ سطر** = علامة إنها محتاجة تتقسّم
- **كلاس أطول من ٢٠٠ سطر** = علامة إنه بيعمل أكتر من حاجة

```php
// ❌
public function handle($data) {
    if ($data) {
        if ($data['status'] === 'active') {
            // ...
        }
    }
}

// ✅
public function handle(AnnouncementData $data): Announcement
{
    if ($data->status !== AnnouncementStatus::Active) {
        throw new InvalidStatusException($data->status);
    }
    // ...
}
```

---

## ٣. التعليقات

- **بالعربي** للشرح، **بالإنجليزي** للأسماء والمصطلحات
- علّق على **ليه** مش **إيه**
- كل قرار غير بديهي لازم له تعليق بسببه

```php
// ❌ بيجيب المستخدمين
$users = User::all();

// ✅ chunkById مش chunk — عشان الحذف أثناء التكرار ما يخبطش الترقيم
User::query()->chunkById(200, fn ($users) => ...);
```

PHPDoc للأنواع المعقّدة بس:

```php
/** @return Collection<int, Announcement> */
public function active(): Collection
```

---

## ٤. Git

### الفروع
```
main                    ← الإنتاج، محمي
develop                 ← التكامل
feat/<context>-<name>   ← ميزة
fix/<context>-<name>    ← إصلاح
chore/<name>            ← صيانة
docs/<name>             ← وثائق
```

### رسائل الكوميت (Conventional Commits)

```
<type>(<scope>): <description>

feat(identity): إضافة المصادقة الثنائية
fix(content): إصلاح تسريب إعلانات بين المستأجرين
docs(permissions): توثيق نمط wildcard
refactor(media): استخراج DiskResolver لواجهة
test(tenancy): اختبار عزل الصلاحيات
chore(deps): ترقية Filament لـ 5.7.6
```

**الأنواع:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`

الوصف **بالعربي**، النوع والنطاق بالإنجليزي.

### قواعد
- كوميت واحد = تغيير منطقي واحد
- **ممنوع** `git push --force` على `main` أو `develop`
- **ممنوع** رفع `.env` أو أي مفتاح
- الـ PR فيه commits نظيفة — اعمل `rebase -i` قبل الطلب

### قالب الـ PR

```markdown
## إيه اللي اتعمل
<وصف بالعربي في ٢-٣ سطور>

## ليه
<المشكلة أو المطلب>

## إزاي أجرّبه
1. ...
2. ...

## لقطات
| عربي فاتح | عربي داكن | إنجليزي |
|---|---|---|
| | | |

## معايير القبول
<انسخ القائمة من docs/17-testing-acceptance.md>
```

---

## ٥. مراجعة الكود

### المراجِع بيفحص (بالترتيب)

1. **الأمان** — صلاحية ناقصة؟ عزل مستأجر مكسور؟ تسريب بيانات؟
2. **البنية** — الطبقة صح؟ منطق في المكان الغلط؟
3. **الأداء** — N+1؟ فهرس ناقص؟ استعلام في لوب؟
4. **الترجمة** — نص مكتوب؟ `left/right`؟ لون مباشر؟
5. **الاختبارات** — موجودة؟ بتختبر الحاجة الصح؟
6. **الأسلوب** — الأخير، ومعظمه Pint بيحله

### قواعد المراجعة

- **علّق بسؤال مش بأمر:** «إيه رأيك لو استخدمنا X هنا؟» مش «غيّرها لـ X»
- **فرّق بين الإلزامي والمقترح:** ابدأ التعليق بـ `[لازم]` أو `[اقتراح]` أو `[سؤال]`
- **امدح الحلو** — لو حد كتب حاجة نضيفة، قول
- **مراجعة أقل من ٢٤ ساعة** — الـ PR المتعلّق بيوقف الفريق

### مين بيراجع

- كود إنترن → مراجعة سينيور إلزامية
- كود سينيور → مراجعة سينيور تاني
- تغيير في `src/Support` أو الصلاحيات أو التعدد → **مراجعتين**

---

## ٦. التعامل مع الاعتماديات

- **متضيفش باكدج من غير مناقشة.** كل باكدج = التزام صيانة.
- قبل الاقتراح، جاوب: آخر تحديث إمتى؟ كام مشروع بيستخدمه؟ بيدعم Filament v5؟ ينفع نكتبه بنفسنا في يوم؟
- **`composer require <package>`** بإصدار محدد — مش `composer update`
- `composer.lock` بيتـcommit دايماً
- ترقية كبيرة (major) = PR منفصل بعنوان `chore(deps)` ووصف كامل للتغييرات الكاسرة

---

## ٧. الملفات والمجلدات

- ملف واحد = كلاس واحد
- اسم الملف = اسم الكلاس
- **مفيش** `helpers.php` عملاق — الدوال المساعدة في `src/Support/helpers.php` مقسّمة بتعليقات
- **مفيش** `Utils` أو `Helpers` أو `Managers` كأسماء كلاسات — سمّي الحاجة باللي بتعمله

---

## ٨. الأخطاء والاستثناءات

```php
// استثناء معبّر في السياق
namespace Src\Contexts\Content\Domain\Exceptions;

final class AnnouncementAlreadyPublishedException extends DomainException
{
    public function __construct(public readonly int $announcementId)
    {
        parent::__construct(__('content::content.announcement.errors.already_published'));
    }
}
```

- **ارمِ استثناء معبّر** مش `abort(422)` من طبقة Application
- **الترجمة في الاستثناء** عشان تظهر للمستخدم صح
- **`report()`** للأخطاء اللي مش لازم توقف التنفيذ
- **مفيش `try/catch` فاضي** — لو مسكت استثناء، اعمل حاجة بيه

---

## ٩. الأداء — قواعد سريعة

- `->with()` صريح لأي علاقة هتُستخدم
- `chunkById()` مش `chunk()` لو بتعدّل أثناء التكرار
- `cursor()` للبيانات الضخمة
- `exists()` مش `count() > 0`
- `select()` بالأعمدة المطلوبة بس في الاستعلامات التقيلة
- الكاش لأي حساب بيتكرر أكتر من مرة في الطلب
- **قِس قبل ما تحسّن** — Telescope أو Pulse

---

## ١٠. الملخص في ١٠ سطور

1. `declare(strict_types=1)` في كل ملف
2. كل كلاس `final`، كل حاجة type-hinted
3. الصلاحية الأول، الكود بعدين
4. `tenant_id` + `BelongsToTenant` لأي موديل تابع
5. صفر نص، صفر لون، صفر `left/right` في الكود
6. المنطق في Actions مش في Filament
7. كل عملية > 200ms تروح الطابور
8. كل PR فيه اختبارات ولقطات
9. متضيفش باكدج من غير مناقشة
10. لو الوثيقة ناقصة — اكتبها
