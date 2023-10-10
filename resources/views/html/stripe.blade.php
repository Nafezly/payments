<script src="https://js.stripe.com/v3/"></script>
<style>
* {
  box-sizing: border-box;
}
.hidden {
  display: none;
}
#payment-message {
  color: rgb(105, 115, 134);
  font-size: 16px;
  line-height: 20px;
  padding-top: 12px;
  text-align: center;
}

#payment-element {
  margin-bottom: 24px;
}
 
#submit-button-stripe {
  background: #5469d4;
  font-family: Arial, sans-serif;
  color: #ffffff;
  border-radius: 4px;
  border: 0;
  padding: 12px 16px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  display: block;
  transition: all 0.2s ease;
  box-shadow: 0px 4px 5.5px 0px rgba(0, 0, 0, 0.07);
  width: 100%;
}
#submit-button-stripe:hover {
  filter: contrast(115%);
}
#submit-button-stripe:disabled {
  opacity: 0.5;
  cursor: default;
}
 
#submit-button-stripe  .spinner,
#submit-button-stripe  .spinner:before,
#submit-button-stripe  .spinner:after {
  border-radius: 50%;
}
#submit-button-stripe  .spinner {
  color: #ffffff;
  font-size: 22px;
  text-indent: -99999px;
  margin: 0px auto;
  position: relative;
  width: 20px;
  height: 20px;
  box-shadow: inset 0 0 0 2px;
  -webkit-transform: translateZ(0);
  -ms-transform: translateZ(0);
  transform: translateZ(0);
}
#submit-button-stripe  .spinner:before,
#submit-button-stripe  .spinner:after {
  position: absolute;
  content: "";
}
#submit-button-stripe  .spinner:before {
  width: 10.4px;
  height: 20.4px;
  background: #5469d4;
  border-radius: 20.4px 0 0 20.4px;
  top: -0.2px;
  left: -0.2px;
  -webkit-transform-origin: 10.4px 10.2px;
  transform-origin: 10.4px 10.2px;
  -webkit-animation: loading 2s infinite ease 1.5s;
  animation: loading 2s infinite ease 1.5s;
}
#submit-button-stripe  .spinner:after {
  width: 10.4px;
  height: 10.2px;
  background: #5469d4;
  border-radius: 0 10.2px 10.2px 0;
  top: -0.1px;
  left: 10.2px;
  -webkit-transform-origin: 0px 10.2px;
  transform-origin: 0px 10.2px;
  -webkit-animation: loading 2s infinite ease;
  animation: loading 2s infinite ease;
}
*{
  direction: rtl;
}
@-webkit-keyframes loading {
  0% {
    -webkit-transform: rotate(0deg);
    transform: rotate(0deg);
  }
  100% {
    -webkit-transform: rotate(360deg);
    transform: rotate(360deg);
  }
}
@keyframes loading {
  0% {
    -webkit-transform: rotate(0deg);
    transform: rotate(0deg);
  }
  100% {
    -webkit-transform: rotate(360deg);
    transform: rotate(360deg);
  }
}
</style>
<div class="stripe-container">
    <form id="payment-form">
        <div id="payment-element">
        </div>
        <div id="btn-stripe-container">
            <button id="submit-button-stripe" style="text-align:center"><div class="spinner" id="spinner"></div><span id="button-text">شحن {{$data['amount']}}$</span></button>
        </div>
        <div id="payment-message" class="hidden"></div>
    </form>
</div>
<script id="stripe-appended-script">
(function(){
        var element = document.getElementById("button-text");
        element.classList.add("hidden");

        

        const stripe = Stripe("{{$data['public_key']}}");
        let elements;
        const appearance = {
            clientSecret: '{{$data['client_secret']}}',
            locale: 'ar',
            theme: 'night',
            variables: { colorPrimaryText: '#2196f3' }

        };
        const options = {
            layout: {
                type: 'tabs',
                defaultCollapsed: false,
            },
            fields: {
                billingDetails: {
                    address: {
                        country: 'never'
                    }
                }
            }
        };
        initialize();
        checkStatus();
        document.querySelector("#payment-form").addEventListener("submit", handleSubmit);
        async function initialize() {
            elements = stripe.elements(appearance);
            const paymentElement = elements.create("payment", options);
            paymentElement.mount("#payment-element");
        }
        async function handleSubmit(e) {
            e.preventDefault();
            setLoading(true);

            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: "{{$data['return_url']}}",
                    payment_method_data: {
                        billing_details: {
                            address: {
                                country: null
                            }
                        }
                    }
                },
            });
            if (error.type === "card_error" || error.type === "validation_error") {
                showMessage(error.message);
            } else {
                showMessage("خطأ غير متوقع.");
            }
            setLoading(false);
        }

        async function checkStatus() {
            const clientSecret = new URLSearchParams(window.location.search).get(
                "payment_intent_client_secret"
            );
            if (!clientSecret) {
                return;
            }
            const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);
            switch (paymentIntent.status) {
                case "succeeded":
                    showMessage("تم الدفع بنجاح!");
                    break;
                case "processing":
                    showMessage("جار تنفيذ العملية.");
                    break;
                case "requires_payment_method":
                    showMessage("لم تتم عملية الدفع بنجاح، برجاء المحاولة مرة أخرى.");
                    break;
                default:
                    showMessage("حدث خطأ ما.");
                    break;
            }
        }

        function showMessage(messageText) {
            const messageContainer = document.querySelector("#payment-message");
            messageContainer.classList.remove("hidden");
            messageContainer.textContent = messageText;

            setTimeout(function() {
                messageContainer.classList.add("hidden");
                messageText.textContent = "";
            }, 4000);
        }

        function setLoading(isLoading) {
            if (isLoading) {
                document.querySelector("#submit-button-stripe").disabled = true;
                document.querySelector("#spinner").classList.remove("hidden");
                document.querySelector("#button-text").classList.add("hidden");
            } else {
                document.querySelector("#submit-button-stripe").disabled = false;
                document.querySelector("#spinner").classList.add("hidden");
                document.querySelector("#button-text").classList.remove("hidden");
            }
        }

        setTimeout(function(){
            document.querySelector("#submit-button-stripe").disabled = false;
            var element = document.getElementById("spinner");
            element.classList.add("hidden");
            var element = document.getElementById("button-text");
            element.classList.remove("hidden");
        },1500);

})();
</script>
