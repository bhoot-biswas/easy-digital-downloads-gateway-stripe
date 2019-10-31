(function($) {

	"use strict";

	class EDD_Gateway_Stripe {
		constructor() {
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

			const self = this;

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
				this.stripeCard.addEventListener('change', function(event) {
					self.onCCFormChange();
					self.updateCardBrand(event.brand);
					if (event.error) {
						$(document.body).trigger('stripeError', event);
					}
				});
				this.stripeExp.addEventListener('change', function(event) {
					self.onCCFormChange();
					if (event.error) {
						$(document.body).trigger('stripeError', event);
					}
				});
				this.stripeCvc.addEventListener('change', function(event) {
					self.onCCFormChange();
					if (event.error) {
						$(document.body).trigger('stripeError', event);
					}
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
				return true;
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

			this.form.submit();
		}

		getBillingDetails() {
			return {};
		}

		/**
		 * If a new credit card is entered, reset sources.
		 */
		onCCFormChange() {
			this.reset();
		}
		/**
		 * Updates the card brand logo with non-inline CC forms.
		 *
		 * @param {string} brand The identifier of the chosen brand.
		 */
		updateCardBrand(brand) {
			const brandClass = {
				'visa': 'stripe-visa-brand',
				'mastercard': 'stripe-mastercard-brand',
				'amex': 'stripe-amex-brand',
				'discover': 'stripe-discover-brand',
				'diners': 'stripe-diners-brand',
				'jcb': 'stripe-jcb-brand',
				'unknown': 'stripe-credit-card-brand'
			};

			let imageElement = $(document.getElementsByClassName('stripe-card-brand'));
			let imageClass = 'stripe-credit-card-brand';

			if (brand in brandClass) {
				imageClass = brandClass[brand];
			}

			// Remove existing card brand class.
			$.each(brandClass, function(index, el) {
				imageElement.removeClass(el);
			});

			imageElement.addClass(imageClass);
		}

		/**
		 * Removes all Stripe errors and hidden fields with IDs from the form.
		 */
		reset() {
			$('.edd-loading-ajax').remove();
			$('.edd_errors, .stripe-source').remove();
			$('.edd-error').hide();
		}
	}

	$(document).ready(function() {
		new EDD_Gateway_Stripe();
	});

})(jQuery);
