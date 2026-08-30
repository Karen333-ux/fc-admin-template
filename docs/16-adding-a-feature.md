# ١٦ — الوصفة: إضافة ميزة جديدة

> الملف ده هو مقياس نجاح القالب كله. لو مطوّر جديد قدر يضيف ميزة كاملة بالملف ده لوحده — نجحنا.

**المثال:** هنضيف ميزة **«الإعلانات»** (Announcements) — رسائل الأدمن بيبعتها لمستخدمي المؤسسة.

---

## قبل ما تبدأ — ٣ أسئلة

1. **هل دي ميزة جديدة ولا امتداد لموجودة؟** لو امتداد، روح للسياق الموجود.
2. **هل محتاجة سياق جديد؟** سياق جديد = ٣+ موديلات مترابطة + لغة أعمال خاصة. الإعلانات = موديل واحد → **تروح لسياق `Content` الموجود**.
3. **مين اللي هيستخدمها وبأي صلاحية؟** اكتب الإجابة قبل ما تكتب كود.

---

## الخطوة ١ — الصلاحيات (الأول دايماً)

في `config/authorization.php`:

```php
'resources' => [
    // ...
    'announcements' => [
        'group'   => 'content',
        'actions' => null,                          // الأفعال القياسية
        'extra'   => ['publish', 'pin'],
    ],
],
```

أضف للأدوار:

```php
'roles' => [
    'admin'  => [..., 'announcements.*'],
    'editor' => [..., 'view_any.announcements', 'view.announcements',
                      'create.announcements', 'update.announcements'],
    'viewer' => [..., 'view_any.announcements', 'view.announcements'],
],
```

الترجمة في `lang/ar/authorization.php` و `lang/en/authorization.php`:

```php
'resources' => [
    'announcements' => 'الإعلانات',
],
'actions' => [
    'publish' => 'نشر',
    'pin'     => 'تثبيت',
],
```

```bash
php artisan authorization:sync
```

> **ليه الصلاحيات الأول؟** عشان لما تكتب الـ Resource تلاقي أسماء الصلاحيات جاهزة قدامك، فما تخترعش أسماء عشوائية.

---

## الخطوة ٢ — الميجريشن

`src/Contexts/Content/Database/Migrations/2026_08_25_000000_create_announcements_table.php`:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->json('title');                    // مترجم
            $table->json('body');                     // مترجم
            $table->string('status')->default('draft');
            $table->string('severity')->default('info');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // فهارس: المستأجر أولاً دايماً
            $table->index(['tenant_id', 'status', 'published_at']);
            $table->index(['tenant_id', 'is_pinned']);
        });
    }
};
```

### قواعد الميجريشن
- `tenant_id` إلزامي لأي جدول تابع لمستأجر
- فهرس مركّب يبدأ بـ `tenant_id`
- `softDeletes()` لأي حاجة المستخدم ممكن يندم على حذفها
- الحقول المترجمة `json`
- **مفيش** `enum` في قاعدة البيانات — استخدم `string` + PHP Enum (أسهل في التعديل)

---

## الخطوة ٣ — طبقة Domain

### Enum

`src/Contexts/Content/Domain/Enums/AnnouncementStatus.php`:

```php
namespace Src\Contexts\Content\Domain\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AnnouncementStatus: string implements HasLabel, HasColor, HasIcon
{
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';

    public function getLabel(): string
    {
        return __("content::content.announcement.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft     => 'gray',
            self::Published => 'success',
            self::Archived  => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft     => 'heroicon-o-pencil',
            self::Published => 'heroicon-o-check-circle',
            self::Archived  => 'heroicon-o-archive-box',
        };
    }
}
```

### الموديل

`src/Contexts/Content/Domain/Models/Announcement.php`:

```php
namespace Src\Contexts\Content\Domain\Models;

final class Announcement extends Model
{
    use SoftDeletes;
    use HasTranslations;
    use BelongsToTenant;          // ← الأهم: عزل المستأجر
    use LogsActivity;
    use InteractsWithMedia;

