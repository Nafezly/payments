# حل مشكلة Apple Pay في Moyasar

## 🔴 المشكلة

عند استخدام Apple Pay مع Moyasar، تظهر الأخطاء التالية:
```
Apple Pay label is required
Validate Merchat URL is required for Apple Pay
Country is required for Apple Pay
```

---

## ✅ الحل

### 1. أضف الإعدادات في ملف `.env`:

```env
# Moyasar - Apple Pay Configuration
MOYASAR_APPLE_PAY_LABEL="Your Store Name"
MOYASAR_APPLE_PAY_VALIDATE_URL="https://yourdomain.com"
MOYASAR_APPLE_PAY_COUNTRY=SA
```

### 2. شرح كل إعداد:

#### `MOYASAR_APPLE_PAY_LABEL`
- **الوصف:** اسم متجرك الذي سيظهر للعميل في واجهة Apple Pay
- **مثال:** `"Ashara Store"` أو `"متجر أشارة"`
- **افتراضي:** سيستخدم `APP_NAME` إذا لم يتم تعريفه

#### `MOYASAR_APPLE_PAY_VALIDATE_URL`
- **الوصف:** رابط موقعك الرئيسي (يُستخدم للتحقق من هوية التاجر)
- **مثال:** `"https://ashara-lms.test"` أو `"https://yourdomain.com"`
- **افتراضي:** سيستخدم `url('/')` إذا لم يتم تعريفه

#### `MOYASAR_APPLE_PAY_COUNTRY`
- **الوصف:** رمز الدولة (ISO 3166-1 alpha-2)
- **أمثلة:** 
  - `SA` = السعودية
  - `AE` = الإمارات
  - `KW` = الكويت
  - `EG` = مصر
  - `US` = أمريكا
- **افتراضي:** `SA`

---

## 📝 مثال كامل للإعدادات

```env
# Moyasar Payment Gateway
MOYASAR_SECRET_KEY=sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxx
MOYASAR_PUBLISHABLE_KEY=pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxx
MOYASAR_CURRENCY=SAR

# Apple Pay Configuration (إذا كنت تستخدم Apple Pay)
MOYASAR_APPLE_PAY_LABEL="Ashara LMS"
MOYASAR_APPLE_PAY_VALIDATE_URL="https://ashara-lms.test"
MOYASAR_APPLE_PAY_COUNTRY=SA
```

---

## 🎯 الكود المحدث

تم تحديث الكود ليتضمن:

### في `MoyasarPayment.php`:
```php
// Apple Pay Configuration (required if Apple Pay is enabled)
if (in_array('applepay', $payment_methods)) {
    $data['apple_pay'] = [
        'label' => config('nafezly-payments.MOYASAR_APPLE_PAY_LABEL', config('nafezly-payments.APP_NAME')),
        'validate_merchant_url' => config('nafezly-payments.MOYASAR_APPLE_PAY_VALIDATE_URL', url('/')),
        'country' => config('nafezly-payments.MOYASAR_APPLE_PAY_COUNTRY', 'SA'),
    ];
}
```

### في `moyasar.blade.php`:
```javascript
Moyasar.init({
    // ... other settings
    @if(isset($data['apple_pay']))
    apple_pay: {!! json_encode($data['apple_pay']) !!},
    @endif
});
```

---

## 🔄 كيف تعمل؟

عندما تستخدم الكود الخاص بك:

```php
$paymentResponse = $paymentInstance->setCurrency($currencySymbol)->pay(
    round($totalFinal, 2),
    $user->id,
    $firstName,
    $lastName,
    $user->email,
    $phoneUser
);
```

الآن ستعمل Apple Pay بشكل صحيح إذا:
1. ✅ أضفت الإعدادات في `.env`
2. ✅ قمت بتنفيذ `php artisan config:cache` (إذا كنت في production)

---

## 📌 ملاحظات مهمة

### 1. Apple Pay يتطلب HTTPS
- ❌ لن يعمل على HTTP في production
- ✅ يعمل على localhost للاختبار
- ✅ يتطلب SSL certificate صالح

### 2. التحقق من المجال (Domain Verification)
- يجب أن يكون `MOYASAR_APPLE_PAY_VALIDATE_URL` مطابقًا للمجال الحقيقي
- مثلاً إذا كان موقعك `https://ashara-lms.test` فاستخدمه كما هو

### 3. الدول المدعومة
استخدم رموز ISO 3166-1 alpha-2:
- `SA` = 🇸🇦 السعودية
- `AE` = 🇦🇪 الإمارات
- `KW` = 🇰🇼 الكويت
- `BH` = 🇧🇭 البحرين
- `OM` = 🇴🇲 عمان
- `QA` = 🇶🇦 قطر
- `EG` = 🇪🇬 مصر
- `JO` = 🇯🇴 الأردن

---

## 🧪 الاختبار

### 1. تعطيل Apple Pay مؤقتًا (للاختبار):
```php
// في الكود الخاص بك
$paymentResponse = $paymentInstance
    ->setCurrency($currencySymbol)
    ->setSource('creditcard') // استخدم البطاقات فقط
    ->pay(...);
```

### 2. تفعيل جميع الطرق:
```php
// لا تحدد source - سيظهر كل الخيارات
$paymentResponse = $paymentInstance
    ->setCurrency($currencySymbol)
    ->pay(...);
```

### 3. Apple Pay فقط:
```php
$paymentResponse = $paymentInstance
    ->setCurrency($currencySymbol)
    ->setSource('applepay')
    ->pay(...);
```

---

## ✅ التحقق من الإصلاح

بعد إضافة الإعدادات، يجب أن:
1. ✅ تختفي رسائل الخطأ الثلاثة
2. ✅ يظهر نموذج الدفع بشكل صحيح
3. ✅ تظهر خيارات الدفع المتاحة

---

## 📞 إذا استمرت المشكلة

تحقق من:
1. تنفيذ `php artisan config:clear`
2. تنفيذ `php artisan cache:clear`
3. التأكد من أن ملف `.env` يحتوي على القيم الصحيحة
4. فحص console المتصفح للأخطاء
5. التأكد من أن الموقع يعمل على HTTPS

---

## 🎉 الخلاصة

**المشكلة:** Apple Pay يتطلب 3 إعدادات إضافية

**الحل:** أضف في `.env`:
```env
MOYASAR_APPLE_PAY_LABEL="Your Store Name"
MOYASAR_APPLE_PAY_VALIDATE_URL="https://yourdomain.com"
MOYASAR_APPLE_PAY_COUNTRY=SA
```

**النتيجة:** Apple Pay سيعمل بشكل صحيح! ✅

---

**تم التحديث:** 16 أكتوبر 2025  
**الكود محدّث في:** `MoyasarPayment.php` و `moyasar.blade.php`
