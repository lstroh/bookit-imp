/**
 * Booking wizard navigation controller.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/assets/js
 */

(function($) {
	'use strict';

	/**
	 * Booking wizard object.
	 */
	const BookitWizard = {
		currentStep: 1,
		maxSteps: 4,
		ajaxUrl: '',
		nonce: '',

		/**
		 * Initialize wizard.
		 */
		init: function() {
			// Get localized data.
			if (typeof bookitWizard !== 'undefined') {
				this.ajaxUrl = bookitWizard.ajaxUrl;
				this.nonce = bookitWizard.nonce;
				this.currentStep = parseInt(bookitWizard.currentStep, 10) || 1;
			}

			// Check URL hash for step number.
			const hashMatch = window.location.hash.match(/^#step-(\d+)$/);
			if (hashMatch) {
				const hashStep = parseInt(hashMatch[1], 10);
				if (hashStep >= 1 && hashStep <= this.maxSteps && hashStep !== this.currentStep) {
					// Hash step differs from session step - sync session to hash.
					this.goToStep(hashStep, false);
					return;
				}
			}

			// Bind events.
			this.bindEvents();

			// Initialize URL hash.
			this.updateUrlHash();

			// Handle browser back/forward buttons.
			$(window).on('popstate', this.handlePopState.bind(this));

			// Check session timeout.
			this.checkSessionTimeout();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Back button.
			$('#bookit-back-btn').on('click', this.handleBack.bind(this));

			// Next button.
			$('#bookit-next-btn').on('click', this.handleNext.bind(this));

			// Keyboard navigation.
			$(document).on('keydown', this.handleKeyboard.bind(this));
		},

		/**
		 * Handle back button click.
		 */
		handleBack: function(e) {
			e.preventDefault();
			if (this.currentStep > 1) {
				this.goToStep(this.currentStep - 1);
			}
		},

		/**
		 * Handle next button click.
		 */
		handleNext: function(e) {
			e.preventDefault();
			if (this.validateCurrentStep()) {
				if (this.currentStep < this.maxSteps) {
					this.goToStep(this.currentStep + 1);
				} else {
					// Step 4 - submit booking (will be implemented in later tasks).
					this.submitBooking();
				}
			}
		},

		/**
		 * Handle keyboard navigation.
		 */
		handleKeyboard: function(e) {
			// Only handle if not in an input field.
			if ($(e.target).is('input, textarea, select')) {
				return;
			}

			// Left arrow or Backspace = Back.
			if ((e.key === 'ArrowLeft' || e.key === 'Backspace') && this.currentStep > 1) {
				e.preventDefault();
				this.handleBack(e);
			}

			// Right arrow or Enter = Next.
			if ((e.key === 'ArrowRight' || e.key === 'Enter') && this.currentStep < this.maxSteps) {
				e.preventDefault();
				this.handleNext(e);
			}
		},

		/**
		 * Handle browser back/forward buttons.
		 */
		handlePopState: function(e) {
			if (e.originalEvent.state && e.originalEvent.state.step) {
				this.goToStep(e.originalEvent.state.step, false);
			}
		},

		/**
		 * Navigate to specific step.
		 *
		 * @param {number} step Step number.
		 * @param {boolean} updateHistory Whether to update browser history.
		 */
		goToStep: function(step, updateHistory = true) {
			if (step < 1 || step > this.maxSteps) {
				return;
			}

			// Save current step data.
			this.saveStepData(step).then(() => {
				this.currentStep = step;
				this.updateProgressIndicator();
				this.updateNavigation();

				// Set URL hash before reload so it persists.
				window.location.hash = '#step-' + step;

				if (updateHistory) {
					window.history.pushState({ step: step }, '', '#step-' + step);
				}

				// Reload page to show new step template.
				window.location.reload();
			}).catch((error) => {
				console.error('Error saving step data:', error);
				alert('An error occurred. Please try again.');
			});
		},

		/**
		 * Validate current step before proceeding.
		 *
		 * @return {boolean} True if valid.
		 */
		validateCurrentStep: function() {
			// Step 1: Validate service selection.
			if (this.currentStep === 1) {
				// Check if a service has been selected (via session data).
				// For now, we'll allow navigation if service selection button was clicked.
				// The actual validation happens server-side when saving.
				return true;
			}
			
			// Other steps will have their own validation (implemented in later tasks).
			return true;
		},

		/**
		 * Initialize service selection handlers.
		 */
		initServiceSelection: function() {
			const self = this;
			const selectButtons = document.querySelectorAll('.bookit-btn-select-service');
			
			selectButtons.forEach(function(button) {
				button.addEventListener('click', function(e) {
					e.preventDefault();
					
					const serviceId = this.dataset.serviceId;
					const serviceName = this.dataset.serviceName;
					const serviceDuration = this.dataset.serviceDuration;
					const servicePrice = this.dataset.servicePrice;
					
					// Disable button during request.
					this.disabled = true;
					const originalText = this.textContent;
					this.textContent = 'Selecting...';
					
					// Get REST URL and nonce from localized script.
					const restUrl = (typeof bookitWizard !== 'undefined' && bookitWizard.restUrl) 
						? bookitWizard.restUrl 
						: '/wp-json/';
					const nonce = (typeof bookitWizard !== 'undefined' && bookitWizard.nonce) 
						? bookitWizard.nonce 
						: '';
					
					// Send AJAX request.
					fetch(restUrl + 'bookit/v1/service/select', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce
						},
						body: JSON.stringify({
							service_id: serviceId
						})
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						if (data.success) {
							// Navigate to step 2 (goToStep will update progress indicator).
							self.goToStep(2);
						} else {
							alert('Error selecting service. Please try again.');
							button.disabled = false;
							button.textContent = originalText;
						}
					})
					.catch(function(error) {
						console.error('Service selection error:', error);
						alert('Error selecting service. Please try again.');
						button.disabled = false;
						button.textContent = originalText;
					});
				});
			});
		},

		/**
		 * Update progress indicator visual state.
		 */
		updateProgressIndicator: function() {
			$('.bookit-progress-step').each(function(index) {
				const step = index + 1;
				const $step = $(this);
				$step.removeClass('bookit-progress-step-completed bookit-progress-step-current bookit-progress-step-upcoming');

				if (step < BookitWizard.currentStep) {
					$step.addClass('bookit-progress-step-completed');
				} else if (step === BookitWizard.currentStep) {
					$step.addClass('bookit-progress-step-current');
				} else {
					$step.addClass('bookit-progress-step-upcoming');
				}
			});
		},

		/**
		 * Update navigation buttons visibility.
		 */
		updateNavigation: function() {
			const $backBtn = $('#bookit-back-btn');
			if (this.currentStep > 1) {
				$backBtn.show();
			} else {
				$backBtn.hide();
			}

			const $nextBtn = $('#bookit-next-btn');
			if (this.currentStep === this.maxSteps) {
				$nextBtn.text('Submit Booking');
			} else {
				$nextBtn.text('Next →');
			}
		},

		/**
		 * Update URL hash.
		 */
		updateUrlHash: function() {
			if (window.location.hash !== '#step-' + this.currentStep) {
				window.history.replaceState({ step: this.currentStep }, '', '#step-' + this.currentStep);
			}
		},

		/**
		 * Save step data to session via AJAX.
		 *
		 * @param {number} step Step number.
		 * @return {Promise} AJAX promise.
		 */
		saveStepData: function(step) {
			return $.ajax({
				url: this.ajaxUrl,
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', BookitWizard.nonce);
				},
				data: {
					current_step: step
				},
				dataType: 'json'
			});
		},

		/**
		 * Get session data.
		 *
		 * @return {Promise} AJAX promise.
		 */
		getSessionData: function() {
			return $.ajax({
				url: this.ajaxUrl,
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', BookitWizard.nonce);
				},
				dataType: 'json'
			});
		},

		/**
		 * Check session timeout and warn user.
		 */
		checkSessionTimeout: function() {
			// This will be enhanced in later tasks with actual timeout data from API.
			// For now, just a placeholder.
			setInterval(() => {
				// Check every minute.
				this.getSessionData().then((response) => {
					if (response.success && response.data && response.data.time_remaining) {
						const timeRemaining = response.data.time_remaining;
						// Warn 2 minutes before expiry (WCAG 2.1 requirement).
						if (timeRemaining > 0 && timeRemaining <= 120) {
							this.showTimeoutWarning(timeRemaining);
						}
					}
				}).catch(() => {
					// Silently fail - session check is not critical.
				});
			}, 60000); // Check every minute.
		},

		/**
		 * Show session timeout warning.
		 *
		 * @param {number} secondsRemaining Seconds until session expires.
		 */
		showTimeoutWarning: function(secondsRemaining) {
			const minutes = Math.ceil(secondsRemaining / 60);
			const message = 'Your session will expire in ' + minutes + ' minute(s). Please complete your booking soon.';
			
			// Create or update warning message.
			let $warning = $('.bookit-timeout-warning');
			if ($warning.length === 0) {
				$warning = $('<div class="bookit-timeout-warning" role="alert" aria-live="polite"></div>');
				$('.bookit-wizard-container').prepend($warning);
			}
			$warning.text(message).show();
		},

		/**
		 * Submit booking (stub for now).
		 */
		submitBooking: function() {
			// Will be implemented in Sprint 2.
			alert('Booking submission will be implemented in Sprint 2.');
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BookitWizard.init();
		
		// Initialize service selection if on step 1.
		if (BookitWizard.currentStep === 1) {
			BookitWizard.initServiceSelection();
		}
	});

})(jQuery);
