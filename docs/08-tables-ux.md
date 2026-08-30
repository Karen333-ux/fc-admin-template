# ٠٨ — تجربة استخدام الجداول

## المبدأ

الجدول هو ٨٠٪ من وقت المستخدم في لوحة تحكم. لو الجدول بطيء أو مربك، التطبيق كله فاشل مهما كان باقيه حلو.

**المعيار:** جدول بـ ٥٠ ألف صف يفتح في أقل من ٥٠٠ms، والمستخدم يوصل للصف اللي عايزه في أقل من ١٥ ثانية.

---

## ١. الإعدادات الافتراضية العامة

بدل ما نكرر نفس الإعدادات في ٦٠ مورد، نظبطها مرة واحدة في `AppServiceProvider::boot()`:

```php
use Filament\Tables\Table;

Table::configureUsing(function (Table $table): void {
    $table
        ->defaultPaginationPageOption(25)
        ->paginated([10, 25, 50, 100])
        ->extremePaginationLinks()
        ->persistFiltersInSession()
        ->persistSortInSession()
        ->persistSearchInSession()
        ->persistColumnSearchesInSession()
        ->persistColumnsInSession()
        ->deferLoading()
        ->deferFilters()
        ->searchOnBlur()
        ->striped()
        ->reorderableColumns()
        ->emptyStateHeading(__('table.empty.heading'))
        ->emptyStateDescription(__('table.empty.description'))
        ->emptyStateIcon('heroicon-o-inbox');
});
```

شرح كل واحدة:

| الطريقة | ليه |
|---|---|
| `deferLoading()` | الصفحة تظهر فوراً والجدول يتحمّل بعدها — فرق هائل على البيانات الكبيرة |
| `deferFilters()` | الفلاتر متتطبّقش لحد ما يضغط «تطبيق» — بيمنع ٥ استعلامات وهو بيملا ٥ فلاتر |
| `persist*InSession()` | يرجع يلاقي الفلاتر والترتيب زي ما سابهم |
| `persistColumnsInSession()` | الأعمدة اللي أخفاها تفضل مخفية |
| `searchOnBlur()` | بحث لما يسيب الحقل مش مع كل حرف — بيقلل الاستعلامات بشكل كبير |
| `extremePaginationLinks()` | روابط «الأولى» و«الأخيرة» |
| `reorderableColumns()` | المستخدم يرتّب الأعمدة زي ما يحب |

> **متغيّرش دي في مورد فردي** غير لو فيه سبب مكتوب في تعليق فوق السطر.

---

## ٢. الأعمدة

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->label(__('identity.fields.name'))
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium)
                ->description(fn (User $r) => $r->email)          // سطر تاني صغير
                ->wrap(),

            TextColumn::make('code')
                ->label(__('identity.fields.code'))
                ->searchable(isIndividual: true)                   // خانة بحث للعمود ده وحده
                ->copyable()
                ->copyMessage(__('common.copied'))
                ->extraAttributes(['class' => 'fc-code']),          // خط mono + عزل ثنائي الاتجاه

            TextColumn::make('roles.name')
                ->label(__('identity.fields.roles'))
                ->badge()
                ->color(fn (string $state) => RoleColor::from($state)->filament())
                ->separator('،')
                ->toggleable(),

            TextColumn::make('status')
                ->label(__('common.status'))
                ->badge()
                ->formatStateUsing(fn (UserStatus $state) => $state->label())
                ->color(fn (UserStatus $state) => $state->color())
                ->icon(fn (UserStatus $state) => $state->icon()),   // أيقونة + لون: مش اللون لوحده

            TextColumn::make('last_login_at')
                ->label(__('identity.fields.last_login'))
                ->dateTime('d M Y — H:i')
                ->since()                                          // «منذ ٣ ساعات»
                ->tooltip(fn ($state) => $state?->format('Y-m-d H:i:s'))
                ->sortable()
                ->toggleable(),

            TextColumn::make('created_at')
                ->label(__('common.created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),      // متاح بس مخفي افتراضياً
        ])
