 <div id="alerts" class="ms-text-center" style="padding: 6px;text-align: center;"></div>
  <div id="loading" class="spinner-container ms-div-center">
    <div class="spinner"></div>
  </div>
  <div id="content" class="hide">
    <div class="ms-card ms-fill">
      <div class="ms-card-content">
      </div>
    </div>
    <div id="payment_options"></div>
  </div>
</div>
<script>
	// Helper / Utility functions
var url_to_head = (url) => {
    return new Promise(function(resolve, reject) {
        var script = document.createElement('script');
        script.src = url;
        script.onload = function() {
            resolve();
        };
        script.onerror = function() {
            reject('Error loading script.');
        };
        document.head.appendChild(script);
    });
}
var handle_close = (event) => {
    event.target.closest(".ms-alert").remove();
}
var handle_click = (event) => {
    if (event.target.classList.contains("ms-close")) {
        handle_close(event);
    }
}


function emulateFetch(intent) {
    var localResponse = JSON.parse(`{!!json_encode($data['response'])!!}`);

    return new Promise((resolve, reject) => {
        setTimeout(() => {
            resolve({
                json: () => Promise.resolve(localResponse),
            });        }, 100);
    })
    .then(response => response.json());
}


document.addEventListener("click", handle_click);
var paypal_sdk_url = "https://www.paypal.com/sdk/js";
var client_id = "{{$data['paypal_client_id']}}";
var currency = "USD";
var intent = "capture";
var alerts = document.getElementById("alerts");

//PayPal Code
//https://developer.paypal.com/sdk/js/configuration/#link-queryparameters
url_to_head(paypal_sdk_url + "?client-id=" + client_id + "&enable-funding=venmo&currency=" + currency + "&intent=" + intent+"&locale=ar_EG")
.then(() => {
    //Handle loading spinner
    document.getElementById("loading").classList.add("hide");
    document.getElementById("content").classList.remove("hide");
    var alerts = document.getElementById("alerts");
    var paypal_buttons = paypal.Buttons({
        onClick: (data) => { 
        },
        style: { //https://developer.paypal.com/sdk/js/reference/#link-style
            shape: 'rect',
            color: 'gold',
            layout: 'vertical',
            label: 'paypal'
        },

        createOrder: function(data, actions) { 

        	return emulateFetch(intent)
		    /*.then((response) => response.json())*/
		    .then((order) => { return order.id; })
		    .catch(error => { 
		        console.error(error);
		    }); 



        //https://developer.paypal.com/docs/api/orders/v2/#orders_create
            /*return fetch("http://localhost:3000/create_order", {
                method: "post", headers: { "Content-Type": "application/json; charset=utf-8" },
                body: JSON.stringify({ "intent": intent })
            })
            .then((response) => response.json())
            .then((order) => { return order.id; });*/
        },

        onApprove: function(data, actions) {
            var order_id = data.orderID;
            return fetch("{{$data['return_url']}}", {
                method: "post", headers: { "Content-Type": "application/json; charset=utf-8" },
                body: JSON.stringify({
                    "intent": intent,
                    "order_id": order_id,
                    "check":1
                })
            })
            .then((response) => response.json())
            .then((order_details) => {
                //console.log(order_details.process_data); //https://developer.paypal.com/docs/api/orders/v2/#orders_capture!c=201&path=create_time&t=response
                var intent_object = intent === "authorize" ? "authorizations" : "captures";
                //Custom Successful Message
                window.location.href = "{{$data['return_url']}}"+"?order_id="+order_details.process_data.id
                alerts.innerHTML = `<div class=\'ms-alert ms-action\'>Thank you ` + order_details.process_data.payer.name.given_name + ` ` + order_details.process_data.payer.name.surname + ` for your payment of ` + order_details.process_data.purchase_units[0].payments[intent_object][0].amount.value + ` ` + order_details.process_data.purchase_units[0].payments[intent_object][0].amount.currency_code + `!</div>`;

                paypal_buttons.close();
             })
             .catch((error) => {
                console.log(error);
                alerts.innerHTML = `<div class="ms-alert ms-action2 ms-small"><span class="ms-close"></span><p>An Error Ocurred!</p>  </div>`;
             });
        },

        onCancel: function (data) {
            alerts.innerHTML = `<div class="ms-alert ms-action2 ms-small"><span class="ms-close"></span><p>Order cancelled!</p>  </div>`;
        },

        onError: function(err) {
            console.log(err);
        }
    });
    paypal_buttons.render('#payment_options');
})
.catch((error) => {
    console.error(error);
});
</script>