/**
 * Contact Form Validation & Submission
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/assets/js
 */

(function() {
	'use strict';

	/**
	 * Contact form handler with real-time validation and UK phone/email checks.
	 */
	class BookitContactForm {
		constructor() {
			this.form = document.getElementById('bookit-contact-form');
			if (!this.form) return;

			this.restUrl = (typeof bookitWizard !== 'undefined' && bookitWizard.restUrl) ? bookitWizard.restUrl : '/wp-json/';
			this.nonce  = (typeof bookitWizard !== 'undefined' && bookitWizard.nonce) ? bookitWizard.nonce : '';

			this.init();
		}

		init() {
			this.attachFieldValidators();
			this.attachCharCounter();
			this.attachPhoneFormatter();
			this.form.addEventListener('submit', (e) => this.handleSubmit(e));
			this.attachBackButton();
		}

		attachFieldValidators() {
			const fields = {
				'first-name': this.validateFirstName.bind(this),
				'last-name': this.validateLastName.bind(this),
				'email': this.validateEmail.bind(this),
				'phone': this.validatePhone.bind(this)
			};

			Object.keys(fields).forEach(function(fieldId) {
				const field = document.getElementById(fieldId);
				if (field) {
					field.addEventListener('blur', function() {
						const error = fields[fieldId](field.value);
						this.showFieldError(fieldId, error);
					}.bind(this));

					field.addEventListener('input', function() {
						if (field.value.trim()) {
							this.clearFieldError(fieldId);
						}
					}.bind(this));
				}
			}, this);
		}

		validateFirstName(value) {
			if (!value || !value.trim()) {
				return 'Please enter your first name';
			}
			if (value.trim().length < 2) {
				return 'Please enter at least 2 characters';
			}
			if (value.length > 100) {
				return 'Maximum 100 characters';
			}
			return null;
		}

		validateLastName(value) {
			if (!value || !value.trim()) {
				return 'Please enter your last name';
			}
			if (value.trim().length < 2) {
				return 'Please enter at least 2 characters';
			}
			if (value.length > 100) {
				return 'Maximum 100 characters';
			}
			return null;
		}

		validateEmail(value) {
			if (!value || !value.trim()) {
				return 'Email address is required';
			}

			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!emailRegex.test(value)) {
				return 'Please enter a valid email address';
			}

			var typos = {
				'gmial.com': 'gmail.com',
				'gmai.com': 'gmail.com',
				'yahooo.com': 'yahoo.com',
				'hotmial.com': 'hotmail.com',
				'outlok.com': 'outlook.com'
			};

			var domain = value.split('@')[1];
			if (domain && typos[domain.toLowerCase()]) {
				return 'Did you mean ' + value.split('@')[0] + '@' + typos[domain.toLowerCase()] + '?';
			}

			return null;
		}

		validatePhone(value) {
			if (!value || !value.trim()) {
				return 'Phone number is required';
			}

			var cleaned = value.replace(/\D/g, '');

			if (!/^(07|01|02|03)\d{9}$/.test(cleaned)) {
				return 'Please enter a valid UK phone number (e.g., 07700 900123)';
			}

			return null;
		}

		showFieldError(fieldId, errorMessage) {
			var field = document.getElementById(fieldId);
			var errorEl = document.getElementById(fieldId + '-error');

			if (errorMessage) {
				field.classList.add('field-error');
				field.setAttribute('aria-invalid', 'true');
				if (errorEl) {
					errorEl.textContent = errorMessage;
				}
			} else {
				this.clearFieldError(fieldId);
			}
		}

		clearFieldError(fieldId) {
			var field = document.getElementById(fieldId);
			var errorEl = document.getElementById(fieldId + '-error');

			if (field) {
				field.classList.remove('field-error');
				field.setAttribute('aria-invalid', 'false');
			}
			if (errorEl) {
				errorEl.textContent = '';
			}
		}

		attachCharCounter() {
			var textarea = document.getElementById('special-requests');
			var counter = document.getElementById('char-count');

			if (textarea && counter) {
				var self = this;
				function update() {
					var remaining = 500 - textarea.value.length;
					counter.textContent = remaining;
					counter.style.color = remaining < 50 ? '#dc2626' : '';
				}
				textarea.addEventListener('input', update);
				update();
			}
		}

		attachPhoneFormatter() {
			var phoneField = document.getElementById('phone');
			if (!phoneField) return;

			phoneField.addEventListener('input', function(e) {
				var value = e.target.value.replace(/\D/g, '');

				if (value.length <= 5 && value.startsWith('07')) {
					e.target.value = value;
					return;
				}
				if (value.startsWith('07') && value.length === 11) {
					e.target.value = value.replace(/(\d{5})(\d{6})/, '$1 $2');
				} else if (value.startsWith('02') && value.length === 11) {
					e.target.value = value.replace(/(\d{3})(\d{4})(\d{4})/, '$1 $2 $3');
				} else if ((value.startsWith('01') || value.startsWith('03')) && value.length === 11) {
					e.target.value = value.replace(/(\d{5})(\d{6})/, '$1 $2');
				} else if (value.length <= 11) {
					e.target.value = value;
				}
			});
		}

		attachBackButton() {
			var backBtn = this.form.querySelector('.bookit-btn-back-step-4');
			if (backBtn) {
				backBtn.addEventListener('click', function() {
					if (typeof window.BookitWizard !== 'undefined') {
						window.BookitWizard.goToStep(3);
					} else {
						window.location.hash = '#step-3';
						window.location.reload();
					}
				});
			}
		}

		handleSubmit(e) {
			e.preventDefault();

			var fields = ['first-name', 'last-name', 'email', 'phone'];
			var validators = {
				'first-name': this.validateFirstName.bind(this),
				'last-name': this.validateLastName.bind(this),
				'email': this.validateEmail.bind(this),
				'phone': this.validatePhone.bind(this)
			};
			var hasErrors = false;

			fields.forEach(function(fieldId) {
				var field = document.getElementById(fieldId);
				var error = validators[fieldId](field.value);
				this.showFieldError(fieldId, error);
				if (error) hasErrors = true;
			}, this);

			var waiverGroup = document.getElementById('cooling-off-waiver-group');
			if (waiverGroup) {
				var waiverCheckbox = document.getElementById('cooling-off-waiver');
				if (waiverCheckbox && !waiverCheckbox.checked) {
					this.showFieldError(
						'cooling-off-waiver',
						'You must acknowledge the cancellation policy to proceed.'
					);
					hasErrors = true;
				} else {
					this.clearFieldError('cooling-off-waiver');
				}
			}

			if (hasErrors) {
				var firstError = document.querySelector('.bookit-contact-form .field-error');
				if (firstError) {
					firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
					firstError.focus();
				}
				return;
			}

			var formData = {
				first_name: document.getElementById('first-name').value.trim(),
				last_name: document.getElementById('last-name').value.trim(),
				email: document.getElementById('email').value.trim().toLowerCase(),
				phone: document.getElementById('phone').value.replace(/\s/g, ''),
				special_requests: document.getElementById('special-requests').value.trim(),
				marketing_consent: document.getElementById('marketing-consent').checked,
				cooling_off_waiver: document.getElementById('cooling-off-waiver')
					? (document.getElementById('cooling-off-waiver').checked ? 1 : 0)
					: 0
			};

			var submitBtn = this.form.querySelector('button[type="submit"]');
			var originalText = submitBtn.textContent;
			submitBtn.disabled = true;
			submitBtn.textContent = 'Saving...';

			var self = this;
			fetch(this.restUrl + 'bookit/v1/contact/save', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.nonce
				},
				body: JSON.stringify(formData)
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				if (data.success) {
					// If on a v2 wizard page, stay on it (session is now step 5)
					// Fall back to redirect_url only if not on a v2 page
					if ( document.querySelector( '.bookit-v2-wizard-container' ) ) {
						window.location.href = window.location.pathname;
					} else {
						window.location.href = data.redirect_url || '/book?step=5';
					}
				} else {
					if (data.errors) {
						var map = {
							first_name: 'first-name',
							last_name: 'last-name',
							email: 'email',
							phone: 'phone',
							special_requests: 'special-requests',
							cooling_off_waiver: 'cooling-off-waiver'
						};
						Object.keys(data.errors).forEach(function(fieldName) {
							var fieldId = map[fieldName] || fieldName.replace('_', '-');
							self.showFieldError(fieldId, data.errors[fieldName]);
						});
						var firstErr = document.querySelector('.bookit-contact-form .field-error');
						if (firstErr) {
							firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
							firstErr.focus();
						}
					} else {
						alert(data.message || 'An error occurred. Please try again.');
					}
				}
			})
			.catch(function(err) {
				console.error('Submission error:', err);
				alert('Unable to save your details. Please try again.');
			})
			.finally(function() {
				submitBtn.disabled = false;
				submitBtn.textContent = originalText;
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function() {
		if (document.querySelector('.bookit-step-4')) {
			new BookitContactForm();
		}
	});
})();
