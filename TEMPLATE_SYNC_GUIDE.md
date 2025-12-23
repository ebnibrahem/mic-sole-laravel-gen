# دليل مزامنة القوالب (Template Sync Guide)

## نظرة عامة

نظام مزامنة القوالب في حزمة `mic-sole-laravel-gen` يسمح بعكس التعديلات من الملفات المولدة في المشروع إلى قوالب الحزمة الأصلية. هذا يضمن أن القوالب تبقى محدثة مع التحسينات والتعديلات التي يتم إجراؤها في المشاريع.

## أنواع القوالب

### 1. القوالب الثابتة (Static Templates)

**الخصائص:**

- لا تحتوي على placeholders ديناميكية مثل `{{modelName}}` أو `{{fields}}`
- ملفات ثابتة يمكن نسخها مباشرة
- سهلة المزامنة بدون معالجة معقدة

**أمثلة:**

- `ExportAsExcel.vue` - مكون تصدير Excel
- `dashboard.blade.php` - قالب لوحة التحكم
- `langHelper.ts` - مساعد الترجمة
- `apiHandler.ts` - معالج API

**الموقع في الحزمة:**

- موجودة في `mic-sole-laravel-gen/src/Templates/`
- يمكن مزامنتها عبر `SyncTemplatesCommand.php`

### 2. القوالب الديناميكية (Dynamic Templates)

**الخصائص:**

- تحتوي على placeholders مثل `{{modelName}}`، `{{modelNamePlural}}`، `{{fields}}`
- يتم استبدال الـ placeholders بقيم حقيقية عند التوليد
- صعبة المزامنة تلقائياً لأنها تحتاج استبدال القيم الحقيقية بالـ placeholders مرة أخرى

**أمثلة:**

- `list_page.stub` - قالب صفحة القائمة
- `form_component.stub` - قالب مكون النموذج
- `table_component.stub` - قالب مكون الجدول
- `single_page.stub` - قالب صفحة التفاصيل

**الموقع في الحزمة:**

- موجودة في `mic-sole-laravel-gen/src/Templates/vue/`
- تحتاج تعديل يدوي في القوالب مباشرة

## كيف يعمل نظام المزامنة

### الملفات القابلة للمزامنة

جميع الملفات المدرجة في `SyncTemplatesCommand.php` يمكن مزامنتها بسهولة:

```php
protected $fileMapping = [
    // Core TypeScript files
    'resources/ts/_dashboard/utilities/langHelper.ts' => 'utilities/langHelper.stub',

    // Vue Components (Static)
    'resources/ts/shared/components/ExportAsExcel.vue' => 'components/exports/ExportAsExcel.stub',

    // Blade Templates
    'resources/views/dashboard.blade.php' => 'dashboard.blade.stub',

    // ... المزيد
];
```

### أنواع الملفات القابلة للمزامنة

#### 1. ملفات الحزمة الأساسية (Core Package Files)

- ملفات TypeScript الأساسية
- مكونات Vue الثابتة
- ملفات Blade
- ملفات CSS
- ملفات الترجمة الأساسية (common, auth, setting, profile)

#### 2. ملفات الأمثلة (Example Files)

- ملفات User, Role, Permission (تُثبت مع `--with-example`)
- مكونات Authorization
- ملفات الأمثلة الأخرى

## استخدام أمر المزامنة

### المزامنة الكاملة

```bash
php artisan mic-sole:sync-templates
```

يقوم بمزامنة جميع الملفات المدرجة في `SyncTemplatesCommand.php`

### المزامنة لملف محدد

```bash
php artisan mic-sole:sync-templates --file=resources/ts/shared/components/ExportAsExcel.vue
```

### المزامنة مع استبدال تلقائي

```bash
php artisan mic-sole:sync-templates --force
```

يستبدل القوالب الموجودة بدون طلب تأكيد

### عرض ما سيتم مزامنته (بدون تنفيذ)

```bash
php artisan mic-sole:sync-templates --dry-run
```

## سير العمل (Workflow)

### 1. التوليد الأولي

```bash
php artisan mic-sole:init-dashboard --with-example
```

يتم توليد جميع الملفات من القوالب

### 2. التعديل في المشروع

قم بتعديل الملفات المولدة حسب احتياجاتك:

- تحسين مكون `ExportAsExcel.vue`
- تعديل `dashboard.blade.php`
- إضافة ميزات جديدة في `langHelper.ts`

### 3. عكس التعديلات على الحزمة

```bash
php artisan mic-sole:sync-templates --force
```