    public array $translatable = ['title', 'body'];

    protected $fillable = [
        'author_id', 'title', 'body', 'status',
        'severity', 'is_pinned', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => AnnouncementStatus::class,
            'severity'     => AnnouncementSeverity::class,
            'is_pinned'    => 'boolean',
            'published_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', AnnouncementStatus::Published)
                 ->where('published_at', '<=', now())
                 ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'is_pinned', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('content');
    }
}
```

> ⚠️ **`$fillable` صريح** — مش `$guarded = []`. ولاحظ إن `tenant_id` **مش** في `$fillable` عشان الـ trait هو اللي بيحطه.

### الحدث

```php
namespace Src\Contexts\Content\Domain\Events;

final readonly class AnnouncementPublished
{
    public function __construct(
        public int $announcementId,
        public int $tenantId,
    ) {}
}
```

---

## الخطوة ٤ — طبقة Application

### DTO

```php
namespace Src\Contexts\Content\Application\DataObjects;

final readonly class PublishAnnouncementData
{
    public function __construct(
        public int $announcementId,
        public ?CarbonImmutable $publishAt = null,
        public bool $notifyUsers = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            announcementId: $data['id'],
            publishAt: isset($data['publish_at']) ? CarbonImmutable::parse($data['publish_at']) : null,
            notifyUsers: $data['notify_users'] ?? true,
        );
    }
}
```

### الـ Action

```php
namespace Src\Contexts\Content\Application\Actions;

final readonly class PublishAnnouncementAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function handle(PublishAnnouncementData $data, ?User $actor = null): Announcement
    {
        return DB::transaction(function () use ($data, $actor): Announcement {
            $announcement = Announcement::findOrFail($data->announcementId);

            // الحاجز الأخير: الـ Action ممكن يتنادى من API أو Job أو Command.
            // $actor = null معناه استدعاء نظامي صريح (أمر مجدول) — مش سهو.
            if ($actor !== null) {
                Gate::forUser($actor)->authorize('publish', $announcement);
            }

            throw_if(
                $announcement->status === AnnouncementStatus::Published,
                new AnnouncementAlreadyPublishedException($announcement->id),
            );

            $announcement->update([
                'status'       => AnnouncementStatus::Published,
                'published_at' => $data->publishAt ?? now(),
            ]);

            if ($data->notifyUsers) {
                $this->events->dispatch(new AnnouncementPublished(
                    $announcement->id,
                    $announcement->tenant_id,
                ));
            }

            return $announcement;
        });
    }
}
```

### قواعد الـ Action
- `readonly` + `final`
- دالة `handle` واحدة
- الاعتماديات في الكونستركتور (DI)
- `DB::transaction` لأي عملية بتلمس أكتر من صف
- بترمي استثناءات معبّرة مش `abort(422)`
- بتطلق حدث بدل ما تنادي سياق تاني

---

## الخطوة ٥ — طبقة Infrastructure

### المستمع

```php
namespace Src\Contexts\Notifications\Infrastructure\Listeners;

final class NotifyUsersOfAnnouncement implements ShouldQueue
{
    public function handle(AnnouncementPublished $event): void
    {
        app(TenantContext::class)->set($event->tenantId);

        User::query()
            ->whereHas('tenants', fn ($q) => $q->whereKey($event->tenantId))
            ->chunkById(200, function (Collection $users) use ($event): void {
                Notification::send($users, new AnnouncementPublishedNotification($event->announcementId));
            });
    }
}
```

### الإشعار

أضفه لكتالوج `config/notifications.php`:

```php
'announcement_published' => [
    'group'    => 'content',
    'channels' => ['database', 'broadcast', 'mail'],
    'default'  => ['database', 'broadcast'],
    'required' => [],
],
```

### الـ Policy — **قلب التفويض** (اقرأ `docs/19-policies.md` الأول)

الـ Policy مش «مكان نفحص فيه الصلاحية». هي المكان اللي فيه **الصلاحية + قواعد الأعمال معاً** — عشان الإجابة تبقى واحدة سواء الطلب جه من الواجهة أو API أو Job.

```php
namespace Src\Contexts\Content\Infrastructure\Policies;

