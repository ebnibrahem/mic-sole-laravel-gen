# Mic Sole Laravel CRUD Generator

حزمة Laravel لتوليد CRUD كامل مع دعم Vue 3 Dashboard وواجهة React لتوليد الملفات

## المميزات

- ✅ توليد ملفات Backend كاملة (Model, Controller, Service, Request, Resource, Migration, Seeder, Factory, Policy)
- ✅ توليد ملفات Frontend (Vue 3 Pages, Components, Types, Routes)
- ✅ واجهة React حديثة لتوليد CRUD (React 19 + TypeScript + Tailwind CSS)
- ✅ دعم MicResponseTrait لتنسيق الاستجابات
- ✅ معالجة أخطاء موحدة (MICApiResponse)
- ✅ دعم Pagination والفلترة
- ✅ دعم Sorting والبحث
- ✅ دعم التصدير (Excel, PDF, Image)
- ✅ دعم الحذف المتعدد والتفعيل/إلغاء التفعيل المتعدد
- ✅ نظام صلاحيات مدمج (Permissions, Roles)
- ✅ واجهة مستخدم حديثة (Vue 3 + TypeScript + PrimeVue)
- ✅ نظام ترجمة متكامل
- ✅ Rollback تلقائي للأخطاء
- ✅ مزامنة تلقائية للقوالب والواجهة

## التثبيت

```bash
composer require ebnibrahem/mic-sole-laravel-gen --dev
```

## الإعداد السريع

### 1. إعداد Dashboard

```bash
php artisan mic-sole:init-dashboard --with-example
```

هذا الأمر سيقوم بـ:

- إنشاء بنية Dashboard كاملة
- إعداد Vite و TypeScript
- إنشاء نظام إدارة المستخدمين (User, Role, Permission) كمثال
- توليد جميع الملفات المطلوبة

### 2. تثبيت Dependencies

```bash
npm install
```

### 3. تشغيل Migrations

```bash
php artisan migrate
```

### 4. بناء التطبيق

```bash
npm run dev
# أو للـ production
npm run build
```

### 5. تثبيت واجهة CRUD Generator (اختياري)

```bash
php artisan mic-sole:install-ui --update-package-json
npm install
npm run dev
```

ثم افتح: `http://your-app.test/generator`

## الأوامر المتاحة

### `mic-sole:init-dashboard`

إنشاء بنية Dashboard كاملة

**Options:**

- `--with-example`: إنشاء مثال (User, Role, Permission)
- `--force`: استبدال الملفات الموجودة ومسح ملف `.mic`
- `--skip-validation`: تخطي التحقق من TypeScript

**Examples:**

```bash
php artisan mic-sole:init-dashboard
php artisan mic-sole:init-dashboard --with-example
php artisan mic-sole:init-dashboard --with-example --force
php artisan mic-sole:init-dashboard --with-example --fresh
```

### `mic-sole:setup-dashboard`

إعداد Dashboard فقط (بدون مثال)

```bash
php artisan mic-sole:setup-dashboard
```

### `mic-sole:status`

عرض حالة الملفات المولدة

**Options:**

- `--model=MODEL`: عرض ملفات موديل محدد
- `--type=TYPE`: عرض ملفات نوع محدد
- `--exists`: عرض الملفات الموجودة فقط
- `--missing`: عرض الملفات المفقودة فقط

**Examples:**

```bash
php artisan mic-sole:status
php artisan mic-sole:status --model=Post
php artisan mic-sole:status --exists
```

### `mic-sole:rollback`

التراجع عن الملفات المولدة

**Options:**

- `--level=N`: التراجع عن عدة مستويات (افتراضي: 1)
- `--id=ID`: التراجع عن توليد محدد
- `--list`: عرض قائمة بجميع التوليدات
- `--stats`: عرض إحصائيات
- `--preview`: معاينة ما سيتم حذفه واستعادته بدون تنفيذ التراجع

**Examples:**

```bash
# التراجع عن آخر جيل
php artisan mic-sole:rollback

# التراجع عن 3 أجيال
php artisan mic-sole:rollback --level=3

# معاينة التراجع قبل التنفيذ
php artisan mic-sole:rollback --preview

# عرض قائمة بجميع التوليدات
php artisan mic-sole:rollback --list

# التراجع عن جيل محدد
php artisan mic-sole:rollback --id=gen_1234567890
```

**ما يقوم به:**

1. ✅ حذف الملفات المولدة في الأجيال المحددة
2. ✅ استعادة الملفات المعدلة (مثل `routes/api.php` و `routes.ts`) من ملفات التعديلات المؤقتة
3. ✅ تنظيف ملفات التعديلات المؤقتة للأجيال المتراجع عنها
4. ✅ تحديث ملف `.mic` لإزالة الأجيال المتراجع عنها

