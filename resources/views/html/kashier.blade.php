<body>
<script id="kashier-iFrame"
src="{{ $model->kashier_url }}/kashier-checkout.js"
data-amount="{{ $model->amount }}"
data-description="Credit"
data-mode="{{ $model->kashier_mode }}"
data-hash="{{ $data['hash'] }}"
data-currency="{{ $data['currency'] }}"
data-orderId="{{ $data['order_id'] }}"
data-allowedMethods="{{ $data['source']==null?'card':$data['source'] }}"
data-merchantId="{{ $data['mid'] }}"
data-merchantRedirect="{{ $data['redirect_back'] }}" 
data-store="{{ $model->app_name }}"
data-type="external" data-display="ar">
</script>
</body>