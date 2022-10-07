<form action="{{ route($model->verify_route_name, ['payment' => 'hyperpay']) }}" class='paymentWidgets' data-brands="{{ $brand }}"></form>
<script src="{{ $model->hyperpay_base_url }}/v1/paymentWidgets.js?checkoutId={{ $model->payment_id }}"></script>
<script type='text/javascript'>
const subTotalAmount = parseFloat({{ $model->amount }});
const shippingAmount = 0;
const taxAmount = 0;
const currency = "{{ $model->currency }}";
const applePayTotalLabel = "{{ $model->app_name }}";

function getAmount() {
    return ((subTotalAmount + shippingAmount + taxAmount)).toFixed(2);
}
function getLineItems() {
    return [{
        label: 'Subtotal',
        amount: (subTotalAmount).toFixed(2)
    }, {
        label: 'Shipping',
        amount: (shippingAmount).toFixed(2)
    }, {
        label: 'Tax',
        amount: (taxAmount).toFixed(2)
    }];
}

const wpwlOptions = {
    applePay: {
        displayName: "{{ $model->app_name }}",
        total: { 
            label: "{{ $model->app_name }}"
        },
        paymentTarget:'_top', 
        merchantCapabilities: ['supports3DS'],
        supportedNetworks: ['mada','masterCard', 'visa' ],
        supportedCountries: ['SA'],   
    }
};
wpwlOptions.createCheckout = function() {
    return $.post("{{ route($model->verify_route_name, ['payment' => 'hyperpay']) }}")
    .then(function(response) {
        return response.checkoutId;
    });
};
</script>