يتم نسخ التعديلات من المشروع إلى قوالب الحزمة

### 4. النتيجة

- القوالب في الحزمة تصبح محدثة
- التوليدات القادمة تستخدم النسخة المحدثة
- التحسينات تنتشر لجميع المشاريع

## القوالب الديناميكية - التحدي

### المشكلة

القوالب الديناميكية تحتوي على placeholders:

```vue
<!-- list_page.stub -->
<script lang="ts" setup>
import type { {{modelName}} } from '@shared/types/{{modelNameLower}}';
// ...
const {{modelNameLower}}s = shallowRef<{{modelName}}[]>([]);
```

عند التوليد، يتم استبدالها:

```vue
<!-- Pages.vue (المولد) -->
<script lang="ts" setup>
import type { Page } from '@shared/types/page';
// ...
const pages = shallowRef<Page[]>([]);
```

### التحدي في المزامنة

عكس التعديلات يتطلب:

1. استبدال `Page` بـ `{{modelName}}`
2. استبدال `page` بـ `{{modelNameLower}}`
3. استبدال `pages` بـ `{{modelNamePluralLower}}`
4. معالجة جميع الـ placeholders الأخرى

هذا معقد ويحتاج خوارزمية ذكية.

### الحل الحالي

- **التعديل اليدوي**: تعديل القوالب الديناميكية مباشرة في الحزمة
- **التحسين المستقبلي**: تطوير نظام ذكي لاستبدال القيم بالـ placeholders

## أفضل الممارسات

### 1. مزامنة دورية

قم بمزامنة التعديلات المهمة بانتظام:

```bash
# بعد إجراء تحسينات مهمة
php artisan mic-sole:sync-templates --force
```

### 2. التحقق قبل المزامنة

استخدم `--dry-run` للتحقق:

```bash
php artisan mic-sole:sync-templates --dry-run
```

### 3. نسخ احتياطي

قبل المزامنة الكبيرة، احتفظ بنسخة احتياطية من القوالب:

```bash
cp -r mic-sole-laravel-gen/src/Templates mic-sole-laravel-gen/src/Templates.backup
```

### 4. التعديلات اليدوية للقوالب الديناميكية

عند تعديل ملف مولدة من قالب ديناميكي:

1. حدد التعديلات المهمة
2. طبقها يدوياً على القالب في الحزمة
3. استبدل القيم الحقيقية بالـ placeholders

## أمثلة عملية

### مثال 1: تحسين مكون التصدير

**1. تعديل في المشروع:**

```vue
<!-- resources/ts/shared/components/ExportAsExcel.vue -->
<template>
  <Button @click="exportToExcel" :loading="exporting">
    <i class="pi pi-file-excel" />
    {{ c('common.export_excel', 'common') }}
  </Button>
</template>
```

**2. مزامنة:**

```bash
php artisan mic-sole:sync-templates --file=resources/ts/shared/components/ExportAsExcel.vue --force
```

**3. النتيجة:**

- القالب `components/exports/ExportAsExcel.stub` يتم تحديثه
- التوليدات القادمة تستخدم النسخة المحدثة

### مثال 2: تحسين dashboard.blade.php

**1. تعديل في المشروع:**

```php
<!-- resources/views/dashboard.blade.php -->
// إضافة ميزة جديدة
```

**2. مزامنة:**

```bash
php artisan mic-sole:sync-templates --file=resources/views/dashboard.blade.php --force
```

**3. النتيجة:**

- القالب `dashboard.blade.stub` يتم تحديثه

## الملفات المدرجة في SyncTemplatesCommand

### Core Files

- `langHelper.ts` - مساعد الترجمة
- `primevue-locale.ts` - إعدادات PrimeVue
- `apiHandler.ts` - معالج API
- `auth_store.ts` - مخزن المصادقة
- `ui_store.ts` - مخزن الواجهة

### Components

- `ExportAsExcel.vue` - تصدير Excel
- `ExportAsPdf.vue` - تصدير PDF
- `ExportAsImage.vue` - تصدير صورة
- `AppButton.vue` - زر التطبيق
- `Can.vue` - مكون الصلاحيات

### Layouts

- `PageHeader.vue` - رأس الصفحة
- `main-layout.vue` - التخطيط الرئيسي
- `app-sidebar.vue` - الشريط الجانبي
- `app-header.vue` - رأس التطبيق

### Views

- `dashboard.blade.php` - لوحة التحكم
- `login.blade.php` - صفحة تسجيل الدخول

### Lang Files

