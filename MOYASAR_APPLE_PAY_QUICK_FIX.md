# ✅ حل سريع لمشكلة Apple Pay

## 🔴 المشكلة:
```
Apple Pay label is required
Validate Merchat URL is required for Apple Pay
Country is required for Apple Pay
```

## ✅ الحل السريع:

### أضف هذه الأسطر في ملف `.env`:

```env
MOYASAR_APPLE_PAY_LABEL="اسم متجرك"
MOYASAR_APPLE_PAY_VALIDATE_URL="https://yourwebsite.com"
MOYASAR_APPLE_PAY_COUNTRY=SA
```

### ثم نفذ:
```bash
php artisan config:clear
php artisan cache:clear
```

## 🎯 مثال للمشروع الخاص بك:

```env
# في ملف .env
MOYASAR_APPLE_PAY_LABEL="Ashara LMS"
MOYASAR_APPLE_PAY_VALIDATE_URL="https://ashara-lms.test"
MOYASAR_APPLE_PAY_COUNTRY=SA
```

## 📌 ملاحظات:

1. **Label**: اسم متجرك (يظهر للعميل)
2. **URL**: رابط موقعك الحقيقي
3. **Country**: رمز البلد (SA للسعودية)

## ✅ الآن المشكلة محلولة! 🎉

للمزيد من التفاصيل، راجع: `MOYASAR_APPLE_PAY_FIX.md`
