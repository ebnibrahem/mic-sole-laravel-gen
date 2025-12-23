# إعداد الحزمة للنشر على Packagist

## الخطوات المطلوبة

### 1. إنشاء مستودع Git

```bash
cd mic-sole-laravel-gen
git init
git add .
git commit -m "Initial commit"
```

### 2. رفع المشروع إلى GitHub

```bash
# إنشاء مستودع جديد على GitHub باسم: ebnibrahem/mic-sole-laravel-gen
# ثم:
git remote add origin https://github.com/ebnibrahem/mic-sole-laravel-gen.git
git branch -M main
git push -u origin main
```

### 3. إنشاء Tag للإصدار الأول

```bash
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0
```

### 4. تسجيل الحزمة على Packagist

1. اذهب إلى [Packagist.org](https://packagist.org)
2. سجل دخول بحساب GitHub
3. اضغط على "Submit" في القائمة العلوية
4. أدخل رابط المستودع: `https://github.com/ebnibrahem/mic-sole-laravel-gen`
5. اضغط "Check" ثم "Submit"

### 5. تفعيل Auto-Update (اختياري)

1. بعد تسجيل الحزمة، اذهب إلى صفحة الحزمة
2. اضغط على "Settings"
3. قم بتفعيل "GitHub Service Hook" أو "Update" تلقائي

### 6. تحديث معلومات المؤلف (اختياري)

قم بتحديث البريد الإلكتروني في `composer.json` إذا لزم الأمر:

```json
"authors": [
    {
        "name": "Ebrahim",
        "email": "your-email@example.com"
    }
]
```

## ملاحظات مهمة

- ✅ تأكد من أن `composer.json` يحتوي على جميع المعلومات المطلوبة
- ✅ تأكد من وجود `.gitignore` مناسب
- ✅ تأكد من وجود `README.md` واضح ومفصل
- ✅ استخدم Semantic Versioning للـ tags (v1.0.0, v1.0.1, v1.1.0, etc.)
- ✅ بعد كل تحديث، أنشئ tag جديد وارفعه

## التثبيت بعد النشر

بعد النشر على Packagist، يمكن تثبيت الحزمة باستخدام:

```bash
composer require ebnibrahem/mic-sole-laravel-gen --dev
```

## روابط مفيدة

- [Packagist Documentation](https://packagist.org/about)
- [Semantic Versioning](https://semver.org/)
- [Composer Documentation](https://getcomposer.org/doc/)