### `mic-sole:sync-templates`

مزامنة التعديلات من الملفات المولدة إلى القوالب

**Options:**

- `--file=FILE`: مزامنة ملف محدد فقط
- `--force`: استبدال القوالب الموجودة بدون تأكيد
- `--dry-run`: عرض ما سيتم مزامنته بدون تنفيذ

**Examples:**

```bash
php artisan mic-sole:sync-templates
php artisan mic-sole:sync-templates --file=resources/ts/_dashboard/pages/Authorization.vue
php artisan mic-sole:sync-templates --force
```

### `mic-sole:verify-templates`

التحقق من تطابق القوالب مع الملفات المولدة

**Options:**

- `--type=TYPE`: التحقق من نوع محدد (backend, vue, all) - افتراضي: all
- `--detailed`: عرض جميع القوالب المفحوصة
- `--fix`: محاولة إصلاح المشاكل تلقائياً

**Examples:**

```bash
php artisan mic-sole:verify-templates
php artisan mic-sole:verify-templates --type=backend
php artisan mic-sole:verify-templates --type=vue --detailed
php artisan mic-sole:verify-templates --fix
```

**ما يقوم به:**

1. ✅ التحقق من وجود جميع القوالب المطلوبة
2. ✅ التحقق من وجود placeholders المطلوبة في كل قالب
3. ✅ التحقق من تطابق القوالب الثابتة مع الملفات المولدة
4. ✅ عرض تقرير بالمشاكل الموجودة

### `mic-sole:sync-ui-to-package`

مزامنة ملفات الواجهة من المشروع إلى الحزمة (للتطوير)

**Options:**

- `--file=FILE`: مزامنة ملف محدد فقط
- `--force`: استبدال الملفات الموجودة بدون تأكيد
- `--watch`: مراقبة التغييرات ومزامنتها تلقائياً
- `--dry-run`: عرض ما سيتم مزامنته بدون تنفيذ

**Examples:**

```bash
# مزامنة تلقائية (Watch Mode)
php artisan mic-sole:sync-ui-to-package --watch

# مزامنة يدوية
php artisan mic-sole:sync-ui-to-package

# مزامنة ملف واحد
php artisan mic-sole:sync-ui-to-package --file=resources/js/crud-generator/CrudGeneratorApp.tsx
```

### `mic-sole:install-ui`

تثبيت ملفات واجهة CRUD Generator من الحزمة إلى المشروع

**Options:**

- `--force`: استبدال الملفات الموجودة بدون تأكيد
- `--update-package-json`: تعديل package.json تلقائياً
- `--skip-routes`: تخطي إضافة routes
- `--skip-vite`: تخطي تحديث vite.config.js
- `--dry-run`: عرض ما سيتم تثبيته بدون تنفيذ

**Examples:**

```bash
php artisan mic-sole:install-ui
php artisan mic-sole:install-ui --update-package-json
php artisan mic-sole:install-ui --force
```

**ما يقوم به:**

1. ✅ نسخ ملفات الواجهة (React components)
2. ✅ إضافة routes إلى `routes/web.php`
3. ✅ تحديث `vite.config.js` (إضافة React plugin و include/exclude)
4. ✅ فحص `package.json` (مع خيار التعديل التلقائي)
5. ✅ عرض الخطوات التالية

### `mic-sole:add-manual-files`

إضافة ملفات Blade يدوية إلى نظام التتبع

```bash
php artisan mic-sole:add-manual-files
```

## استخدام واجهة CRUD Generator

بعد تثبيت الواجهة (`mic-sole:install-ui`):

1. افتح المتصفح: `http://your-app.test/generator`
2. أدخل بيانات الموديل:
   - اسم الموديل (مثل: `Product`)
   - الحقول (Name, Type, Required, Unique, Show In)
   - العلاقات (Relationships)
   - الخيارات (Permissions, Translations)
3. اضغط "Generate"
4. سيتم توليد جميع الملفات تلقائياً

## البنية المولدة

### Backend Files

- `app/Models/{{Model}}.php`
- `app/Http/Controllers/{{Model}}Controller.php`
- `app/Services/{{Model}}Service.php`
- `app/Http/Requests/{{Model}}Request.php`
- `app/Http/Resources/{{Model}}Resource.php`
- `database/migrations/{{timestamp}}_create_{{table}}_table.php`
- `database/seeders/{{Model}}Seeder.php`
- `database/factories/{{Model}}Factory.php`
- `app/Policies/{{Model}}Policy.php`
- `routes/api/dashboard/api-{{models}}.php`