use Illuminate\Auth\Access\Response;
use Src\Support\Domain\Authorization\Policy;

final class AnnouncementPolicy extends Policy
{
    protected function resource(): string
    {
        return 'announcements';     // ← نفس المفتاح في config/authorization.php
    }

    /** قدرات لا يتجاوزها المدير العام — قواعد سلامة مش صلاحيات */
    public function invariants(): array
    {
        return ['publish', 'delete'];
    }

    public function viewAny(User $user): Response
    {
        return $this->decide()->permission($user, $this, 'view_any')->response();
    }

    public function create(User $user): Response
    {
        return $this->decide()->permission($user, $this, 'create')->response();
    }

    public function view(User $user, Announcement $a): Response
    {
        return $this->decide()
            ->permission($user, $this, 'view')
            // سجل من مستأجر تاني: 404 مش 403 — «ممنوع» بتأكد إنه موجود
            ->ruleOrNotFound($a->tenant_id === app(TenantContext::class)->id(), 'record_not_found')
            ->response();
    }

    public function update(User $user, Announcement $a): Response
    {
        return $this->decide()
            ->permission($user, $this, 'update')
            ->rule(! $a->trashed(), 'record_trashed')
            ->ruleUsing(
                fn () => $a->status !== AnnouncementStatus::Published
                    || $user->can('publish', $a),
                'announcement.published_needs_publish_permission',
            )
            ->response();
    }

    public function delete(User $user, Announcement $a): Response
    {
        return $this->decide()
            ->permission($user, $this, 'delete')
            ->rule(! $a->trashed(), 'record_trashed')
            ->rule($a->status !== AnnouncementStatus::Published, 'announcement.published_must_archive')
            ->response();
    }

    public function publish(User $user, Announcement $a): Response
    {
        return $this->decide()
            ->permission($user, $this, 'publish')
            ->rule(! $a->trashed(), 'record_trashed')
            ->rule($a->status === AnnouncementStatus::Draft, 'announcement.not_draft')
            ->rule(
                filled($a->getTranslation('title', config('app.fallback_locale'), false)),
                'announcement.missing_fallback_title',
                ['locale' => config('app.fallback_locale')],
            )
            ->response();
    }

    public function pin(User $user, Announcement $a): Response
    {
        return $this->decide()
            ->permission($user, $this, 'pin')
            ->rule($a->status === AnnouncementStatus::Published, 'announcement.pin_requires_published')
            ->response();
    }
}
```

**مفيش تسجيل يدوي.** `Gate::guessPolicyNamesUsing()` بيحوّل
`Src\Contexts\Content\Domain\Models\Announcement` → `Src\Contexts\Content\Infrastructure\Policies\AnnouncementPolicy`
تلقائياً. اتبع الاصطلاح وخلاص.

### رسائل الرفض

كل `->rule()` بترجّع رسالة **بتظهر للمستخدم**. ضيفها في `lang/{ar,en}/authorization.php`:

```php
'denied' => [
    'announcement' => [
        'not_draft'              => 'الإعلان ده منشور بالفعل.',
        'published_must_archive' => 'الإعلان المنشور بيتأرشف مش بيتحذف.',
        'missing_fallback_title' => 'لازم تكتب العنوان بلغة :locale قبل النشر.',
        'pin_requires_published' => 'مينفعش تثبّت إعلان مش منشور.',
        'published_needs_publish_permission' => 'الإعلان منشور — تعديله محتاج صلاحية النشر.',
    ],
],
```

---

## الخطوة ٦ — طبقة Presentation

```php
namespace Src\Contexts\Content\Presentation\Filament\Resources;

