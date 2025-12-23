# اختبار الامثلة المدمجة مع الحزمة

```bash
# 0. الحصول على التوكن
 php artisan tinker --execute="echo User::find(1)->createToken('test-token')->plainTextToken;"

# 1. إعادة توليد الأمثلة
php artisan mic-sole:init-dashboard --with-example --fresh --force

# 2. فحص الملفات ومقارنتها مع القوالب
php testing/verify_example_files.php

# 3. اختبار عمليات CRUD
node testing/crud_operations.js

# 4. اختبار API
node testing/user_api.js
