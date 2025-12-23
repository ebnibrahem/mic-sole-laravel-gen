# دليل البدء السريع - إضافة وحدة جديدة

## الخطوات السريعة لإضافة موديل جديد عبر الواجهة المرئية

### 1️⃣ الوصول للواجهة
افتح المتصفح وانتقل إلى: `http://localhost/generator`

### 2️⃣ إدخال بيانات الموديل

#### أ. اسم الموديل
- أدخل اسم الموديل في حقل "Model/Entity Name"
- مثال: `Product`, `Order`, `Category`
- استخدم PascalCase (الحرف الأول كبير)

#### ب. إضافة الحقول

**خيار 1: إضافة يدوية**
1. اضغط "Add Field"
2. املأ بيانات الحقل:
   - **Name**: اسم الحقل (مثل: `title`, `price`)
   - **Type**: نوع الحقل (`string`, `integer`, `float`, `boolean`, `text`, `date`, `enum`)
   - **Required**: هل الحقل إلزامي؟
   - **Unique**: هل يجب أن يكون فريداً؟
   - **Show In**: أين يظهر؟ (`add`, `edit`, `show`, `table`)

**خيار 2: استيراد من JSON**
1. اضغط "Paste Fields as JSON"
2. الصق JSON:
```json
[
  {
    "name": "title",
    "type": "string",
    "required": true,
    "unique": false,
    "default": "",
    "min": "",
    "max": "",
    "enumValues": "",
    "showIn": ["add", "edit", "show", "table"]
  },
  {
    "name": "price",
    "type": "float",
    "required": true,
    "unique": false,
    "default": "0",
    "min": "0",
    "max": "999999",
    "enumValues": "",
    "showIn": ["add", "edit", "show", "table"]
  }
]
```
3. اضغط "Import Fields from JSON"

### 3️⃣ اختيار الملفات (تبويب Settings)

**Backend Files:**
- ✅ Model
- ✅ Migration
- ✅ Controller
- ✅ Request
- ✅ Resource
- ✅ Service
- ✅ Routes
- ✅ Lang

**Frontend Files (Vue.js):**
- ✅ List Page
- ✅ Table Component
- ✅ Form Component
- ✅ Single Page
- ✅ Create Page
- ✅ Filter
- ✅ Types
- ✅ Routes

### 4️⃣ الإعدادات الإضافية
- ✅ **Permissions**: تفعيل نظام الصلاحيات
- ✅ **Translations**: تفعيل نظام الترجمة

### 5️⃣ التوليد
اضغط زر **"Generate"** وانتظر اكتمال العملية

### 6️⃣ الخطوات التالية

```bash
# 1. تشغيل Migration
php artisan migrate

# 2. بناء المشروع (إذا لزم الأمر)
npm run build
# أو
npm run dev
```

## الأوامر المتاحة

### إنشاء Dashboard
```bash
# بدون مثال
php artisan mic-sole:init-dashboard

# مع مثال (User, Role, Permission)
php artisan mic-sole:init-dashboard --with-example

# استبدال الملفات الموجودة
php artisan mic-sole:init-dashboard --force
php artisan mic-sole:init-dashboard --with-example --force

# تخطي التحقق من TypeScript (أسرع)
php artisan mic-sole:init-dashboard --with-example --force --skip-validation
```

### عرض حالة الملفات
```bash
# عرض جميع الملفات
php artisan mic-sole:status

# عرض ملفات موديل محدد
php artisan mic-sole:status --model=Post

# عرض الملفات الموجودة فقط
php artisan mic-sole:status --exists

# عرض الملفات المفقودة فقط
php artisan mic-sole:status --missing
```

### التراجع (Rollback)
```bash
# التراجع عن آخر عملية
php artisan mic-sole:rollback

# عرض القائمة
php artisan mic-sole:rollback --list

# عرض الإحصائيات
php artisan mic-sole:rollback --stats

# التراجع عن عدة مستويات
php artisan mic-sole:rollback --level=3
```

## البنية المولدة

### Backend
- Model, Controller, Service, Request, Resource
- Migration, Seeder, Factory, Policy
- Routes, Lang files

### Frontend (Vue.js)
- List Page (`Products.vue`)
- Create Page (`ProductCreate.vue`)
- Detail Page (`Product.vue`)
- Components (table, form, filter)
- TypeScript Types
- Vue Router Routes

## ملاحظات

1. ✅ استخدم PascalCase للموديلات (`Product`)
2. ✅ استخدم snake_case للحقول (`product_name`)
3. ✅ يتم التحقق من TypeScript تلقائياً
4. ✅ Rollback تلقائي في حالة الأخطاء
5. ✅ جميع الملفات متتبعة في `.mic`

## استكشاف الأخطاء

- **الواجهة لا تظهر**: تأكد من تشغيل `npm run dev`
- **خطأ في التوليد**: تحقق من الصلاحيات والمساحة
- **أخطاء TypeScript**: استخدم `rollback` ثم أعد التوليد

