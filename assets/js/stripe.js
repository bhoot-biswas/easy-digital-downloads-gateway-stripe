(function($) {

	"use strict";

	class EDD_Gateway_Stripe {
		constructor() {
			try {
				this.stripe = Stripe('pk_test_ZWxxc0uVCxEduPZDVH6TCkhV00YNdRuEqo');
			} catch (error) {
				console.log(error);
				return;
			}

			this.elements = this.stripe.elements();

			this.init();
		}

		init() {
			const $body = $(document.body);
            const $this = this;
			$body.on('edd_gateway_loaded', function() {
				$this.createElements();
			});
		}

		createElements() {
            if ( ! $( '#stripe-card-element' ).length ) {
				return;
			}
            
			const elementStyles = {
				base: {
					iconColor: '#666EE8',
					color: '#31325F',
					fontSize: '15px',
					'::placeholder': {
						color: '#CFD7E0',
					}
				}
			};

			const elementClasses = {
				focus: 'focused',
				empty: 'empty',
				invalid: 'invalid',
			};

            if ( typeof this.stripe_card === 'undefined' ) {
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

		mountElements() {
			this.stripe_card.mount( '#stripe-card-element' );
			this.stripe_exp.mount( '#stripe-exp-element' );
			this.stripe_cvc.mount( '#stripe-cvc-element' );
		}
	}

	$(document).ready(function() {
		new EDD_Gateway_Stripe();
	});

})(jQuery);