- `common.php` - ترجمات مشتركة
- `auth.php` - ترجمات المصادقة
- `setting.php` - ترجمات الإعدادات
- `profile.php` - ترجمات الملف الشخصي

## بنية ملفات الأمثلة المضمنة

الحزمة تحتوي على أمثلة جاهزة لثلاثة أنظمة رئيسية:

### 1. نظام المصادقة (Authentication)

نظام مصادقة كامل مع تسجيل الدخول، استعادة كلمة المرور، وإعادة تعيين كلمة المرور.

#### Backend Files

```
app/Http/Controllers/Auth/
├── AuthController.php                    # API Controller للمصادقة (Sanctum)
├── AuthenticatedSessionController.php    # Controller لتسجيل الدخول (Blade)
├── PasswordResetLinkController.php        # Controller لطلب إعادة تعيين كلمة المرور
└── NewPasswordController.php             # Controller لإعادة تعيين كلمة المرور

app/Http/Requests/Auth/
├── LoginRequest.php                      # Validation لتسجيل الدخول
├── RegisterRequest.php                   # Validation للتسجيل
└── PasswordResetRequest.php              # Validation لطلب إعادة تعيين كلمة المرور

routes/
├── auth.php                              # Routes للمصادقة (Blade)
└── api.php                               # Routes للمصادقة (API) - يضاف تلقائياً
```

#### Frontend Files (Vue 3)

```
resources/ts/_dashboard/pages/
├── Login.vue                             # صفحة تسجيل الدخول (Vue)
├── ForgotPassword.vue                     # صفحة طلب إعادة تعيين كلمة المرور
└── ResetPassword.vue                     # صفحة إعادة تعيين كلمة المرور

resources/ts/_dashboard/router/raws/
└── auth.ts                               # Vue Router routes للمصادقة
```

#### Blade Views

```
resources/views/auth/
├── login.blade.php                       # صفحة تسجيل الدخول (Blade)
├── forgot-password.blade.php             # صفحة طلب إعادة تعيين كلمة المرور
└── reset-password.blade.php             # صفحة إعادة تعيين كلمة المرور

resources/views/components/
├── layouts/auth.blade.php                # Layout لصفحات المصادقة
└── auth/
    ├── form-input.blade.php              # مكون حقل الإدخال
    ├── form-checkbox.blade.php           # مكون Checkbox
    └── alert.blade.php                   # مكون التنبيهات
```

#### Lang Files

```text
resources/lang/ar/
├── auth.php                              # ترجمات المصادقة
└── passwords.php                         # ترجمات كلمات المرور
```

#### المميزات

- ✅ تسجيل الدخول عبر API (Sanctum)
- ✅ تسجيل الدخول عبر Blade (Session)
- ✅ استعادة كلمة المرور
- ✅ إعادة تعيين كلمة المرور
- ✅ دعم Remember Me
- ✅ معالجة أخطاء موحدة

---

### 2. نظام البروفايل (Profile)

صفحة بروفايل المستخدم مع إمكانية تعديل البيانات الشخصية وتغيير كلمة المرور.

#### Backend Files

```
app/Http/Controllers/
└── (يستخدم UserController الموجود)

app/Http/Requests/
└── ChangePasswordRequest.php             # Validation لتغيير كلمة المرور
```

#### Frontend Files (Vue 3)

```
resources/ts/_dashboard/pages/
└── Profile.vue                            # صفحة البروفايل الرئيسية

resources/ts/_dashboard/router/raws/
└── settings.ts                            # يحتوي على route للبروفايل
```

#### المميزات

- ✅ عرض معلومات المستخدم (الاسم، البريد الإلكتروني، الصورة)
- ✅ تعديل البيانات الشخصية
- ✅ رفع وتغيير صورة البروفايل
- ✅ تغيير كلمة المرور
- ✅ عرض الأدوار والصلاحيات
- ✅ معاينة الصورة قبل الرفع

#### التبويبات

1. **معلوماتي** - تعديل البيانات الشخصية
2. **كلمة المرور** - تغيير كلمة المرور
3. **الأدوار والصلاحيات** - عرض الأدوار والصلاحيات الممنوحة

#### Lang Files

```
resources/lang/ar/
└── profile.php                            # ترجمات البروفايل
```

---

### 3. نظام الإعدادات (Settings)

صفحة إعدادات التطبيق مع إدارة معلومات التطبيق، الخطوط، والألوان.

#### Backend Files

