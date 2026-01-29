<?php
/**
 * Date/Time model for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * DateTime model class.
 */
class Bookit_DateTime_Model {

	/**
	 * UK bank holidays 2026 (hardcoded for Phase 1).
	 *
	 * @var array<string>
	 */
	private static $uk_bank_holidays_2026 = array(
		'2026-01-01', // New Year's Day
		'2026-04-03', // Good Friday
		'2026-04-06', // Easter Monday
		'2026-05-04', // Early May bank holiday
		'2026-05-25', // Spring bank holiday
		'2026-08-31', // Summer bank holiday
		'2026-12-25', // Christmas Day
		'2026-12-28', // Boxing Day (substitute)
	);

	/**
	 * Generate time slots for a given date (15-minute increments).
	 * Phase 1: Return all slots 00:00-23:45 (no availability filtering yet).
	 *
	 * @param string $date       Date in Y-m-d format.
	 * @param int    $service_id Service ID (for future availability check).
	 * @param int    $staff_id   Staff ID or 0 for "No Preference".
	 * @return array<int, string> Array of time slots ['09:00:00', '09:15:00', ...].
	 */
	public function generate_time_slots( $date, $service_id, $staff_id ) {
		// Phase 1: Generate all 15-min slots from 00:00 to 23:45.
		// Phase 2 (Task 5) will filter by staff working hours, existing bookings, service duration + buffers.
		$slots = array();
		for ( $hour = 0; $hour < 24; $hour++ ) {
			for ( $minute = 0; $minute < 60; $minute += 15 ) {
				$slots[] = sprintf( '%02d:%02d:00', $hour, $minute );
			}
		}
		return $slots;
	}

	/**
	 * Check if date is a UK bank holiday.
	 * Phase 1: Hardcoded 2026 dates.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return bool True if bank holiday.
	 */
	public function is_bank_holiday( $date ) {
		return in_array( $date, self::$uk_bank_holidays_2026, true );
	}

	/**
	 * Check if date is in the past.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return bool True if past date.
	 */
	public function is_past_date( $date ) {
		return strtotime( $date ) < strtotime( date( 'Y-m-d' ) );
	}

	/**
	 * Format time for display (24h → 12h with AM/PM).
	 *
	 * @param string $time Time in H:i:s format (e.g., '14:00:00').
	 * @return string Formatted time (e.g., '2:00 PM').
	 */
	public function format_time_display( $time ) {
		return date( 'g:i A', strtotime( $time ) );
	}

	/**
	 * Group time slots by period (Morning/Afternoon/Evening).
	 *
	 * @param array<int, string> $time_slots Array of time strings.
	 * @return array<string, array<int, string>> ['morning' => [...], 'afternoon' => [...], 'evening' => [...]].
	 */
	public function group_time_slots( $time_slots ) {
		$grouped = array(
			'morning'   => array(), // 00:00 - 11:59
			'afternoon' => array(), // 12:00 - 16:59
			'evening'   => array(), // 17:00 - 23:59
		);

		foreach ( $time_slots as $time ) {
			$hour = (int) date( 'H', strtotime( $time ) );

			if ( $hour < 12 ) {
				$grouped['morning'][] = $time;
			} elseif ( $hour < 17 ) {
				$grouped['afternoon'][] = $time;
			} else {
				$grouped['evening'][] = $time;
			}
		}

		return $grouped;
	}
}