### Frontend Files (Vue 3)

- `resources/ts/_dashboard/pages/{{Models}}.vue` (List Page)
- `resources/ts/_dashboard/pages/{{Model}}Create.vue` (Create Page)
- `resources/ts/_dashboard/pages/{{Model}}.vue` (Detail Page)
- `resources/ts/_dashboard/pages/_{{models}}/table.vue` (Table Component)
- `resources/ts/_dashboard/pages/_{{models}}/form.vue` (Form Component)
- `resources/ts/_dashboard/pages/_{{models}}/form-show.vue` (Show Component)
- `resources/ts/_dashboard/pages/_{{models}}/filter.vue` (Filter Component)
- `resources/ts/shared/types/{{model}}.ts` (TypeScript Types)
- `resources/ts/_dashboard/router/raws/{{model}}.ts` (Vue Router Routes)

## نظام الصلاحيات

الحزمة تدعم نظام صلاحيات مدمج:

- `{{model}}_access` - الوصول للصفحة
- `{{model}}_view` - عرض
- `{{model}}_create` - إنشاء
- `{{model}}_edit` - تعديل
- `{{model}}_delete` - حذف

## نظام الترجمة

الترجمات موجودة في `resources/lang/{{locale}}/`:

- `common.php` - ترجمات مشتركة
- `auth.php` - ترجمات المصادقة
- `{{model}}.php` - ترجمات خاصة بالنموذج

### استخدام الترجمة في Vue

```typescript
import { c } from '@dashboard/utilities/langHelper';

c('dashboard', 'common') // "لوحة التحكم"
c('plural', 'user') // "مستخدمين"
```

## نظام تتبع الملفات

الحزمة تدعم تتبع الملفات المولدة:

- تتبع جميع الملفات في ملف `.mic` (قاعدة بيانات الهيستوري)
- حفظ حالة التوليد في `storage/app/mic-sole-laravel-gen/`
- حفظ التعديلات على الملفات المشتركة (مثل `routes/api.php` و `routes.ts`) في ملفات مؤقتة
- عرض حالة الملفات عبر `php artisan mic-sole:status`
- Rollback تلقائي عند فشل TypeScript validation
- Rollback يدوي عبر `php artisan mic-sole:rollback`
- معاينة التراجع قبل التنفيذ عبر `php artisan mic-sole:rollback --preview`

### بنية ملفات التتبع

```text
storage/app/mic-sole-laravel-gen/
├── index.json                    # ملف الهيستوري الرئيسي
├── generations/                  # ملفات الأجيال الفردية
│   ├── gen_1234567890.json      # ملفات الجيل الأول
│   └── gen_9876543210.json      # ملفات الجيل الثاني
└── modifications/                # ملفات التعديلات المؤقتة
    ├── gen_1234567890.json      # تعديلات الجيل الأول
    └── gen_9876543210.json      # تعديلات الجيل الثاني
```

### كيف يعمل نظام التتبع

1. **عند التوليد:**
   - يتم حفظ معلومات الجيل في `index.json`
   - يتم حفظ قائمة الملفات المولدة في `generations/{generationId}.json`
   - عند تعديل ملفات مشتركة (مثل `routes/api.php`):
     - يتم حفظ المحتوى الأصلي قبل التعديل
     - يتم حفظ التعديلات المضافة في `modifications/{generationId}.json`

2. **عند التراجع:**
   - يتم حذف الملفات المولدة
   - يتم استعادة الملفات المعدلة من ملفات التعديلات المؤقتة
   - يتم حذف ملفات التعديلات المؤقتة للأجيال المتراجع عنها
   - يتم تحديث `index.json` لإزالة الأجيال المتراجع عنها

3. **معاينة التراجع:**
   - يعرض الملفات التي سيتم حذفها
   - يعرض الملفات التي سيتم استعادتها
   - يعرض الأجيال التي سيتم إزالتها
   - لا يقوم بأي تعديلات على الملفات

## المتطلبات

- PHP >= 8.2
- Laravel >= 12.0
- Node.js >= 18.0
- Vue 3
- PrimeVue
- TypeScript
- Pinia

## الترخيص

MIT

## الدعم

للدعم والمساعدة، يرجى فتح Issue في المستودع.

## المطور

- محمدابراهيم
- <ebnibrahem@gmail.com>
- 249915102431

## ملفات التوثيق الإضافية

- `UI_COMPLETE_GUIDE.md` - دليل شامل لواجهة CRUD Generator
- `QUICK_START_AR.md` - دليل البدء السريع بالعربية