```

### قواعد الأعمدة

1. **٦ أعمدة ظاهرة كحد أقصى** افتراضياً. الباقي `toggleable(isToggledHiddenByDefault: true)`.
2. **العمود الأول = المُعرِّف** اللي المستخدم بيدوّر بيه، وبيبقى `->weight(FontWeight::Medium)`.
3. **اللون ما يقفش لوحده** — دايماً مع أيقونة أو نص (عمى الألوان).
4. **الأرقام والأكواد** كلها `fc-code` (خط mono + `tabular-nums` + `unicode-bidi: isolate`).
5. **التواريخ** `->since()` مع `->tooltip()` بالتاريخ الكامل.
6. **أي عمود `searchable()` أو `sortable()` عليه index** في الميجريشن. مفيش استثناء.

---

## ٣. الفلاتر

```php
->filters([
    SelectFilter::make('status')
        ->label(__('common.status'))
        ->options(UserStatus::class)
        ->multiple()
        ->preload(),

    SelectFilter::make('roles')
        ->label(__('identity.fields.roles'))
        ->relationship('roles', 'name')
        ->multiple()
        ->preload()
        ->searchable(),

    Filter::make('created_at')
        ->label(__('common.created_between'))
        ->form([
            DatePicker::make('from')->label(__('common.from')),
            DatePicker::make('until')->label(__('common.until')),
        ])
        ->query(fn (Builder $q, array $data) => $q
            ->when($data['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($data['until'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
        ->indicateUsing(function (array $data): array {
            $indicators = [];
            if ($data['from'] ?? null) {
                $indicators[] = Indicator::make(__('common.from') . ': ' . $data['from'])->removeField('from');
            }
            if ($data['until'] ?? null) {
                $indicators[] = Indicator::make(__('common.until') . ': ' . $data['until'])->removeField('until');
            }
            return $indicators;
        }),

    TernaryFilter::make('is_active')->label(__('common.active')),

    TrashedFilter::make(),
])
->filtersLayout(FiltersLayout::AboveContentCollapsible)
->filtersFormColumns(3)
->deferFilters()
```

> **`indicateUsing()` إلزامي** لأي فلتر مخصص. من غيرها المستخدم بيفلتر وينسى وبعدين يقول «البيانات ناقصة».

---

## ٤. الإجراءات

```php
->recordActions([
    ActionGroup::make([
        ViewAction::make(),
        EditAction::make(),

        Action::make('impersonate')
            ->label(__('identity.actions.impersonate'))
            ->icon('heroicon-o-user-circle')
            ->authorize('impersonate')
            ->authorizationTooltip()
            ->requiresConfirmation()
            ->modalHeading(__('identity.confirm.impersonate_heading'))
            ->modalDescription(__('identity.confirm.impersonate_body'))
            ->action(fn (User $record) => app(ImpersonateAction::class)->handle($record)),

        Action::make('force_logout')
            ->label(__('identity.actions.force_logout'))
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('warning')
            ->authorize('forceLogout')          // ← Policy، مش نص صلاحية
            ->authorizationTooltip()
            ->requiresConfirmation()
            ->action(fn (User $r) => app(ForceLogoutAction::class)->handle($r))
            ->successNotificationTitle(__('identity.notifications.logged_out')),

        DeleteAction::make(),
        RestoreAction::make(),
    ])
    ->label(__('common.actions'))
    ->icon('heroicon-m-ellipsis-vertical')
    ->button()
    ->color('gray'),
])
->toolbarActions([
    BulkActionGroup::make([
        DeleteBulkAction::make()->authorizeIndividualRecords('delete'),   // فحص كل سجل، مش فحص شامل

        BulkAction::make('activate')
            ->label(__('identity.actions.bulk_activate'))
            ->icon('heroicon-o-check-circle')
            ->authorizeIndividualRecords('activate')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => app(BulkActivateAction::class)->handle($records)),

        ExportBulkAction::make(),
    ]),
])
->headerActions([
    CreateAction::make(),
    ExportAction::make(),
])
```

> **قاعدة:** أكتر من ٣ إجراءات صف = لازم `ActionGroup`. الصف مش مكان لسبع أزرار.

---

## ٥. الأداء

### أ. تجنّب N+1

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['roles:id,name', 'tenant:id,slug'])
        ->withCount('sessions');
}
```

**افحص دايماً بـ Telescope.** أي صفحة جدول بأكتر من ١٠ استعلامات = باگ.

### ب. الفهارس

```php
Schema::table('users', function (Blueprint $table) {
    $table->index(['tenant_id', 'status']);        // مركّب: المستأجر أولاً دايماً
    $table->index(['tenant_id', 'created_at']);
    $table->index('last_login_at');
});
```

للبحث النصي على Postgres:

```php
DB::statement('CREATE INDEX users_name_trgm_idx ON users USING gin (name gin_trgm_ops)');
// يحتاج: CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

الفهرس ده بيخلي `LIKE '%term%'` سريع — وده اللي Filament بيعمله في البحث.

### ج. للجداول الضخمة جداً (> ٥٠٠ ألف صف)

`COUNT(*)` على Postgres بيبقى بطيء. الحل: ترقيم بسيط بدل الكامل:

```php
->paginationMode(PaginationMode::Simple)
```

أو استخدم تقدير Postgres:

```sql
SELECT reltuples::bigint FROM pg_class WHERE relname = 'users';
```

---

## ٦. التصدير

```bash
composer require pxlrbt/filament-excel
```

```php
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;   // ملاحظة: Actions\Tables\ExportBulkAction مهجورة (deprecated)
use pxlrbt\FilamentExcel\Exports\ExcelExport;

ExportBulkAction::make()
    ->authorize('export')       // فحص على مستوى المورد — التصدير مالوش قاعدة لكل سجل
    ->exports([
        ExcelExport::make('xlsx')
            ->fromTable()
            ->withFilename(fn () => 'users-' . now()->format('Y-m-d'))
            ->withWriterType(Excel::XLSX)
            ->queue(),                    // ← إلزامي للتصدير الكبير
    ]);
```

> **قاعدة:** أي تصدير محتمل يعدّي ١٠٠٠ صف لازم `->queue()`. المستخدم بياخد إشعار بالرابط لما يخلص.

---

## ٧. حالات فارغة مفيدة

```php
->emptyStateHeading(__('identity.empty.heading'))
->emptyStateDescription(__('identity.empty.description'))
->emptyStateIcon('heroicon-o-users')
->emptyStateActions([
    CreateAction::make()
        ->label(__('identity.empty.cta'))
        ->authorize('create'),
])
```

**فرّق بين حالتين:**
- **مفيش بيانات أصلاً** → «ابدأ بإضافة أول مستخدم» + زرار إنشاء
- **الفلتر مرجّعش نتيجة** → «مفيش نتائج للفلتر ده» + زرار «مسح الفلاتر»

```php
->emptyStateHeading(fn (Table $table) => $table->isFiltered()
    ? __('table.empty.no_results')
    : __('identity.empty.heading'))
```

---

## ٨. تفاصيل UX صغيرة بتفرق كتير

- **تثبيت رأس الجدول** عند التمرير — CSS: `.fi-ta-header-cell { position: sticky; inset-block-start: 0; }`
- **تثبيت عمود الإجراءات** في الآخر على الجداول العريضة
- **صف قابل للنقر بالكامل** — `->recordUrl(fn ($r) => static::getUrl('edit', ['record' => $r]))`
- **تلوين الصف حسب الحالة** — `->recordClasses(fn ($r) => $r->is_suspended ? 'fc-row-suspended' : null)`
- **مؤشر تحميل واضح** عند تطبيق الفلتر
- **عدّاد النتائج** ظاهر دايماً: «٢٤٧ نتيجة من ٥٠٬٠٠٠»
- **الترتيب الافتراضي** دايماً محدد صراحةً: `->defaultSort('created_at', 'desc')`

---

## ٩. معايير القبول

- [ ] `Table::configureUsing()` موجود وكل الجداول بتاخد منه
- [ ] جدول ٥٠ ألف صف بيفتح في < 500ms (قِس بـ Telescope)
- [ ] عدد الاستعلامات لأي صفحة جدول ≤ ١٠
- [ ] كل عمود `searchable`/`sortable` عليه index — راجع الميجريشن
- [ ] كل فلتر مخصص عليه `indicateUsing()`
- [ ] كل إجراء عبر `->authorize()` وكل إجراء جماعي عبر `->authorizeIndividualRecords()`
- [ ] كل تصدير محتمل يكون كبير `->queue()`
- [ ] الفلاتر والترتيب والأعمدة بتفضل بعد refresh
- [ ] الحالة الفارغة بتفرّق بين «مفيش بيانات» و«الفلتر فاضي»
- [ ] الجدول شغّال RTL — الأعمدة والترقيم منعكسين صح
- [ ] كل الألوان معاها أيقونة أو نص
