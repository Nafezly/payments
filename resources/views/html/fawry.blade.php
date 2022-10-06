<link rel='stylesheet' href="https://atfawry.fawrystaging.com/atfawry/plugin/assets/payments/css/fawrypay-payments.css">
<script type='text/javascript' src="{{ $data['fawry_url'] }}atfawry/plugin/assets/payments/js/fawrypay-payments.js"></script>
<script>  
    const chargeRequest = {};
    chargeRequest.language= "ar-eg";
    chargeRequest.merchantCode= "{{ $data['fawry_merchant'] }}";
    chargeRequest.merchantRefNumber= "{{ $data['payment_id'] }}";
    chargeRequest.customer = {};
    chargeRequest.customer.name = "{{ $data['user_name'] }}";
    chargeRequest.customer.mobile = "{{ $data['user_phone'] }}";
    chargeRequest.customer.email = "{{ $data['user_email'] }}";
    chargeRequest.customer.customerProfileId = "{{ $data['user_id'] }}";
    chargeRequest.order = {};
    chargeRequest.order.description = "Credit";
    chargeRequest.order.expiry = '';
    chargeRequest.order.orderItems = [];
    const item = {};
    item.productSKU = 1;
    item.description = 'Credit';
    item.price = "{{ $data['amount'] }}";
    item.quantity = "{{ $data['item_quantity'] }}";
    chargeRequest.order.orderItems.push(item); 
    chargeRequest.signature = "{{ $data['secret'] }}";
    setTimeout(function(){
        FawryPay.checkout(chargeRequest,'{{ route($this->verify_route_name, ["payment" => "fawry"]) }}', '{{ route($this->verify_route_name, ["payment" => "fawry"]) }}');
    },100); 
</script>