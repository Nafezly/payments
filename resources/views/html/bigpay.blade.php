<script src="https://{{$data['bigpay_mode']=='live'?'':'test-'}}bobsal.gateway.mastercard.com/static/checkout/checkout.min.js"></script>
 <script> 
  setTimeout(function(){
    Checkout.configure({session: {id: '{{$data['session_id']}}' }});
    Checkout.showPaymentPage();
  },3000);
</script>
