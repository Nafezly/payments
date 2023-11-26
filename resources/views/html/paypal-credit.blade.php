<div id="paypal-credit-frame">
    <div id="loading" class="spinner-container ms-div-center">
        <div class="spinner"></div>
    </div>
    <div id="content" class="hide">
        <div class="ms-card ms-fill">
          <div class="ms-card-content">
          </div>
        </div>
        <div id="payment_options"></div>
        <div id="alerts" class="ms-text-center" style="padding: 6px;text-align: center;"></div>
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
            alerts.innerHTML="";
        },
        style: { //https://developer.paypal.com/sdk/js/reference/#link-style
            shape: 'rect',
            color: 'gold',
            layout: 'vertical',
            label: 'paypal'
        },

        createOrder: function(data, actions) { 
            alerts.innerHTML="";
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
            alerts.innerHTML="";
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
                alerts.innerHTML="";
                //console.log(order_details.process_data); //https://developer.paypal.com/docs/api/orders/v2/#orders_capture!c=201&path=create_time&t=response
                var intent_object = intent === "authorize" ? "authorizations" : "captures";
                //Custom Successful Message
                window.location.href = "{{$data['return_url']}}"+"?order_id="+order_details.payment_id;

                alerts.innerHTML = `<div style='height:60px'></div><svg width="66px" height="66px" viewBox="-1.44 -1.44 26.88 26.88" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#000000" stroke-width="0.00024000000000000003" transform="rotate(0)"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.336"></g><g id="SVGRepo_iconCarrier"> <rect width="24" height="24" fill="white"></rect> <path fill-rule="evenodd" clip-rule="evenodd" d="M7.25007 2.38782C8.54878 2.0992 10.1243 2 12 2C13.8757 2 15.4512 2.0992 16.7499 2.38782C18.06 2.67897 19.1488 3.176 19.9864 4.01358C20.824 4.85116 21.321 5.94002 21.6122 7.25007C21.9008 8.54878 22 10.1243 22 12C22 13.8757 21.9008 15.4512 21.6122 16.7499C21.321 18.06 20.824 19.1488 19.9864 19.9864C19.1488 20.824 18.06 21.321 16.7499 21.6122C15.4512 21.9008 13.8757 22 12 22C10.1243 22 8.54878 21.9008 7.25007 21.6122C5.94002 21.321 4.85116 20.824 4.01358 19.9864C3.176 19.1488 2.67897 18.06 2.38782 16.7499C2.0992 15.4512 2 13.8757 2 12C2 10.1243 2.0992 8.54878 2.38782 7.25007C2.67897 5.94002 3.176 4.85116 4.01358 4.01358C4.85116 3.176 5.94002 2.67897 7.25007 2.38782ZM15.7071 9.29289C16.0976 9.68342 16.0976 10.3166 15.7071 10.7071L12.0243 14.3899C11.4586 14.9556 10.5414 14.9556 9.97568 14.3899L11 13.3656L9.97568 14.3899L8.29289 12.7071C7.90237 12.3166 7.90237 11.6834 8.29289 11.2929C8.68342 10.9024 9.31658 10.9024 9.70711 11.2929L11 12.5858L14.2929 9.29289C14.6834 8.90237 15.3166 8.90237 15.7071 9.29289Z" fill="#23b623"></path> </g></svg> <div class=\'ms-alert ms-action\'><h4 style='text-align:center;font-weight:bold'>عملية مقبولة</h4></div>`;
                setTimeout(function(){alerts.innerHTML=""},3000);

                /*alerts.innerHTML = `<div class=\'ms-alert ms-action\'>شكراً لك أستاذ ` + order_details.process_data.payer.name.given_name + ` ` + order_details.process_data.payer.name.surname + `، تفاصيل العملية ` + order_details.process_data.purchase_units[0].payments[intent_object][0].amount.value + ` ` + order_details.process_data.purchase_units[0].payments[intent_object][0].amount.currency_code + `!</div>`;*/

                paypal_buttons.close();
             })
             .catch((error) => {
                alerts.innerHTML = `<svg width="75px" height="75px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12 17V11" stroke="#0194fe" stroke-width="1.464" stroke-linecap="round"></path> <circle cx="1" cy="1" r="1" transform="matrix(1 0 0 -1 11 9)" fill="#0194fe"></circle> <path d="M22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C21.5093 4.43821 21.8356 5.80655 21.9449 8" stroke="#0194fe" stroke-width="1.464" stroke-linecap="round"></path> </g></svg> <div class=\'ms-alert ms-action\'><h4 style='text-align:center;font-weight:bold'>حدث خطأ أثناء التنفيذ</h4></div>`;
                setTimeout(function(){alerts.innerHTML=""},3000);
             });
        },

        onCancel: function (data) {
            alerts.innerHTML = `<svg width="75px" height="75px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12 17V11" stroke="#0194fe" stroke-width="1.464" stroke-linecap="round"></path> <circle cx="1" cy="1" r="1" transform="matrix(1 0 0 -1 11 9)" fill="#0194fe"></circle> <path d="M22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C21.5093 4.43821 21.8356 5.80655 21.9449 8" stroke="#0194fe" stroke-width="1.464" stroke-linecap="round"></path> </g></svg> <div class=\'ms-alert ms-action\'><h4 style='text-align:center;font-weight:bold'>لقد تم الغاء العملية</h4></div>`;
            setTimeout(function(){alerts.innerHTML=""},3000);
        },

        onError: function(err) {
            alerts.innerHTML = `<svg width="75px" height="75px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12 17V11" stroke="#0194fe" stroke-width="1.464" stroke-linecap="round"></path> <circle cx="1" cy="1" r="1" transform="matrix(1 0 0 -1 11 9)" fill="#0194fe"></circle> <path d="M22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C21.5093 4.43821 21.8356 5.80655 21.9449 8" stroke="#0194fe" stroke-width="1.464" stroke-linecap="round"></path> </g></svg> <div class=\'ms-alert ms-action\'><h4 style='text-align:center;font-weight:bold'>حدث خطأ أثناء التنفيذ</h4></div>`;
            setTimeout(function(){alerts.innerHTML=""},3000);
            console.log(err);
        }
    });
    paypal_buttons.render('#payment_options');
})
.catch((error) => {
    console.error(error);
});
</script>