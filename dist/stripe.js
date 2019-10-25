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
        this.stripe = Stripe('pk_test_ZWxxc0uVCxEduPZDVH6TCkhV00YNdRuEqo');
      } catch (error) {
        console.log(error);
        return;
      }

      this.elements = this.stripe.elements();
      this.init();
    }

    _createClass(EDD_Gateway_Stripe, [{
      key: "init",
      value: function init() {
        var $body = $(document.body);
        var $this = this;
        $body.on('edd_gateway_loaded', function () {
          $this.createElements();
        });
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

        if (typeof this.stripe_card === 'undefined') {
          this.stripe_card = this.elements.create('cardNumber', {
            style: elementStyles,
            classes: elementClasses
          });
          this.stripe_exp = this.elements.create('cardExpiry', {
            style: elementStyles,
            classes: elementClasses
          });
          this.stripe_cvc = this.elements.create('cardCvc', {
            style: elementStyles,
            classes: elementClasses
          });
        }

        this.mountElements();
      }
    }, {
      key: "mountElements",
      value: function mountElements() {
        this.stripe_card.mount('#stripe-card-element');
        this.stripe_exp.mount('#stripe-exp-element');
        this.stripe_cvc.mount('#stripe-cvc-element');
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