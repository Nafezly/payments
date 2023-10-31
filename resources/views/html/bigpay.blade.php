 <script src="https://{{$data['bigpay_mode']=='live'?'live':'integration'}}.big-pay.com/scripts/redirect-bigpay.min.js"></script>
 <script> 
  function checkout(){ 
    var transaction = {
        order_number        : '{{$data['order_number']}}',
        product_description : '{{$data['product_description']}}',
        amount              : '{{$data['amount']}}',
        complete_url        : '{{$data['verify_route_name']}}',         
        cancel_url          : '{{$data['verify_route_name']}}',  
        timeout_url         : '{{$data['verify_route_name']}}',   
        failure_callback_url: '{{$data['verify_route_name']}}',
        success_callback_url: '{{$data['verify_route_name']}}', 
        website_id          : '{{$data['bigpay_key']}}',
        authorization       : '{{$data['authorization']}}'
    };
  }
  var transaction = {
        order_number        : '{{$data['order_number']}}',
        product_description : '{{$data['product_description']}}',
        amount              : '{{$data['amount']}}',
        complete_url        : '{{$data['verify_route_name']}}',         
        cancel_url          : '{{$data['verify_route_name']}}',  
        timeout_url         : '{{$data['verify_route_name']}}',   
        failure_callback_url: '{{$data['verify_route_name']}}',
        success_callback_url: '{{$data['verify_route_name']}}', 
        website_id          : '{{$data['bigpay_key']}}',
        authorization       : '{{$data['authorization']}}'
    };

  setTimeout(function(){
    redirectToCheckout(transaction);
    checkout();
  },3000);
</script>