<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garanti BBVA 3D Secure</title>
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #1e40af;
            --bg: #f4f6fb;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px;
        }
        .container {
            background: #fff;
            width: 100%;
            max-width: 560px;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0 0 6px;
            font-size: 22px;
            color: var(--primary);
        }
        .header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        .amount {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            margin-bottom: 18px;
        }
        .amount span {
            display: block;
            font-size: 14px;
            color: var(--muted);
        }
        .amount strong {
            font-size: 22px;
            color: var(--primary-dark);
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 12px;
        }
        label {
            font-size: 13px;
            color: var(--muted);
        }
        input {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 14px;
            outline: none;
        }
        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.15);
        }
        .submit {
            width: 100%;
            background: var(--primary);
            border: none;
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .submit:hover {
            background: var(--primary-dark);
        }
        .note {
            margin-top: 12px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }
        .hint {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }
        input:invalid {
            border-color: #ef4444;
        }
        @media (max-width: 520px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Garanti BBVA 3D Secure</h1>
        <p>Güvenli ödeme için kart bilgilerinizi giriniz</p>
    </div>

    <div class="amount">
        <span>Tahsil Edilecek Tutar</span>
        <strong>{{ $data['display_amount'] }}</strong>
    </div>

    <form method="post" action="{{ $data['action_url'] }}">
        <input type="hidden" name="mode" id="mode" value="{{ $data['mode'] }}" />
        <input type="hidden" name="apiversion" id="apiversion" value="{{ $data['api_version'] }}" />
        <input type="hidden" name="secure3dsecuritylevel" id="secure3dsecuritylevel" value="{{ $data['security_level'] }}" />
        <input type="hidden" name="terminalprovuserid" id="terminalprovuserid" value="{{ $data['terminalprovuserid'] }}" />
        <input type="hidden" name="terminaluserid" id="terminaluserid" value="{{ $data['terminaluserid']??'GARANTI' }}" />
        <input type="hidden" name="terminalmerchantid" id="terminalmerchantid" value="{{ $data['terminalmerchantid'] }}" />
        <input type="hidden" name="terminalid" id="terminalid" value="{{ $data['terminalid'] }}" />
        <input type="hidden" name="orderid" id="orderid" value="{{ $data['orderid'] }}" />
        <input type="hidden" name="successurl" id="successurl" value="{{ $data['successurl'] }}" />
        <input type="hidden" name="errorurl" id="errorurl" value="{{ $data['errorurl'] }}" />
        <input type="hidden" name="customeremailaddress" id="customeremailaddress" value="{{ $data['customeremailaddress'] }}" />
        <input type="hidden" name="customeripaddress" id="customeripaddress" value="{{ $data['customeripaddress'] }}" />
        <input type="hidden" name="companyname" id="companyname" value="{{ $data['companyname'] }}" />
        <input type="hidden" name="lang" id="lang" value="{{ $data['lang'] }}" />
        <input type="hidden" name="txntimestamp" id="txntimestamp" value="{{ $data['txntimestamp'] }}" />
        <input type="hidden" name="refreshtime" id="refreshtime" value="{{ $data['refreshtime'] }}" />
        <input type="hidden" name="secure3dhash" id="secure3dhash" value="{{ $data['secure3dhash'] }}" />
        <input type="hidden" name="txnamount" id="txnamount" value="{{ $data['txnamount'] }}" />
        <input type="hidden" name="txntype" id="txntype" value="{{ $data['txntype'] }}" />
        <input type="hidden" name="txncurrencycode" id="txncurrencycode" value="{{ $data['txncurrencycode'] }}" />
        <input type="hidden" name="txninstallmentcount" id="txninstallmentcount" value="{{ $data['txninstallmentcount'] }}" />

        <div class="field">
            <label for="cardholdername">Kart Üzerindeki Ad Soyad</label>
            <input type="text" id="cardholdername" name="cardholdername" value="{{ $data['cardholdername'] }}" required />
        </div>

        <div class="field">
            <label for="cardnumber">Kart Numarası</label>
            <input type="text" id="cardnumber" name="cardnumber" inputmode="numeric" autocomplete="cc-number" 
                   pattern="[0-9]{13,19}" maxlength="19" placeholder="0000 0000 0000 0000" required />
            <small class="hint">16 haneli kart numaranızı giriniz</small>
        </div>

        <div class="grid">
            <div class="field">
                <label for="cardexpiredatemonth">Son Kullanma (Ay)</label>
                <input type="text" id="cardexpiredatemonth" name="cardexpiredatemonth" inputmode="numeric" 
                       autocomplete="cc-exp-month" pattern="(0[1-9]|1[0-2])" maxlength="2" placeholder="MM" required />
                <small class="hint">01-12 arası</small>
            </div>
            <div class="field">
                <label for="cardexpiredateyear">Son Kullanma (Yıl)</label>
                <input type="text" id="cardexpiredateyear" name="cardexpiredateyear" inputmode="numeric" 
                       autocomplete="cc-exp-year" pattern="[0-9]{2}" maxlength="2" placeholder="YY" required />
                <small class="hint">Örn: 26, 27, 28</small>
            </div>
        </div>

        <div class="field">
            <label for="cardcvv2">CVV</label>
            <input type="password" id="cardcvv2" name="cardcvv2" inputmode="numeric" autocomplete="cc-csc" 
                   pattern="[0-9]{3,4}" maxlength="4" placeholder="***" required />
            <small class="hint">Kartın arkasındaki 3-4 haneli kod</small>
        </div>

        <button type="submit" class="submit">Ödemeye Devam Et</button>
        <div class="note">3D Secure doğrulamasına yönlendirileceksiniz.</div>
    </form>
</div>
<script>
    // Auto-format card number with spaces
    document.getElementById('cardnumber').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        e.target.value = value;
    });

    // Validate and pad month with leading zero
    document.getElementById('cardexpiredatemonth').addEventListener('blur', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length === 1 && parseInt(value) >= 1 && parseInt(value) <= 9) {
            e.target.value = '0' + value;
        } else if (value.length === 2) {
            let month = parseInt(value);
            if (month < 1) e.target.value = '01';
            else if (month > 12) e.target.value = '12';
            else e.target.value = value;
        }
    });

    // Validate year (only allow 2 digits)
    document.getElementById('cardexpiredateyear').addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 2);
    });

    // Validate CVV (only allow 3-4 digits)
    document.getElementById('cardcvv2').addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    });

    // Form validation before submit
    document.querySelector('form').addEventListener('submit', function(e) {
        const month = document.getElementById('cardexpiredatemonth').value;
        const year = document.getElementById('cardexpiredateyear').value;
        const cardNumber = document.getElementById('cardnumber').value;
        const cvv = document.getElementById('cardcvv2').value;

        // Validate card number length
        if (cardNumber.length < 13 || cardNumber.length > 19) {
            e.preventDefault();
            alert('Lütfen geçerli bir kart numarası giriniz (13-19 hane)');
            return false;
        }

        // Validate month
        if (!/^(0[1-9]|1[0-2])$/.test(month)) {
            e.preventDefault();
            alert('Lütfen geçerli bir ay giriniz (01-12)');
            return false;
        }

        // Validate year
        if (!/^[0-9]{2}$/.test(year)) {
            e.preventDefault();
            alert('Lütfen geçerli bir yıl giriniz (örn: 26)');
            return false;
        }

        // Validate CVV
        if (cvv.length < 3 || cvv.length > 4) {
            e.preventDefault();
            alert('Lütfen geçerli bir CVV giriniz (3-4 hane)');
            return false;
        }

        return true;
    });
</script>
</body>
</html>
