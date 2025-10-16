# Moyasar API Keys - التوضيح الكامل

## 🔑 أنواع المفاتيح في Moyasar

بناءً على [وثائق Moyasar الرسمية](https://docs.moyasar.com/api/authentication)، يستخدم Moyasar **نوعين فقط** من المفاتيح:

---

## 1️⃣ Secret Key (المفتاح السري)

### 📝 الوصف:
- **الشكل:** `sk_test_xxxxxx` (للاختبار) أو `sk_live_xxxxxx` (للإنتاج)
- **الاستخدام:** Backend فقط
- **الصلاحيات:** جميع العمليات على حسابك

### ✅ استخدامات Secret Key:
- ✅ التحقق من الدفع (Verify Payment)
- ✅ استرجاع معلومات الدفع (Fetch Payment)
- ✅ قائمة المدفوعات (List Payments)
- ✅ استرداد المبالغ (Refunds)
- ✅ جميع عمليات API الأخرى

### ⚠️ ملاحظات أمان:
- ❌ **لا تشاركه أبدًا في Frontend**
- ❌ **لا تضعه في كود JavaScript**
- ❌ **لا ترفعه على GitHub بدون تشفير**
- ✅ استخدمه فقط في Backend (PHP, Laravel, Node.js Server)
- ✅ احفظه في `.env` file

### 📌 مثال الاستخدام:
```php
// ✅ صحيح - في Backend
$response = Http::withBasicAuth($this->moyasar_secret_key, '')
    ->get('https://api.moyasar.com/v1/payments/' . $payment_id)
    ->json();
```

---

## 2️⃣ Publishable Key (المفتاح القابل للنشر)

### 📝 الوصف:
- **الشكل:** `pk_test_xxxxxx` (للاختبار) أو `pk_live_xxxxxx` (للإنتاج)
- **الاستخدام:** Frontend (JavaScript, HTML)
- **الصلاحيات:** محدودة - إنشاء الدفع فقط

### ✅ استخدامات Publishable Key:
- ✅ إنشاء نموذج الدفع (Payment Form)
- ✅ إنشاء الدفع من Frontend مباشرة
- ✅ توكينات البطاقات (Card Tokenization)

### ✅ ملاحظات أمان:
- ✅ آمن للاستخدام في Frontend
- ✅ يمكن وضعه في كود JavaScript
- ✅ يمكن أن يراه المستخدم
- ⚠️ لكن صلاحياته محدودة جدًا

### 📌 مثال الاستخدام:
```javascript
// ✅ صحيح - في Frontend
Moyasar.init({
    element: '.mysr-form',
    amount: 10000,
    currency: 'SAR',
    publishable_api_key: 'pk_test_xxxxxxxxxx', // Publishable Key
    callback_url: 'https://example.com/verify'
});
```

---

## 🔄 كيف تعمل المفاتيح معًا؟

```
Frontend (المتصفح)
    ↓
    🔑 Publishable Key
    ↓
    [نموذج الدفع Moyasar]
    ↓
    [المستخدم يدخل بيانات البطاقة]
    ↓
    [Moyasar يعالج الدفع]
    ↓
    [Redirect to callback_url?id=payment_123]
    ↓
Backend (السيرفر)
    ↓
    🔐 Secret Key
    ↓
    [GET /v1/payments/payment_123]
    ↓
    [التحقق من حالة الدفع]
    ↓
    [تأكيد أو رفض الطلب]
```

---

## ❌ الأخطاء الشائعة

### ❌ خطأ 1: استخدام Secret Key في Frontend
```javascript
// ❌ خطأ خطير!
Moyasar.init({
    publishable_api_key: 'sk_test_xxxxxxxxxx' // Secret Key في Frontend!
});
```

### ❌ خطأ 2: استخدام Publishable Key للتحقق
```php
// ❌ لن يعمل!
$response = Http::withBasicAuth('pk_test_xxxxx', '') // Publishable Key
    ->get('https://api.moyasar.com/v1/payments/' . $id);
// سيرجع خطأ 401 Unauthorized
```

---

## ✅ الطريقة الصحيحة في Laravel

### 📁 `.env` file:
```env
# مفتاحين فقط
MOYASAR_SECRET_KEY=sk_test_xxxxxxxxxx
MOYASAR_PUBLISHABLE_KEY=pk_test_xxxxxxxxxx
```

### 📁 `config/nafezly-payments.php`:
```php
'MOYASAR_SECRET_KEY' => env('MOYASAR_SECRET_KEY'),
'MOYASAR_PUBLISHABLE_KEY' => env('MOYASAR_PUBLISHABLE_KEY'),
```

### 📁 `MoyasarPayment.php`:
```php
public function __construct()
{
    // Secret Key - للاستخدام في Backend
    $this->moyasar_secret_key = config('nafezly-payments.MOYASAR_SECRET_KEY');
    
    // Publishable Key - للاستخدام في Frontend
    $this->moyasar_publishable_key = config('nafezly-payments.MOYASAR_PUBLISHABLE_KEY');
}

public function pay() 
{
    // ✅ إرسال Publishable Key للـ Frontend
    return [
        'html' => view('payment', [
            'publishable_key' => $this->moyasar_publishable_key
        ])
    ];
}

public function verify($request)
{
    // ✅ استخدام Secret Key للتحقق في Backend
    $response = Http::withBasicAuth($this->moyasar_secret_key, '')
        ->get('https://api.moyasar.com/v1/payments/' . $request->id);
}
```

---

## 🎯 الخلاصة

| المفتاح | الاستخدام | المكان | الصلاحيات |
|---------|-----------|--------|-----------|
| **Secret Key** | Backend API | Server | **كاملة** |
| **Publishable Key** | Payment Form | Frontend | **محدودة** |

**القاعدة الذهبية:**
- 🔐 Secret Key = Backend فقط
- 🌐 Publishable Key = Frontend فقط

---

## 📚 المراجع الرسمية

- [Moyasar Authentication Docs](https://docs.moyasar.com/api/authentication)
- [Moyasar API Keys Guide](https://docs.moyasar.com/guides/dashboard/get-your-api-keys)
- [Moyasar Basic Integration](https://docs.moyasar.com/guides/card-payments/basic-integration)

---

**تم التحديث:** 16 أكتوبر 2025  
**بناءً على:** Moyasar Official Documentation
