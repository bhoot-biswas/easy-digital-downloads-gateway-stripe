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
			this.onSubmit = this.onSubmit.bind(this);
			this.createElements = this.createElements.bind(this);
			this.sourceResponse = this.sourceResponse.bind(this);

			this.init();
		}

		init() {
			const body = $(document.body);
			const eddPurchaseform = $(document.getElementById('edd_purchase_form'));
			if (!$(eddPurchaseform).length) {
				return;
			}

			this.form = eddPurchaseform;
			this.form.on('submit', this.onSubmit);
			body.on('edd_gateway_loaded', this.createElements);
		}

		createElements() {
			if (!$('#stripe-card-element').length) {
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
			}

			this.mountElements();
		}

		mountElements() {
			this.stripeCard.mount('#stripe-card-element');
			this.stripeExp.mount('#stripe-exp-element');
			this.stripeCvc.mount('#stripe-cvc-element');
		}

		onSubmit() {
			if (!this.isStripeChosen()) {
				return true;
			}

			// If a source is already in place, submit the form as usual.
			if (this.hasSource()) {
				// return true;
			}

			this.createSource();

			return false;
		}

		isStripeChosen() {
			return $('#edd-gateway-stripe').is(':checked');
		}

		/**
		 * Checks if a source ID is present as a hidden input.
		 * Only used when SEPA Direct Debit is chosen.
		 *
		 * @return {boolean}
		 */
		hasSource() {
			return 0 < $('input.stripe-source').length;
		}

		/**
		 * Initiates the creation of a Source object.
		 *
		 * Currently this is only used for credit cards and SEPA Direct Debit,
		 * all other payment methods work with redirects to create sources.
		 */
		createSource() {
			const billingDetails = this.getBillingDetails();

			// Handle card payments.
			return this.stripe.createSource(this.stripeCard, billingDetails)
				.then(this.sourceResponse);
		}

		/**
		 * Handles responses, based on source object.
		 *
		 * @param {Object} response The `stripe.createSource` response.
		 */
		sourceResponse(response) {
			if (response.error) {
				return $(document.body).trigger('stripeError', response);
			}

			this.reset();

			this.form.append($('<input type="hidden" />').addClass('stripe-source').attr('name', 'stripe_source')
				.val(response.source.id)
			)

			console.log(response.source.id);

			// this.form.submit();
		}

		getBillingDetails() {
			return {};
		}

		/**
		 * Removes all Stripe errors and hidden fields with IDs from the form.
		 */
		reset() {
			$('.edd_errors, .stripe-source').remove();
		}
	}

	$(document).ready(function() {
		new EDD_Gateway_Stripe();
	});

})(jQuery);