```
app/Http/Controllers/
├── SettingController.php                 # Controller للإعدادات
└── Api/Admin/
    └── SettingsController.php            # API Controller للإعدادات

app/Http/Requests/
└── ApplicationRequest.php                # Validation لإعدادات التطبيق

app/Services/
└── SettingService.php                    # Service لإدارة الإعدادات

app/Http/Resources/
└── ApplicationResource.php               # Resource لإعدادات التطبيق

app/Models/
└── Application.php                       # Model لإعدادات التطبيق
```

#### Frontend Files (Vue 3)

```
resources/ts/_dashboard/pages/
├── Settings.vue                           # صفحة الإعدادات الرئيسية
└── _settings/
    └── ApplicationTab.vue                 # تبويب معلومات التطبيق

resources/ts/shared/components/
├── FontConfig.vue                         # مكون إعدادات الخطوط
└── ColorConfig.vue                        # مكون إعدادات الألوان

resources/ts/shared/composables/
├── useFontConfig.ts                       # Composable لإدارة الخطوط
└── useColorConfig.ts                      # Composable لإدارة الألوان

resources/ts/_dashboard/router/raws/
└── settings.ts                            # Vue Router routes للإعدادات
```

#### المميزات

- ✅ إدارة معلومات التطبيق (الاسم، الوصف، الشعار، Favicon)
- ✅ إعدادات الخطوط (عائلة الخط، الحجم)
- ✅ إعدادات الألوان (الألوان الأساسية، الثانوية)
- ✅ حفظ الإعدادات في قاعدة البيانات
- ✅ معاينة التغييرات مباشرة

#### التبويبات

1. **معلومات التطبيق** - إدارة معلومات التطبيق الأساسية
2. **الخطوط** - إعدادات الخطوط
3. **الألوان** - إعدادات الألوان

#### Database

```
database/migrations/
└── {timestamp}_create_applications_table.php  # جدول إعدادات التطبيق

database/seeders/
└── ApplicationSeeder.php                      # Seeder لإعدادات التطبيق
```

#### Lang Files

```
resources/lang/ar/
└── setting.php                             # ترجمات الإعدادات
```

---

### ملخص ملفات الأمثلة

| النظام | Backend Files | Frontend Files | Blade Views | Lang Files |
|-------|---------------|----------------|-------------|------------|
| **Authentication** | 4 Controllers, 3 Requests | 3 Vue Pages, 1 Router | 3 Pages, 4 Components | 2 Files |
| **Profile** | 1 Request | 1 Vue Page | - | 1 File |
| **Settings** | 2 Controllers, 1 Service, 1 Request, 1 Resource, 1 Model | 1 Vue Page, 2 Components, 2 Composables, 1 Router | - | 1 File |

### مزامنة ملفات الأمثلة

جميع ملفات الأمثلة هذه مدرجة في `SyncTemplatesCommand.php` ويمكن مزامنتها:

```bash
# مزامنة جميع ملفات الأمثلة
php artisan mic-sole:sync-templates --force

# مزامنة ملف محدد
php artisan mic-sole:sync-templates --file=resources/ts/_dashboard/pages/Profile.vue --force
php artisan mic-sole:sync-templates --file=resources/ts/_dashboard/pages/Settings.vue --force
php artisan mic-sole:sync-templates --file=resources/ts/_dashboard/pages/Login.vue --force
```

### ملاحظات مهمة

1. **ملفات Authentication**: يتم إنشاؤها تلقائياً عند استخدام `--with-example`
2. **ملفات Profile**: موجودة في كل مشروع، يمكن تخصيصها حسب الحاجة
3. **ملفات Settings**: تحتاج إلى Model و Migration منفصلين (Application)
4. **Routes**: يتم إضافتها تلقائياً إلى `routes/api.php` و `routes.ts`
5. **Middleware**: يتم إضافة `AuthGates` تلقائياً إلى `bootstrap/app.php`

## الخلاصة

- ✅ **القوالب الثابتة**: سهلة المزامنة عبر `mic-sole:sync-templates`
- ⚠️ **القوالب الديناميكية**: تحتاج تعديل يدوي (سيتم تحسينها لاحقاً)
- 🔄 **سير العمل**: تعديل → مزامنة → تحديث القوالب
- 📦 **النتيجة**: قوالب محدثة لجميع المشاريع

## الخطوات التالية

1. ✅ فهم نظام المزامنة الحالي
2. 🔄 تحسين معالجة القوالب الديناميكية
3. 🚀 تطوير نظام ذكي لاستبدال القيم بالـ placeholders

---

**ملاحظة**: هذا الدليل يشرح النظام الحالي. سيتم تحسين معالجة القوالب الديناميكية في المستقبل.
