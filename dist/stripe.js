(window["webpackJsonp"] = window["webpackJsonp"] || []).push([["/stripe"],{

/***/ "./assets/js/stripe.js":
/*!*****************************!*\
  !*** ./assets/js/stripe.js ***!
  \*****************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

(function ($) {
  "use strict";

  var EDD_Gateway_Stripe =
  /*#__PURE__*/
  function () {
    function EDD_Gateway_Stripe() {
      _classCallCheck(this, EDD_Gateway_Stripe);

      try {
        this.stripe = Stripe(edd_stripe_params.key);
      } catch (error) {
        console.log(error);
        return;
      }

      this.elements = this.stripe.elements();
      this.onSubmit = this.onSubmit.bind(this);
      this.createElements = this.createElements.bind(this);
      this.sourceResponse = this.sourceResponse.bind(this);
      this.init();
    }

    _createClass(EDD_Gateway_Stripe, [{
      key: "init",
      value: function init() {
        var body = $(document.body);
        var eddPurchaseform = $(document.getElementById('edd_purchase_form'));

        if (!$(eddPurchaseform).length) {
          return;
        }

        this.form = eddPurchaseform;
        this.form.on('submit', this.onSubmit);
        body.on('edd_gateway_loaded', this.createElements);
        $(document).ajaxComplete(function (event, xhr, settings) {
          console.log(xhr.responseText); // window.location = 'https://facebook.com';
        });
        this.maybeConfirmIntent();
      }
    }, {
      key: "createElements",
      value: function createElements() {
        if (!$('#stripe-card-element').length) {
          return;
        }

        var elementStyles = {
          base: {
            iconColor: '#666EE8',
            color: '#31325F',
            fontSize: '15px',
            '::placeholder': {
              color: '#CFD7E0'
            }
          }
        };
        var elementClasses = {
          focus: 'focused',
          empty: 'empty',
          invalid: 'invalid'
        };
        var self = this;

        if (typeof this.stripe_card === 'undefined') {
          this.stripeCard = this.elements.create('cardNumber', {
            style: elementStyles,
            classes: elementClasses
          });
          this.stripeExp = this.elements.create('cardExpiry', {
            style: elementStyles,
            classes: elementClasses
          });
          this.stripeCvc = this.elements.create('cardCvc', {
            style: elementStyles,
            classes: elementClasses
          });
          this.stripeCard.addEventListener('change', function (event) {
            self.onCCFormChange();
            self.updateCardBrand(event.brand);

            if (event.error) {
              $(document.body).trigger('stripeError', event);
            }
          });
          this.stripeExp.addEventListener('change', function (event) {
            self.onCCFormChange();

            if (event.error) {
              $(document.body).trigger('stripeError', event);
            }
          });
          this.stripeCvc.addEventListener('change', function (event) {
            self.onCCFormChange();

            if (event.error) {
              $(document.body).trigger('stripeError', event);
            }
          });
        }

        this.mountElements();
      }
    }, {
      key: "mountElements",
      value: function mountElements() {
        this.stripeCard.mount('#stripe-card-element');
        this.stripeExp.mount('#stripe-exp-element');
        this.stripeCvc.mount('#stripe-cvc-element');
      }
    }, {
      key: "maybeConfirmIntent",
      value: function maybeConfirmIntent() {
        if (!$('#stripe-intent-id').length || !$('#stripe-intent-return').length) {
          return;
        }

        var intentSecret = $('#stripe-intent-id').val();
        var returnURL = $('#stripe-intent-return').val();
        this.openIntentModal(intentSecret, returnURL, true, false);
      }
      /**
       * Opens the modal for PaymentIntent authorizations.
       *
       * @param {string}  intentClientSecret The client secret of the intent.
       * @param {string}  redirectURL        The URL to ping on fail or redirect to on success.
       * @param {boolean} alwaysRedirect     If set to true, an immediate redirect will happen no matter the result.
       *                                     If not, an error will be displayed on failure.
       * @param {boolean} isSetupIntent      If set to true, ameans that the flow is handling a Setup Intent.
       *                                     If false, it's a Payment Intent.
       */

    }, {
      key: "openIntentModal",
      value: function openIntentModal(intentClientSecret, redirectURL, alwaysRedirect, isSetupIntent) {
        this.stripe[isSetupIntent ? 'handleCardSetup' : 'handleCardPayment'](intentClientSecret).then(function (response) {
          if (response.error) {
            throw response.error;
          }

          var intent = response[isSetupIntent ? 'setupIntent' : 'paymentIntent'];

          if ('requires_capture' !== intent.status && 'succeeded' !== intent.status) {
            return;
          }

          window.location = redirectURL;
        })["catch"](function (error) {
          if (alwaysRedirect) {
            return window.location = redirectURL;
          }

          $(document.body).trigger('stripeError', {
            error: error
          }); // wc_stripe_form.form && wc_stripe_form.form.removeClass( 'processing' );
          // Report back to the server.

          $.get(redirectURL + '&is_ajax');
        });
      }
    }, {
      key: "onSubmit",
      value: function onSubmit() {
        if (!this.isStripeChosen()) {
          return true;
        } // If a source is already in place, submit the form as usual.


        if (this.hasSource()) {
          return true;
        }

        this.createSource();
        return false;
      }
    }, {
      key: "isStripeChosen",
      value: function isStripeChosen() {
        return $('#edd-gateway-stripe').is(':checked');
      }
      /**
       * Checks if a source ID is present as a hidden input.
       * Only used when SEPA Direct Debit is chosen.
       *
       * @return {boolean}
       */

    }, {
      key: "hasSource",
      value: function hasSource() {
        return 0 < $('input.stripe-source').length;
      }
      /**
       * Initiates the creation of a Source object.
       *
       * Currently this is only used for credit cards and SEPA Direct Debit,
       * all other payment methods work with redirects to create sources.
       */

    }, {
      key: "createSource",
      value: function createSource() {
        var billingDetails = this.getBillingDetails(); // Handle card payments.

        return this.stripe.createSource(this.stripeCard, billingDetails).then(this.sourceResponse);
      }
      /**
       * Handles responses, based on source object.
       *
       * @param {Object} response The `stripe.createSource` response.
       */

    }, {
      key: "sourceResponse",
      value: function sourceResponse(response) {
        if (response.error) {
          return $(document.body).trigger('stripeError', response);
        }

        this.reset();
        this.form.append($('<input type="hidden" />').addClass('stripe-source').attr('name', 'stripe_source').val(response.source.id));
        this.form.submit();
      }
    }, {
      key: "getBillingDetails",
      value: function getBillingDetails() {
        return {};
      }
      /**
       * If a new credit card is entered, reset sources.
       */

    }, {
      key: "onCCFormChange",
      value: function onCCFormChange() {
        this.reset();
      }
      /**
       * Updates the card brand logo with non-inline CC forms.
       *
       * @param {string} brand The identifier of the chosen brand.
       */

    }, {
      key: "updateCardBrand",
      value: function updateCardBrand(brand) {
        var brandClass = {
          'visa': 'stripe-visa-brand',
          'mastercard': 'stripe-mastercard-brand',
          'amex': 'stripe-amex-brand',
          'discover': 'stripe-discover-brand',
          'diners': 'stripe-diners-brand',
          'jcb': 'stripe-jcb-brand',
          'unknown': 'stripe-credit-card-brand'
        };
        var imageElement = $(document.getElementsByClassName('stripe-card-brand'));
        var imageClass = 'stripe-credit-card-brand';

        if (brand in brandClass) {
          imageClass = brandClass[brand];
        } // Remove existing card brand class.


        $.each(brandClass, function (index, el) {
          imageElement.removeClass(el);
        });
        imageElement.addClass(imageClass);
      }
      /**
       * Removes all Stripe errors and hidden fields with IDs from the form.
       */

    }, {
      key: "reset",
      value: function reset() {
        $('.edd-loading-ajax').remove();
        $('.edd_errors, .stripe-source').remove();
        $('.edd-error').hide();
      }
    }]);

    return EDD_Gateway_Stripe;
  }();

  $(document).ready(function () {
    new EDD_Gateway_Stripe();
  });
})(jQuery);

/***/ }),

/***/ 1:
/*!***********************************!*\
  !*** multi ./assets/js/stripe.js ***!
  \***********************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! C:\xampp\htdocs\wp\edd\wp-content\plugins\easy-digital-downloads-gateway-stripe\assets\js\stripe.js */"./assets/js/stripe.js");


/***/ })

},[[1,"/manifest"]]]);