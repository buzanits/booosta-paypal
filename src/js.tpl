  paypal.Buttons({
    createOrder: function(data, actions) {
      return actions.order.create({
        purchase_units: [{
          amount: {
            value: '{amount}'
          }
        }]
      });
    },

    onApprove: function(data, actions) {
      return actions.order.capture().then(function(details) {
        {success-js}
        return fetch('{url}', {
          method: 'post',
          headers: {
            'content-type': 'application/json'
          },
          body: JSON.stringify({
            orderID: data.orderID{extradata}
          })
        });
      });
    }
  }).render('#paypal-button-container-{id}');