final class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?int $navigationSort = 30;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::Content->getLabel();
    }

    public static function getModelLabel(): string
    {
        return __('content::content.announcement.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content::content.announcement.plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();     // ← بتمرّ على الـ Policy
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::tags(['nav-badges', 'tenant:' . app(TenantContext::class)->id()])
            ->remember('nav:draft-announcements', now()->addMinutes(2),
                fn () => static::getModel()::where('status', AnnouncementStatus::Draft)->count() ?: null);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('author:id,name');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('content::content.announcement.sections.content'))
                ->columnSpanFull()
                ->schema([
                    Tabs::make('translations')
                        ->columnSpanFull()
                        ->tabs(collect(config('app.supported_locales'))->map(
                            fn (string $l) => Tabs\Tab::make($l)
                                ->label(__("common.locales.{$l}"))
                                ->schema([
                                    TextInput::make("title.{$l}")
                                        ->label(__('content::content.announcement.fields.title'))
                                        ->required($l === config('app.fallback_locale'))
                                        ->maxLength(180),

                                    RichEditor::make("body.{$l}")
                                        ->label(__('content::content.announcement.fields.body'))
                                        ->required($l === config('app.fallback_locale'))
                                        ->columnSpanFull(),
                                ])
                        )->all()),
                ]),

            Section::make(__('content::content.announcement.sections.settings'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('severity')
                        ->label(__('content::content.announcement.fields.severity'))
                        ->options(AnnouncementSeverity::class)
                        ->default(AnnouncementSeverity::Info)
                        ->required(),

                    Toggle::make('is_pinned')
                        ->label(__('content::content.announcement.fields.pinned'))
                        // إخفاء + منع حفظ. الإخفاء لوحده مش أمان.
                        ->visible(fn (?Announcement $record) => $record !== null
                            && auth()->user()->can('pin', $record))
                        ->saved(fn (?Announcement $record) => $record !== null
                            && auth()->user()->can('pin', $record)),

                    DateTimePicker::make('published_at')
                        ->label(__('content::content.announcement.fields.published_at'))
                        ->seconds(false),

                    DateTimePicker::make('expires_at')
                        ->label(__('content::content.announcement.fields.expires_at'))
                        ->seconds(false)
                        ->after('published_at'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_pinned')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('')
                    ->trueColor('warning'),

                TextColumn::make('title')
                    ->label(__('content::content.announcement.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->limit(80),

                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->badge(),

                TextColumn::make('severity')
                    ->label(__('content::content.announcement.fields.severity'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('author.name')
                    ->label(__('content::content.announcement.fields.author'))
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label(__('content::content.announcement.fields.published_at'))
                    ->dateTime('d M Y — H:i')
                    ->since()
                    ->tooltip(fn ($state) => $state?->format('Y-m-d H:i'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(AnnouncementStatus::class)->multiple(),
                SelectFilter::make('severity')->options(AnnouncementSeverity::class)->multiple(),
                TernaryFilter::make('is_pinned')->label(__('content::content.announcement.fields.pinned')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('publish')
                        ->label(__('content::content.announcement.actions.publish'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->authorize('publish')        // السجل بيتبعت للـ Policy تلقائياً
                        ->authorizationTooltip()      // بيعرض سبب الرفض من الـ Policy
                        ->requiresConfirmation()
                        ->modalHeading(__('content::content.announcement.confirm.publish_heading'))
                        ->modalDescription(__('content::content.announcement.confirm.publish_body'))
                        ->schema([
                            Toggle::make('notify_users')
                                ->label(__('content::content.announcement.fields.notify_users'))
                                ->default(true),
                        ])
                        ->action(fn (Announcement $record, array $data) =>
                            app(PublishAnnouncementAction::class)->handle(
                                PublishAnnouncementData::fromArray(['id' => $record->id, ...$data])
                            ))
                        ->successNotificationTitle(__('content::content.announcement.notifications.published')),

                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // فحص كل سجل مختار بالـ Policy — اللي يرسب بيتستبعد
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    ExportBulkAction::make()->authorize('export'),
                ]),
            ])
            ->emptyStateHeading(__('content::content.announcement.empty.heading'))
            ->emptyStateDescription(__('content::content.announcement.empty.description'))
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateActions([CreateAction::make()->authorize('create')]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'view'   => Pages\ViewAnnouncement::route('/{record}'),
            'edit'   => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}
```

---

## الخطوة ٧ — الترجمة

`src/Contexts/Content/Lang/ar/content.php`:

```php
return [
    'announcement' => [
        'singular' => 'إعلان',
        'plural'   => 'الإعلانات',

        'sections' => [
            'content'  => 'محتوى الإعلان',
            'settings' => 'إعدادات النشر',
        ],

        'fields' => [
            'title'        => 'العنوان',
            'body'         => 'النص',
            'severity'     => 'الأهمية',
            'pinned'       => 'مثبّت',
            'author'       => 'الكاتب',
            'published_at' => 'تاريخ النشر',
            'expires_at'   => 'ينتهي في',
            'notify_users' => 'إشعار المستخدمين',
        ],

        'status' => [
            'draft'     => 'مسودة',
            'published' => 'منشور',
            'archived'  => 'مؤرشف',
        ],

        'severity' => [
            'info'    => 'معلومة',
            'warning' => 'تنبيه',
            'urgent'  => 'عاجل',
        ],

        'actions' => ['publish' => 'نشر', 'pin' => 'تثبيت'],

        'confirm' => [
            'publish_heading' => 'نشر الإعلان؟',
            'publish_body'    => 'هيوصل لكل مستخدمي المؤسسة حسب تفضيلاتهم. مش هينفع تتراجع.',
        ],

        'notifications' => ['published' => 'تم نشر الإعلان'],

        'empty' => [
            'heading'     => 'مفيش إعلانات لسه',
            'description' => 'الإعلانات بتوصل لكل مستخدمي المؤسسة. ابدأ بأول إعلان.',
        ],
    ],
];
```

ونفس الملف بالإنجليزي في `Lang/en/content.php`.

---

## الخطوة ٨ — الاختبارات

`src/Contexts/Content/Tests/Feature/AnnouncementTest.php`:

```php
it('ينشئ إعلاناً بالمستأجر الصحيح', function () {
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->hasAttached($tenant)->create()->assignRole('admin');

    app(TenantContext::class)->set($tenant->id);
    actingAs($user);

    $announcement = Announcement::factory()->create();

    expect($announcement->tenant_id)->toBe($tenant->id);
});

it('يمنع المشاهد من النشر', function () {
    $viewer = userWithRole('viewer');
    $announcement = Announcement::factory()->draft()->create();

    actingAs($viewer);

    livewire(ListAnnouncements::class)
        ->assertTableActionHidden('publish', $announcement);
});

it('يمنع نشر إعلان منشور بالفعل ويوضّح السبب', function () {
    $user = userWithRole('admin');
    $announcement = Announcement::factory()->published()->create();

    $response = Gate::forUser($user)->inspect('publish', $announcement);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe(__('authorization.denied.announcement.not_draft'));
});

it('لا يتجاوز المدير العام قواعد السلامة', function () {
    $super = userWithRole('super_admin');
    $published = Announcement::factory()->published()->create();

    expect($super->can('publish.announcements'))->toBeTrue()      // معاه الصلاحية
        ->and($super->can('publish', $published))->toBeFalse()    // والقاعدة بتمنعه
        ->and($super->can('delete', $published))->toBeFalse();
});

it('يخفي سجل مستأجر آخر بـ 404 لا 403', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $user = userWithRole('admin', $a);
    $foreign = Announcement::factory()->create(['tenant_id' => $b->id]);

    expect(Gate::forUser($user)->inspect('view', $foreign)->status())->toBe(404);
});

it('يرفض نشر إعلان منشور بالفعل (على مستوى الـ Action)', function () {
    $announcement = Announcement::factory()->published()->create();

    expect(fn () => app(PublishAnnouncementAction::class)
        ->handle(new PublishAnnouncementData($announcement->id)))
        ->toThrow(AnnouncementAlreadyPublishedException::class);
});

it('يبعث إشعاراً للمستخدمين عند النشر', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $users  = User::factory()->count(3)->hasAttached($tenant)->create();
    $announcement = Announcement::factory()->draft()->create(['tenant_id' => $tenant->id]);

    app(PublishAnnouncementAction::class)
        ->handle(new PublishAnnouncementData($announcement->id, notifyUsers: true));

    Notification::assertSentTo($users, AnnouncementPublishedNotification::class);
});

it('لا يسرّب إعلانات بين المستأجرين', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();

    Announcement::factory()->count(2)->create(['tenant_id' => $a->id]);
    Announcement::factory()->count(5)->create(['tenant_id' => $b->id]);

    app(TenantContext::class)->set($a->id);

    expect(Announcement::count())->toBe(2);
});

it('حقل التثبيت لا يُحفظ لمن لا يملك الصلاحية', function () {
    $editor = userWithRole('editor');   // مالوش pin.announcements

    actingAs($editor);

    livewire(CreateAnnouncement::class)
        ->fillForm(['title' => ['en' => 'Test'], 'body' => ['en' => 'Body'], 'is_pinned' => true])
        ->call('create');

    expect(Announcement::latest('id')->first()->is_pinned)->toBeFalse();
});
```

---

## الخطوة ٩ — التحقق النهائي

```bash
composer test
composer lint
php artisan authorization:sync
```

ثم يدوياً:
- [ ] الشاشة بالعربي — لقطة
- [ ] الشاشة بالإنجليزي — لقطة
- [ ] الوضع الداكن — لقطة
- [ ] الدخول بدور `viewer` — الأزرار الممنوعة مش ظاهرة
- [ ] Telescope: عدد الاستعلامات ≤ ١٠
- [ ] البحث الشامل (Ctrl+K) بيلاقي الإعلان

---

## الخلاصة — الترتيب المختصر

```
١. الصلاحيات في config/authorization.php + الترجمة + sync
٢. الميجريشن (tenant_id + فهارس + softDeletes)
٣. Domain: Enum → Model (BelongsToTenant!) → Event
٤. Application: DTO → Action (+ Gate::authorize كحاجز أخير)
٥. Infrastructure: Policy (صلاحية + قواعد أعمال) → Listener → Notification
٦. Presentation: Resource — ->authorize() بس، مفيش نص صلاحية
٧. الترجمة ar + en (+ رسائل الرفض)
٨. الاختبارات (مصفوفة أدوار + كل قاعدة أعمال + عزل مستأجر + المدير العام)
٩. التحقق: test + lint + لقطات + Telescope
```

### التسلسل اللي بيحدد كل حاجة

**اسأل الأول: «إيه اللي يمنع العملية دي؟»** الإجابة بتتقسم لحاجتين:

| السبب | مكانه |
|---|---|
| «مالوش صلاحية» | `->permission()` في الـ Policy |
| «الحالة مش مناسبة» / «القيمة ناقصة» / «وصل للحد» | `->rule()` في نفس دالة الـ Policy |

**الاتنين في نفس الدالة.** لو حطيت التاني في الـ Resource، هتضطر تكرره في الـ API والـ Job، وهتنسى واحد منهم.

**لو الميزة أضافت pattern جديد مش موجود في `docs/` — اكتبه.**

---

## مولّد آلي (اختياري — أسبوع ٦)

```bash
php artisan make:fc-feature Content Announcement --translatable --media --soft-deletes
```

بيولّد كل الملفات فوق بالبنية الصحيحة، وبيضيف الصلاحيات للكونفيج، وبيعمل ملفات ترجمة فاضية. بيوفّر ٤٥ دقيقة على كل ميزة.
