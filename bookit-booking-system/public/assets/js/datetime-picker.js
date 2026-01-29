/**
 * Date & Time Picker - Booking Step 3
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/assets/js
 */

(function() {
	'use strict';

	const UK_BANK_HOLIDAYS_2026 = [
		'2026-01-01',
		'2026-04-03',
		'2026-04-06',
		'2026-05-04',
		'2026-05-25',
		'2026-08-31',
		'2026-12-25',
		'2026-12-28'
	];

	const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

	/**
	 * BookitDateTimePicker class.
	 */
	class BookitDateTimePicker {
		constructor() {
			this.currentDate = new Date();
			this.selectedDate = null;
			this.selectedTime = null;
			this.container = document.querySelector('.bookit-step-3');
			if (!this.container) {
				return;
			}
			this.restUrl = (typeof bookitWizard !== 'undefined' && bookitWizard.restUrl) ? bookitWizard.restUrl : '/wp-json/';
			this.nonce = (typeof bookitWizard !== 'undefined' && bookitWizard.nonce) ? bookitWizard.nonce : '';
			this.init();
		}

		init() {
			this.renderCalendar();
			this.attachEventListeners();
			this.hideShellNextButton();
		}

		hideShellNextButton() {
			const nextBtn = document.getElementById('bookit-next-btn');
			if (nextBtn) {
				nextBtn.style.display = 'none';
			}
		}

		renderCalendar() {
			const monthYearEl = this.container.querySelector('.current-month-year');
			const weekdaysEl = this.container.querySelector('.calendar-weekdays');
			const daysEl = this.container.querySelector('.calendar-days');
			if (!monthYearEl || !weekdaysEl || !daysEl) {
				return;
			}

			const year = this.currentDate.getFullYear();
			const month = this.currentDate.getMonth();
			monthYearEl.textContent = this.formatMonthYear(month, year);

			// Weekday headers (UK: Monday first).
			weekdaysEl.innerHTML = '';
			WEEKDAYS.forEach(function(label) {
				const cell = document.createElement('div');
				cell.className = 'calendar-weekday';
				cell.setAttribute('role', 'columnheader');
				cell.textContent = label;
				weekdaysEl.appendChild(cell);
			});

			// First day of month (0 = Sunday in JS; Monday = 1).
			const firstDay = new Date(year, month, 1);
			let startOffset = firstDay.getDay() - 1; // Monday = 0.
			if (startOffset < 0) {
				startOffset += 7;
			}

			const daysInMonth = new Date(year, month + 1, 0).getDate();
			const prevMonth = month === 0 ? 11 : month - 1;
			const prevYear = month === 0 ? year - 1 : year;
			const daysPrevMonth = new Date(prevYear, prevMonth + 1, 0).getDate();

			daysEl.innerHTML = '';
			const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
			const today = this.toYmd(new Date());

			for (let i = 0; i < totalCells; i++) {
				const cell = document.createElement('button');
				cell.type = 'button';
				cell.className = 'date-cell';
				cell.setAttribute('role', 'gridcell');

				let dayNum;
				let dateYmd;
				let isCurrentMonth;

				if (i < startOffset) {
					dayNum = daysPrevMonth - startOffset + i + 1;
					dateYmd = this.toYmd(new Date(prevYear, prevMonth, dayNum));
					isCurrentMonth = false;
				} else if (i < startOffset + daysInMonth) {
					dayNum = i - startOffset + 1;
					dateYmd = this.toYmd(new Date(year, month, dayNum));
					isCurrentMonth = true;
				} else {
					dayNum = i - startOffset - daysInMonth + 1;
					dateYmd = this.toYmd(new Date(year, month + 1, dayNum));
					isCurrentMonth = false;
				}

				cell.textContent = dayNum;
				cell.dataset.date = dateYmd;

				if (!isCurrentMonth) {
					cell.classList.add('date-other-month');
				}
				if (dateYmd === today) {
					cell.classList.add('date-today');
				}
				if (this.isPastDate(dateYmd)) {
					cell.classList.add('date-past');
					cell.disabled = true;
					cell.setAttribute('aria-disabled', 'true');
				}
				if (this.isBankHoliday(dateYmd)) {
					cell.classList.add('date-holiday');
					cell.disabled = true;
					cell.setAttribute('aria-disabled', 'true');
				}
				if (this.selectedDate === dateYmd) {
					cell.classList.add('date-selected');
				}

				cell.addEventListener('click', function() {
					this.handleDateClick(cell);
				}.bind(this));

				daysEl.appendChild(cell);
			}
		}

		handleDateClick(dateElement) {
			const date = dateElement.dataset.date;

			if (this.isPastDate(date)) {
				return;
			}
			if (this.isBankHoliday(date)) {
				return;
			}
			if (dateElement.classList.contains('date-other-month')) {
				// Allow selecting other month dates for navigation context.
			}

			this.selectedDate = date;
			this.selectedTime = null;

			this.container.querySelectorAll('.date-cell').forEach(function(el) {
				el.classList.remove('date-selected');
			});
			dateElement.classList.add('date-selected');

			this.updateSelectedDateDisplay(date);
			this.loadTimeSlots(date);

			const continueBtn = this.container.querySelector('.bookit-btn-continue-datetime');
			if (continueBtn) {
				continueBtn.disabled = true;
			}
		}

		updateSelectedDateDisplay(date) {
			const displayEl = this.container.querySelector('.selected-date-display');
			const textEl = this.container.querySelector('.selected-date-text');
			if (!displayEl) {
				return;
			}
			const formatted = this.formatDateUK(date);
			displayEl.textContent = 'Selected: ' + formatted;
			if (textEl) {
				textEl.textContent = formatted;
			}
		}

		loadTimeSlots(date) {
			const container = this.container.querySelector('.bookit-timeslots-container');
			const loading = container ? container.querySelector('.timeslots-loading') : null;
			const content = container ? container.querySelector('.timeslots-content') : null;
			const errorEl = container ? container.querySelector('.timeslots-error') : null;

			if (!container) {
				return;
			}

			container.style.display = 'block';
			if (loading) loading.style.display = 'block';
			if (content) content.style.display = 'none';
			if (errorEl) {
				errorEl.style.display = 'none';
				errorEl.textContent = '';
				errorEl.innerHTML = '';
			}

			const url = this.restUrl + 'bookit/v1/timeslots?date=' + encodeURIComponent(date);
			const self = this;

			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': this.nonce
				}
			})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (loading) loading.style.display = 'none';
					if (!data.success) {
						self.showTimeslotsError(data.message || 'Unable to load times.');
						return;
					}
					if (data.available === false) {
						errorEl.innerHTML = '<p><strong>No time slots available</strong></p><p>This date is fully booked. Please select another date.</p>';
						errorEl.style.display = 'block';
						return;
					}
					if (data.slots) {
						self.renderTimeSlots(data.slots);
						if (content) content.style.display = 'block';
					} else {
						self.showTimeslotsError(data.message || 'Unable to load times.');
					}
				})
				.catch(function(err) {
					if (loading) loading.style.display = 'none';
					self.showTimeslotsError('Unable to load available times. Please try again.');
				});
		}

		showTimeslotsError(message) {
			const errorEl = this.container.querySelector('.timeslots-error');
			if (errorEl) {
				errorEl.textContent = message;
				errorEl.style.display = 'block';
			}
		}

		renderTimeSlots(slots) {
			const periods = ['morning', 'afternoon', 'evening'];
			const self = this;

			periods.forEach(function(period) {
				const periodEl = self.container.querySelector('.timeslot-period[data-period="' + period + '"]');
				if (!periodEl) return;
				const grid = periodEl.querySelector('.timeslot-grid');
				if (!grid) return;

				grid.innerHTML = '';

				const list = slots[period];
				if (!list || list.length === 0) {
					periodEl.style.display = 'none';
					return;
				}
				periodEl.style.display = 'block';

				list.forEach(function(time) {
					const button = document.createElement('button');
					button.type = 'button';
					button.className = 'timeslot';
					button.dataset.time = time;
					button.textContent = self.formatTime(time);

					button.addEventListener('click', function() {
						self.handleTimeClick(button);
					});
					grid.appendChild(button);
				});
			});
		}

		handleTimeClick(timeButton) {
			const time = timeButton.dataset.time;
			this.selectedTime = time;

			this.container.querySelectorAll('.timeslot').forEach(function(el) {
				el.classList.remove('timeslot-selected');
			});
			timeButton.classList.add('timeslot-selected');

			const continueBtn = this.container.querySelector('.bookit-btn-continue-datetime');
			if (continueBtn) {
				continueBtn.disabled = false;
			}
		}

		saveDateTimeToSession() {
			if (!this.selectedDate || !this.selectedTime) {
				return Promise.reject(new Error('Please select both date and time.'));
			}

			const self = this;
			const url = this.restUrl + 'bookit/v1/datetime/select';
			const body = JSON.stringify({
				date: this.selectedDate,
				time: this.selectedTime
			});

			return fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.nonce
				},
				body: body
			})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						window.location.hash = '#step-4';
						window.location.reload();
					} else {
						return Promise.reject(new Error(data.message || 'Failed to save date and time.'));
					}
				})
				.catch(function(err) {
					return Promise.reject(err);
				});
		}

		isPastDate(date) {
			const today = this.toYmd(new Date());
			return date < today;
		}

		isBankHoliday(date) {
			return UK_BANK_HOLIDAYS_2026.indexOf(date) !== -1;
		}

		toYmd(d) {
			const y = d.getFullYear();
			const m = String(d.getMonth() + 1).padStart(2, '0');
			const day = String(d.getDate()).padStart(2, '0');
			return y + '-' + m + '-' + day;
		}

		formatMonthYear(month, year) {
			const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
			return months[month] + ' ' + year;
		}

		formatDateUK(dateStr) {
			const d = new Date(dateStr + 'T12:00:00');
			const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
			const dayName = days[d.getDay()];
			const date = String(d.getDate()).padStart(2, '0');
			const year = d.getFullYear();
			const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
			return dayName + ', ' + date + ' ' + months[d.getMonth()] + ' ' + year;
		}

		formatTime(time) {
			const parts = time.split(':');
			const h = parseInt(parts[0], 10);
			const m = parts[1] || '00';
			const ampm = h >= 12 ? 'PM' : 'AM';
			const h12 = h % 12 || 12;
			return h12 + ':' + m + ' ' + ampm;
		}

		attachEventListeners() {
			const self = this;

			const prevBtn = this.container.querySelector('.btn-prev-month');
			const nextBtn = this.container.querySelector('.btn-next-month');
			if (prevBtn) {
				prevBtn.addEventListener('click', function() {
					self.currentDate.setMonth(self.currentDate.getMonth() - 1);
					self.renderCalendar();
				});
			}
			if (nextBtn) {
				nextBtn.addEventListener('click', function() {
					self.currentDate.setMonth(self.currentDate.getMonth() + 1);
					self.renderCalendar();
				});
			}

			const backBtn = this.container.querySelector('.bookit-btn-back-step-3');
			if (backBtn) {
				backBtn.addEventListener('click', function() {
					if (typeof window.BookitWizard !== 'undefined') {
						window.BookitWizard.goToStep(2);
					} else {
						window.location.hash = '#step-2';
						window.location.reload();
					}
				});
			}

			const continueBtn = this.container.querySelector('.bookit-btn-continue-datetime');
			if (continueBtn) {
				continueBtn.addEventListener('click', function() {
					if (!self.selectedDate || !self.selectedTime) {
						alert('Please select both a date and a time.');
						return;
					}
					continueBtn.disabled = true;
					const originalText = continueBtn.textContent;
					continueBtn.textContent = 'Saving...';
					self.saveDateTimeToSession()
						.catch(function(err) {
							continueBtn.disabled = false;
							continueBtn.textContent = originalText;
							alert(err.message || 'Failed to save. Please try again.');
						});
				});
			}
		}
	}

	document.addEventListener('DOMContentLoaded', function() {
		if (document.querySelector('.bookit-step-3')) {
			new BookitDateTimePicker();
		}
	});
})();
