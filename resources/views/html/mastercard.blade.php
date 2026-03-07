<script src="{{$data['checkout_js_url']}}"></script>
<script>
    Checkout.configure({
        session: {
            id: '{{$data['session_id']}}'
        }
    });
    Checkout.showPaymentPage();
</script>
