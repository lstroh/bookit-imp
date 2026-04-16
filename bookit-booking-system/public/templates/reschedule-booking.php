<?php
/**
 * Magic-link reschedule booking page.
 *
 * Variables: $booking_id, $token, $rest_url (see Bookit_Shortcodes::render_reschedule_booking).
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$booking = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT b.id, b.booking_reference, b.booking_date, b.start_time,
				b.end_time, b.status, b.magic_link_token, b.service_id, b.staff_id,
				s.name AS service_name,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name
		 FROM {$wpdb->prefix}bookings b
		 LEFT JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
		 LEFT JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
		 WHERE b.id = %d",
		$booking_id
	),
	ARRAY_A
);

if ( ! $booking || ! hash_equals( (string) $booking['magic_link_token'], (string) $token ) ) {
	?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">
		<div class="bookit-magic-link-notice bookit-magic-link-notice--error">
			<h2 class="bookit-magic-link-notice__title"><?php esc_html_e( 'Invalid or expired link', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-magic-link-notice__text"><?php esc_html_e( 'This link is not valid. Please use the link from your confirmation email or contact us for help.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
</div>
	<?php
	return;
}

$status = (string) $booking['status'];

if ( 'cancelled' === $status ) {
	?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">
		<div class="bookit-magic-link-notice bookit-magic-link-notice--neutral">
			<h2 class="bookit-magic-link-notice__title"><?php esc_html_e( 'Already cancelled', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-magic-link-notice__text"><?php esc_html_e( 'This booking has already been cancelled.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
</div>
	<?php
	return;
}

if ( in_array( $status, array( 'completed', 'no_show' ), true ) ) {
	?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">
		<div class="bookit-magic-link-notice bookit-magic-link-notice--neutral">
			<h2 class="bookit-magic-link-notice__title"><?php esc_html_e( 'Cannot reschedule this booking', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-magic-link-notice__text"><?php esc_html_e( 'This booking cannot be rescheduled online.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
</div>
	<?php
	return;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$notice_hours = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
		'cancellation_window_hours'
	)
);
if ( ! $notice_hours ) {
	$notice_hours = 24;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$business_phone = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'business_phone'
	)
);
$business_phone = is_string( $business_phone ) ? trim( $business_phone ) : '';

$tz          = new DateTimeZone( get_option( 'timezone_string' ) ?: 'Europe/London' );
$appt_dt     = new DateTime( $booking['booking_date'] . ' ' . $booking['start_time'], $tz );
$now_dt      = new DateTime( 'now', $tz );
$hours_until   = ( $appt_dt->getTimestamp() - $now_dt->getTimestamp() ) / 3600;
$within_window = $hours_until < $notice_hours;

$service_label = isset( $booking['service_name'] ) ? (string) $booking['service_name'] : '';
$staff_label   = trim(
	trim( (string) ( $booking['staff_first_name'] ?? '' ) ) . ' ' . trim( (string) ( $booking['staff_last_name'] ?? '' ) )
);

$booking_service_id = isset( $booking['service_id'] ) ? absint( $booking['service_id'] ) : 0;
$booking_staff_id   = isset( $booking['staff_id'] ) ? absint( $booking['staff_id'] ) : 0;

$dow_labels = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );
$day_cells  = array();
$display_month = '';

if ( ! $within_window ) {
	// For the calendar: current month (same grid logic as booking-wizard-v2-step-3.php).
	$today         = new DateTime( 'now', $tz );
	$display_month = $today->format( 'Y-m' );
	$cal_year      = (int) $today->format( 'Y' );
	$cal_month     = (int) $today->format( 'm' );

	require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-datetime-model.php';
	$datetime_model = new Bookit_DateTime_Model();

	$view_year          = $cal_year;
	$view_month_num     = $cal_month;
	$first_day_of_month = mktime( 0, 0, 0, $view_month_num, 1, $view_year );
	$days_in_month      = (int) date( 't', $first_day_of_month );
	$first_dow          = (int) date( 'N', $first_day_of_month );
	$today_str          = date( 'Y-m-d' );

	$pad_count = $first_dow - 1;
	for ( $i = 0; $i < $pad_count; $i++ ) {
		$day_cells[] = array( 'type' => 'pad' );
	}
	for ( $day = 1; $day <= $days_in_month; $day++ ) {
		$date_str = sprintf( '%04d-%02d-%02d', $view_year, $view_month_num, $day );
		$classes  = array( 'bookit-v2-day' );
		$is_past  = $datetime_model->is_past_date( $date_str );
		$is_bank  = $datetime_model->is_bank_holiday( $date_str );
		$disabled = $is_past || $is_bank;

		if ( $date_str === $today_str ) {
			$classes[] = 'bookit-v2-day--today';
		}
		if ( $disabled ) {
			$classes[] = 'bookit-v2-day--disabled';
		} else {
			$classes[] = 'bookit-v2-day--available';
		}

		$day_cells[] = array(
			'type'     => 'day',
			'day'      => $day,
			'date_str' => $date_str,
			'classes'  => $classes,
			'disabled' => $disabled,
		);
	}
}
?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">

		<div class="bookit-confirmation-details">
			<h2><?php esc_html_e( 'Reschedule Your Booking', 'bookit-booking-system' ); ?></h2>
			<table class="bookit-summary-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Reference', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( (string) $booking['booking_reference'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Service', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( $service_label ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Staff', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( $staff_label ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Date', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( date_i18n( 'l, j F Y', strtotime( $booking['booking_date'] ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Time', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( date_i18n( 'g:i a', strtotime( $booking['start_time'] ) ) ); ?></td>
				</tr>
			</table>
		</div>

		<div class="bookit-policy-notice">
			<?php if ( $within_window ) : ?>
				<p class="bookit-policy-notice__text">
					<?php
					printf(
						/* translators: %d: hours before appointment */
						esc_html__( 'Online rescheduling is not available within %d hours of your appointment. Please contact us directly.', 'bookit-booking-system' ),
						(int) $notice_hours
					);
					?>
				</p>
				<?php if ( $business_phone ) : ?>
					<p class="bookit-policy-notice__phone">
						<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $business_phone ) ); ?>">
							<?php echo esc_html( $business_phone ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="bookit-policy-notice__text">
					<?php
					printf(
						/* translators: %d: minimum hours before appointment */
						esc_html__( 'You can reschedule free of charge up to %d hours before your appointment.', 'bookit-booking-system' ),
						(int) $notice_hours
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( ! $within_window ) : ?>
		<div class="bookit-magic-action" id="bookit-reschedule-action">
			<h3><?php esc_html_e( 'Choose a new date and time', 'bookit-booking-system' ); ?></h3>

			<div class="bookit-magic-message" id="bookit-reschedule-message"
				style="display:none;" role="status" aria-live="polite"></div>

			<div class="bookit-v2-wizard-container">
				<!-- Calendar — same PHP grid pattern as booking-wizard-v2-step-3.php (single month, no nav). -->
				<div class="bookit-v2-calendar" id="bookit-reschedule-calendar"
					data-staff-id="<?php echo esc_attr( (string) $booking_staff_id ); ?>"
					data-service-id="<?php echo esc_attr( (string) $booking_service_id ); ?>"
					data-timeslots-url="<?php echo esc_url( rest_url( 'bookit/v1/wizard/timeslots' ) ); ?>">

					<div class="bookit-v2-calendar-header">
						<button
							type="button"
							class="bookit-v2-calendar-nav"
							id="bookit-reschedule-prev-month"
							aria-label="<?php esc_attr_e( 'Previous month', 'bookit-booking-system' ); ?>"
						>&lsaquo;</button>
						<span class="bookit-v2-calendar-title"><?php echo esc_html( date_i18n( 'F Y', strtotime( $display_month . '-01' ) ) ); ?></span>
						<button
							type="button"
							class="bookit-v2-calendar-nav"
							id="bookit-reschedule-next-month"
							aria-label="<?php esc_attr_e( 'Next month', 'bookit-booking-system' ); ?>"
						>&rsaquo;</button>
					</div>
					<div class="bookit-v2-calendar-grid" role="grid" aria-label="<?php esc_attr_e( 'Calendar', 'bookit-booking-system' ); ?>">
						<?php foreach ( $dow_labels as $dow_label ) : ?>
							<div class="bookit-v2-calendar-dow"><?php echo esc_html( $dow_label ); ?></div>
						<?php endforeach; ?>
						<?php foreach ( $day_cells as $cell ) : ?>
							<?php if ( 'pad' === $cell['type'] ) : ?>
								<span class="bookit-v2-day-empty"></span>
							<?php else : ?>
								<button
									type="button"
									class="<?php echo esc_attr( implode( ' ', $cell['classes'] ) ); ?>"
									data-date="<?php echo esc_attr( $cell['date_str'] ); ?>"
									<?php echo $cell['disabled'] ? ' disabled aria-disabled="true"' : ''; ?>
								><?php echo esc_html( (string) $cell['day'] ); ?></button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div><!-- .bookit-v2-calendar -->

				<div class="bookit-v2-slots" id="bookit-reschedule-slots" style="display:none;">
					<p class="bookit-v2-slots__loading" id="bookit-reschedule-slots-loading">
						<?php esc_html_e( 'Loading available times…', 'bookit-booking-system' ); ?>
					</p>
					<div class="bookit-v2-slots-grid bookit-v2-slots__list" id="bookit-reschedule-slots-list"></div>
					<p class="bookit-v2-slots__empty" id="bookit-reschedule-slots-empty"
						style="display:none;">
						<?php esc_html_e( 'No available times on this date. Please choose another day.', 'bookit-booking-system' ); ?>
					</p>
				</div>
			</div><!-- .bookit-v2-wizard-container -->

			<button
				type="button"
				class="bookit-btn-primary"
				id="bookit-reschedule-confirm"
				disabled
				data-booking-id="<?php echo esc_attr( (string) $booking_id ); ?>"
				data-token="<?php echo esc_attr( $token ); ?>"
				data-rest-url="<?php echo esc_url( $rest_url . 'reschedule' ); ?>"
			>
				<?php esc_html_e( 'Confirm Reschedule', 'bookit-booking-system' ); ?>
			</button>
		</div>
		<?php endif; ?>

	</div>
</div>
	<?php if ( ! $within_window ) : ?>
<script>
(function () {
	var calendar    = document.getElementById('bookit-reschedule-calendar');
	var slotsWrap   = document.getElementById('bookit-reschedule-slots');
	var slotsList   = document.getElementById('bookit-reschedule-slots-list');
	var slotsEmpty  = document.getElementById('bookit-reschedule-slots-empty');
	var slotsLoading= document.getElementById('bookit-reschedule-slots-loading');
	var confirmBtn  = document.getElementById('bookit-reschedule-confirm');
	var msgEl       = document.getElementById('bookit-reschedule-message');
	var prevMonthBtn= document.getElementById('bookit-reschedule-prev-month');
	var nextMonthBtn= document.getElementById('bookit-reschedule-next-month');
	var calTitleEl  = calendar ? calendar.querySelector('.bookit-v2-calendar-title') : null;
	var calGridEl   = calendar ? calendar.querySelector('.bookit-v2-calendar-grid') : null;

	if (!calendar || !confirmBtn) return;

	var staffId     = calendar.dataset.staffId;
	var serviceId   = calendar.dataset.serviceId;
	var timeslotsUrl= calendar.dataset.timeslotsUrl;
	var selectedDate= null;
	var selectedTime= null;
	var emptySlotsDefault = slotsEmpty ? slotsEmpty.textContent : '';
	var confirmBtnDefault = confirmBtn.textContent;

	var dowLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
	var today     = new Date();
	var todayYear = today.getFullYear();
	var todayMonth= today.getMonth(); // 0-based
	var todayDay  = today.getDate();

	var currentYear  = todayYear;
	var currentMonth = todayMonth;

	function pad2(n) {
		return String(n).padStart(2, '0');
	}

	function formatDateStr(year, month0, day) {
		return String(year) + '-' + pad2(month0 + 1) + '-' + pad2(day);
	}

	function updateCalendarHeader() {
		if (!calTitleEl) return;
		var dt = new Date(currentYear, currentMonth, 1);
		try {
			calTitleEl.textContent = dt.toLocaleString(undefined, { month: 'long', year: 'numeric' });
		} catch (e) {
			calTitleEl.textContent = dt.toLocaleString();
		}
	}

	function updatePrevNavState() {
		if (!prevMonthBtn) return;
		var isCurrentMonth = (currentYear === todayYear && currentMonth === todayMonth);
		prevMonthBtn.disabled = isCurrentMonth;
	}

	function resetSelectionAndSlots() {
		selectedDate = null;
		selectedTime = null;
		confirmBtn.disabled = true;
		confirmBtn.textContent = confirmBtnDefault;

		if (calGridEl) {
			calGridEl.querySelectorAll('.bookit-v2-day--selected')
				.forEach(function (d) { d.classList.remove('bookit-v2-day--selected'); });
		}

		if (slotsWrap) {
			slotsWrap.style.display = 'none';
		}
		if (slotsList) {
			slotsList.innerHTML = '';
		}
		if (slotsLoading) {
			slotsLoading.style.display = 'none';
		}
		if (slotsEmpty) {
			slotsEmpty.style.display = 'none';
			if (emptySlotsDefault) {
				slotsEmpty.textContent = emptySlotsDefault;
			}
		}
	}

	function renderCalendarGrid() {
		if (!calGridEl) return;

		calGridEl.innerHTML = '';
		dowLabels.forEach(function (label) {
			var d = document.createElement('div');
			d.className = 'bookit-v2-calendar-dow';
			d.textContent = label;
			calGridEl.appendChild(d);
		});

		var first = new Date(currentYear, currentMonth, 1);
		var jsDow = first.getDay(); // 0..6 (Sun..Sat)
		var isoDow = jsDow === 0 ? 7 : jsDow; // 1..7 (Mon..Sun)
		var padCount = isoDow - 1;
		for (var i = 0; i < padCount; i++) {
			var pad = document.createElement('span');
			pad.className = 'bookit-v2-day-empty';
			calGridEl.appendChild(pad);
		}

		var daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
		for (var day = 1; day <= daysInMonth; day++) {
			var dateStr = formatDateStr(currentYear, currentMonth, day);
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'bookit-v2-day';
			btn.textContent = String(day);
			btn.dataset.date = dateStr;

			var isPastMonth = (currentYear < todayYear) || (currentYear === todayYear && currentMonth < todayMonth);
			var isPastDayInCurrentMonth = (currentYear === todayYear && currentMonth === todayMonth && day < todayDay);
			var isDisabled = isPastMonth || isPastDayInCurrentMonth;

			if (currentYear === todayYear && currentMonth === todayMonth && day === todayDay) {
				btn.classList.add('bookit-v2-day--today');
			}

			if (isDisabled) {
				btn.classList.add('bookit-v2-day--disabled');
				btn.disabled = true;
				btn.setAttribute('aria-disabled', 'true');
			} else {
				btn.classList.add('bookit-v2-day--available');
			}

			calGridEl.appendChild(btn);
		}

		updateCalendarHeader();
		updatePrevNavState();
	}

	function selectDateAndFetch(dayBtn) {
		if (!dayBtn || dayBtn.classList.contains('bookit-v2-day--disabled') || dayBtn.disabled) return;

		calGridEl.querySelectorAll('.bookit-v2-day--selected')
			.forEach(function (d) { d.classList.remove('bookit-v2-day--selected'); });
		dayBtn.classList.add('bookit-v2-day--selected');

		selectedDate = dayBtn.dataset.date;
		selectedTime = null;
		confirmBtn.disabled = true;

		slotsWrap.style.display    = 'block';
		slotsLoading.style.display = 'block';
		slotsList.innerHTML        = '';
		slotsEmpty.style.display   = 'none';
		if (slotsEmpty && emptySlotsDefault) {
			slotsEmpty.textContent = emptySlotsDefault;
		}

		var url = timeslotsUrl
			+ '?staff_id=' + encodeURIComponent(staffId)
			+ '&service_id=' + encodeURIComponent(serviceId)
			+ '&date=' + encodeURIComponent(selectedDate);

		fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
			.then(function (result) {
				slotsLoading.style.display = 'none';
				var data = result.body;
				if (!result.ok && data && data.code) {
					slotsEmpty.style.display = 'block';
					slotsEmpty.textContent = (data.message) || '<?php echo esc_js( __( 'No available times on this date. Please choose another day.', 'bookit-booking-system' ) ); ?>';
					return;
				}
				if (data && data.success === false) {
					slotsEmpty.style.display = 'block';
					slotsEmpty.textContent = (data.message) || '<?php echo esc_js( __( 'No available times on this date. Please choose another day.', 'bookit-booking-system' ) ); ?>';
					return;
				}
				var slots = flattenSlotsPayload(data);
				if (!slots.length) {
					slotsEmpty.style.display = 'block';
					return;
				}
				slots.forEach(function (slot) {
					var timeVal = normalizeTimeForApi(slot);
					var label   = formatSlotLabel(slot);
					var btn     = document.createElement('button');
					btn.type      = 'button';
					btn.className = 'bookit-v2-slot bookit-v2-slot--available';
					btn.textContent = label;
					btn.dataset.time = timeVal;

					btn.addEventListener('click', function () {
						slotsList.querySelectorAll('.bookit-v2-slot--selected')
							.forEach(function (s) { s.classList.remove('bookit-v2-slot--selected'); });
						btn.classList.add('bookit-v2-slot--selected');
						selectedTime = timeVal;
						confirmBtn.disabled = false;
					});

					slotsList.appendChild(btn);
				});
			})
			.catch(function () {
				slotsLoading.style.display = 'none';
				slotsEmpty.style.display   = 'block';
				slotsEmpty.textContent     = '<?php echo esc_js( __( 'Could not load available times. Please try again.', 'bookit-booking-system' ) ); ?>';
			});
	}

	function selectDefaultDateForMonthAndFetch() {
		if (!calGridEl) return;
		var startDay = 1;
		if (currentYear === todayYear && currentMonth === todayMonth) {
			startDay = todayDay;
		}
		for (var d = startDay; d <= 31; d++) {
			var candidate = calGridEl.querySelector('[data-date="' + formatDateStr(currentYear, currentMonth, d) + '"]');
			if (candidate && !candidate.disabled && !candidate.classList.contains('bookit-v2-day--disabled')) {
				selectDateAndFetch(candidate);
				return;
			}
		}
	}

	// Initialize current month/year from the server-rendered grid if present.
	var initialDay = calendar.querySelector('.bookit-v2-calendar-grid [data-date]');
	if (initialDay && initialDay.dataset.date && /^\d{4}-\d{2}-\d{2}$/.test(initialDay.dataset.date)) {
		var parts = initialDay.dataset.date.split('-');
		currentYear = parseInt(parts[0], 10);
		currentMonth = parseInt(parts[1], 10) - 1;
	}
	updatePrevNavState();

	if (prevMonthBtn) {
		prevMonthBtn.addEventListener('click', function () {
			if (prevMonthBtn.disabled) return;
			var nextMonthVal = currentMonth - 1;
			var nextYearVal = currentYear;
			if (nextMonthVal < 0) {
				nextMonthVal = 11;
				nextYearVal = currentYear - 1;
			}
			if (nextYearVal < todayYear || (nextYearVal === todayYear && nextMonthVal < todayMonth)) {
				return;
			}
			currentYear = nextYearVal;
			currentMonth = nextMonthVal;
			resetSelectionAndSlots();
			renderCalendarGrid();
			selectDefaultDateForMonthAndFetch();
		});
	}

	if (nextMonthBtn) {
		nextMonthBtn.addEventListener('click', function () {
			var nextMonthVal = currentMonth + 1;
			var nextYearVal = currentYear;
			if (nextMonthVal > 11) {
				nextMonthVal = 0;
				nextYearVal = currentYear + 1;
			}
			currentYear = nextYearVal;
			currentMonth = nextMonthVal;
			resetSelectionAndSlots();
			renderCalendarGrid();
			selectDefaultDateForMonthAndFetch();
		});
	}

	function flattenSlotsPayload(data) {
		if (!data) return [];
		if (Array.isArray(data)) return data;
		var raw = data.slots;
		if (!raw) return [];
		if (Array.isArray(raw)) return raw;
		var out = [];
		if (raw.morning) out = out.concat(raw.morning);
		if (raw.afternoon) out = out.concat(raw.afternoon);
		if (raw.evening) out = out.concat(raw.evening);
		return out;
	}

	function formatSlotLabel(slot) {
		var t = typeof slot === 'string' ? slot : (slot && slot.time ? slot.time : '');
		if (!t) return '';
		var parts = t.split(':');
		if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
		return t;
	}

	function normalizeTimeForApi(slot) {
		var t = typeof slot === 'string' ? slot : (slot && slot.time ? slot.time : '');
		if (!t) return '';
		var parts = t.split(':');
		if (parts.length === 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
		if (parts.length >= 3) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0') + ':' + parts[2].padStart(2, '0');
		return t;
	}

	calendar.addEventListener('click', function (e) {
		var day = e.target.closest('[data-date]');
		if (!day) return;
		selectDateAndFetch(day);
	});

	confirmBtn.addEventListener('click', function () {
		if (!selectedDate || !selectedTime) return;

		confirmBtn.disabled    = true;
		confirmBtn.textContent = '<?php echo esc_js( __( 'Rescheduling...', 'bookit-booking-system' ) ); ?>';

		fetch(confirmBtn.dataset.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				booking_id: parseInt(confirmBtn.dataset.bookingId, 10),
				token:      confirmBtn.dataset.token,
				new_date:   selectedDate,
				new_time:   selectedTime
			})
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				msgEl.style.display = 'block';
				if (data.success) {
					msgEl.className   = 'bookit-magic-message bookit-magic-message--success';
					msgEl.textContent = '<?php echo esc_js( __( 'Your booking has been rescheduled', 'bookit-booking-system' ) ); ?> \u2713';
					confirmBtn.disabled = false;
					confirmBtn.textContent = confirmBtnDefault;
					if (prevMonthBtn) prevMonthBtn.disabled = true;
					if (nextMonthBtn) nextMonthBtn.disabled = true;
					if (calGridEl) {
						calGridEl.querySelectorAll('button')
							.forEach(function (b) { b.disabled = true; });
					}
					setTimeout(function () {
						window.location.href = '<?php echo esc_js( home_url( '/' ) ); ?>';
					}, 3000);
				} else {
					msgEl.className      = 'bookit-magic-message bookit-magic-message--error';
					msgEl.textContent    = (data.message) || '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'bookit-booking-system' ) ); ?>';
					confirmBtn.disabled  = false;
					confirmBtn.textContent = confirmBtnDefault;
				}
			})
			.catch(function () {
				msgEl.style.display    = 'block';
				msgEl.className        = 'bookit-magic-message bookit-magic-message--error';
				msgEl.textContent      = '<?php echo esc_js( __( 'A network error occurred. Please try again.', 'bookit-booking-system' ) ); ?>';
				confirmBtn.disabled    = false;
				confirmBtn.textContent = confirmBtnDefault;
			});
	});
}());
</script>
	<?php endif; ?>
