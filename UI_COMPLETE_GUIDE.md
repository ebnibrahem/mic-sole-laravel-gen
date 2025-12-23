# دليل شامل - واجهة CRUD Generator

## 📋 جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [الأوامر المتاحة](#الأوامر-المتاحة)
3. [سير العمل](#سير-العمل)
4. [التبعيات المطلوبة](#التبعيات-المطلوبة)
5. [البدء السريع](#البدء-السريع)
6. [استكشاف الأخطاء](#استكشاف-الأخطاء)
7. [الملفات المطلوبة](#الملفات-المطلوبة)

---

## 📋 نظرة عامة

واجهة CRUD Generator هي واجهة مستخدم حديثة مبنية بـ React + TypeScript + Tailwind CSS لتوليد ملفات Laravel CRUD كاملة.

### المميزات

- ✅ مزامنة تلقائية أثناء التطوير
- ✅ تثبيت تلقائي للملفات والإعدادات
- ✅ دعم React 19 + TypeScript
- ✅ واجهة مستخدم حديثة مع Radix UI

---

## 🛠️ الأوامر المتاحة

### 1. `mic-sole:sync-ui-to-package`

**الوظيفة**: مزامنة ملفات الواجهة من المشروع إلى الحزمة (للتطوير)

**الاستخدام**:

```bash
# مزامنة تلقائية (Watch Mode)
php artisan mic-sole:sync-ui-to-package --watch

# مزامنة يدوية
php artisan mic-sole:sync-ui-to-package

# مزامنة ملف واحد فقط
php artisan mic-sole:sync-ui-to-package --file=resources/js/crud-generator/CrudGeneratorApp.tsx

# مزامنة بدون تأكيد
php artisan mic-sole:sync-ui-to-package --force

# معاينة فقط
php artisan mic-sole:sync-ui-to-package --dry-run
```

**ما يقوم به**:

- نسخ ملفات الواجهة من `resources/js/crud-generator/` إلى `mic-sole-laravel-gen/resources/js/crud-generator/`
- نسخ ملفات UI Components
- نسخ ملفات الدعم (mic-sole.tsx, mic-sole.css, generator.blade.php)

### 2. `mic-sole:install-ui`

**الوظيفة**: تثبيت ملفات الواجهة من الحزمة إلى المشروع (للمستخدمين)

**الاستخدام**:

```bash
# التثبيت الأساسي
php artisan mic-sole:install-ui

# مع تعديل package.json تلقائياً
php artisan mic-sole:install-ui --update-package-json

# تخطي إضافة routes
php artisan mic-sole:install-ui --skip-routes

# تخطي تحديث vite.config.js
php artisan mic-sole:install-ui --skip-vite

# تجاوز الملفات الموجودة
php artisan mic-sole:install-ui --force

# معاينة فقط
php artisan mic-sole:install-ui --dry-run
```

**ما يقوم به**:

1. ✅ نسخ ملفات الواجهة من الحزمة إلى المشروع
2. ✅ إضافة routes إلى `routes/web.php`
3. ✅ تحديث `vite.config.js`
4. ✅ فحص `package.json` (مع خيار التعديل التلقائي)
5. ✅ عرض الخطوات التالية

---

## 🔄 سير العمل

### أثناء التطوير (في المشروع)

#### المزامنة التلقائية (Watch Mode)

```bash
php artisan mic-sole:sync-ui-to-package --watch
```

**الاستخدام**:

- شغّل الأمر في terminal منفصل
- عدّل أي ملف في `resources/js/crud-generator/`
- سيتم مزامنة التغييرات تلقائياً إلى الحزمة
- اضغط `Ctrl+C` لإيقاف المراقبة

#### المزامنة اليدوية

```bash
# بعد التعديل
php artisan mic-sole:sync-ui-to-package
```

### بعد نشر الحزمة (للمستخدم)

#### 1. تثبيت الحزمة

```bash
composer require ebnibrahem/mic-sole-laravel-gen --dev
```

#### 2. تثبيت ملفات الواجهة

```bash
php artisan mic-sole:install-ui --update-package-json
```

هذا الأمر سيقوم بـ:

- ✅ نسخ ملفات الواجهة
- ✅ إضافة routes تلقائياً
- ✅ تحديث vite.config.js
- ✅ تعديل package.json (إذا استخدمت `--update-package-json`)
- ✅ عرض الخطوات التالية

#### 3. تثبيت التبعيات

```bash
npm install
```

#### 4. بناء الأصول

```bash
npm run dev
```

#### 5. فتح الواجهة

افتح المتصفح على:

```
http://your-app.test/generator
```

---

## 📦 التبعيات المطلوبة

### Production Dependencies (11 حزمة)

```json
{
  "dependencies": {
    "react": "^19.1.0",
    "react-dom": "^19.1.0",
    "lucide-react": "^0.525.0",
    "@radix-ui/react-checkbox": "^1.3.2",
    "@radix-ui/react-popover": "^1.1.14",
    "@radix-ui/react-select": "^2.2.5",
    "@radix-ui/react-slot": "^1.2.3",
    "@radix-ui/react-switch": "^1.2.5",
    "class-variance-authority": "^0.7.1",
    "clsx": "^2.1.1",
    "tailwind-merge": "^3.3.1"
  }
}
```

### Development Dependencies (6 حزم)

```json
{
  "devDependencies": {
    "@vitejs/plugin-react": "^4.7.0",
    "typescript": "^5.9.3",
    "vite": "^6.3.5",
    "laravel-vite-plugin": "^1.3.0",
    "tailwindcss": "^4.1.11",
    "@tailwindcss/vite": "^4.1.11"
  }
}
```

### شرح التبعيات

#### Production Dependencies

| الحزمة | الوصف | الاستخدام |
|--------|-------|-----------|
| `react` | مكتبة React الأساسية | بناء واجهة المستخدم |
| `react-dom` | React DOM renderer | عرض React في المتصفح |
| `lucide-react` | مكتبة الأيقونات | الأيقونات في الواجهة |
| `@radix-ui/react-checkbox` | مكون Checkbox | مربعات الاختيار |
| `@radix-ui/react-popover` | مكون Popover | النوافذ المنبثقة |
| `@radix-ui/react-select` | مكون Select | القوائم المنسدلة |
| `@radix-ui/react-slot` | مكون Slot | تكوين المكونات |
| `@radix-ui/react-switch` | مكون Switch | مفاتيح التبديل |
| `class-variance-authority` | إدارة variants | إدارة CSS variants |
| `clsx` | دمج CSS classes | دمج classes بذكاء |
| `tailwind-merge` | دمج Tailwind classes | دمج Tailwind بذكاء |

#### Development Dependencies

| الحزمة | الوصف | الاستخدام |
|--------|-------|-----------|
| `@vitejs/plugin-react` | Vite plugin لـ React | بناء React مع Vite |
| `typescript` | TypeScript compiler | التحقق من الأنواع |
| `vite` | Build tool | أداة البناء السريعة |
| `laravel-vite-plugin` | Laravel Vite plugin | التكامل مع Laravel |
| `tailwindcss` | Tailwind CSS framework | إطار CSS |
| `@tailwindcss/vite` | Tailwind Vite plugin | Tailwind مع Vite |

### تثبيت التبعيات

```bash
# Production dependencies
npm install react@^19.1.0 react-dom@^19.1.0 lucide-react@^0.525.0 @radix-ui/react-checkbox@^1.3.2 @radix-ui/react-popover@^1.1.14 @radix-ui/react-select@^2.2.5 @radix-ui/react-slot@^1.2.3 @radix-ui/react-switch@^1.2.5 class-variance-authority@^0.7.1 clsx@^2.1.1 tailwind-merge@^3.3.1

# Development dependencies
npm install --save-dev @vitejs/plugin-react@^4.7.0 typescript@^5.9.3 vite@^6.3.5 laravel-vite-plugin@^1.3.0 tailwindcss@^4.1.11 @tailwindcss/vite@^4.1.11
```

---

## 🚀 البدء السريع

### للمطور (تطوير الواجهة)

```bash
# 1. شغّل Watch Mode
php artisan mic-sole:sync-ui-to-package --watch

# 2. عدّل الملفات في resources/js/crud-generator/
# 3. التغييرات ستظهر تلقائياً في الحزمة
```

### للمستخدم (تثبيت الواجهة)

```bash
# 1. تثبيت الحزمة
composer require ebnibrahem/mic-sole-laravel-gen --dev

# 2. تثبيت ملفات الواجهة
php artisan mic-sole:install-ui --update-package-json

# 3. تثبيت التبعيات
npm install

# 4. بناء الأصول
npm run dev

# 5. شغّل Laravel Server (في terminal منفصل)
php artisan serve

# 6. افتح المتصفح
# http://localhost:8000/generator
```

### عرض الواجهة

```bash
# 1. تأكد من Routes
php artisan route:list | grep generator

# 2. شغّل Vite (في terminal منفصل)
npm run dev

# 3. شغّل Laravel Server (في terminal منفصل)
php artisan serve

# 4. افتح المتصفح
# http://localhost:8000/generator
```

---

## 🔍 استكشاف الأخطاء

### خطأ: Package path not found

**الحل**: تأكد من أن الحزمة موجودة في `mic-sole-laravel-gen/` في جذر المشروع.

### خطأ: Project file not found

**الحل**: تأكد من أن ملفات الواجهة موجودة في:

- `resources/js/crud-generator/`
- `resources/js/mic-sole.tsx`
- `resources/css/mic-sole.css`

### خطأ: Cannot find module 'react'

**الحل**:

```bash
npm install react react-dom
```

### خطأ: Cannot find module '@radix-ui/...'

**الحل**:

```bash
npm install @radix-ui/react-checkbox @radix-ui/react-popover @radix-ui/react-select @radix-ui/react-slot @radix-ui/react-switch
```

### خطأ: Cannot find module 'lucide-react'

**الحل**:

```bash
npm install lucide-react
```

### خطأ في Vite: Plugin react not found

**الحل**:

```bash
npm install --save-dev @vitejs/plugin-react
```

### خطأ: Route not found

**الحل**: تأكد من أن routes موجودة في `routes/web.php`:

```php
use MicSoleLaravelGen\Http\Controllers\CrudGeneratorController;
Route::post('/generator', [CrudGeneratorController::class, 'generate']);
Route::get('/generator', function () {
    return view('generator');
});
```

### خطأ: View not found

**الحل**: تأكد من وجود ملف:

```
resources/views/generator.blade.php
```

### خطأ: Vite assets not found

**الحل**:

1. تأكد من أن `npm run dev` يعمل
2. تأكد من أن `vite.config.js` يحتوي على:
   - `resources/js/mic-sole.tsx`
   - `resources/css/mic-sole.css`
   - `react()` plugin

### خطأ: Routes already exist

**الحل**: استخدم `--force` لتجاوز الملفات الموجودة:

```bash
php artisan mic-sole:install-ui --force
```

---

## 📁 الملفات المطلوبة

### ملفات الواجهة

- `resources/js/crud-generator/CrudGeneratorApp.tsx`
- `resources/js/crud-generator/Font.tsx`
- `resources/js/crud-generator/GeneratorTab.tsx`
- `resources/js/crud-generator/OtherFeaturesTab.tsx`
- `resources/js/crud-generator/PreviewGenerator.tsx`
- `resources/js/crud-generator/SelectMultiple.tsx`
- `resources/js/crud-generator/SettingsTab.tsx`
- `resources/js/crud-generator/types.ts`

### ملفات UI Components

- `resources/js/components/ui/button.tsx`
- `resources/js/components/ui/card.tsx`
- `resources/js/components/ui/checkbox.tsx`
- `resources/js/components/ui/input.tsx`
- `resources/js/components/ui/popover.tsx`
- `resources/js/components/ui/select.tsx`
- `resources/js/components/ui/switch.tsx`

### ملفات الدعم

- `resources/js/mic-sole.tsx` - نقطة الدخول
- `resources/css/mic-sole.css` - الأنماط
- `resources/views/generator.blade.php` - View

---

## 📝 ملاحظات مهمة

1. **Watch Mode**: استخدم `--watch` أثناء التطوير للمزامنة التلقائية
2. **Force Mode**: استخدم `--force` لتجاوز الملفات الموجودة بدون تأكيد
3. **Dry Run**: استخدم `--dry-run` لمعاينة التغييرات قبل التطبيق
4. **File Sync**: استخدم `--file` لمزامنة ملف واحد فقط
5. **Update Package.json**: استخدم `--update-package-json` لتعديل package.json تلقائياً

---

## ✅ الحالة الحالية

- ✅ الأوامر مسجلة ومتاحة
- ✅ الملفات منسوخة إلى الحزمة
- ✅ التوثيق جاهز
- ✅ جاهز للتطوير والاستخدام!

---

## 🎯 سير العمل الكامل

### أثناء التطوير

```
1. تعديل ملفات في resources/js/crud-generator/
   ↓
2. php artisan mic-sole:sync-ui-to-package --watch
   ↓
3. التغييرات تظهر تلقائياً في mic-sole-laravel-gen/
```

### بعد النشر

```
1. المستخدم: composer require package
   ↓
2. المستخدم: php artisan mic-sole:install-ui --update-package-json
   ↓
3. المستخدم: npm install
   ↓
4. المستخدم: npm run dev
   ↓
5. الواجهة جاهزة على /generator
```

---

## 🚀 ابدأ الآن

### للتطوير

```bash
php artisan mic-sole:sync-ui-to-package --watch
```

### للتثبيت

```bash
php artisan mic-sole:install-ui --update-package-json
npm install
npm run dev
```

---

**آخر تحديث**: تم دمج جميع ملفات التوثيق في ملف واحد شامل.
