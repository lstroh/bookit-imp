<?php
/**
 * Email queue data access layer.
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Email_Queue {

	/**
	 * Insert a queue row.
	 *
	 * @param array $data Queue payload.
	 * @return int|false
	 */
	public static function insert( array $data ): int|false {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_email_queue';
		$booking_id = isset( $data['booking_id'] ) ? (int) $data['booking_id'] : null;
		if ( 0 === $booking_id ) {
			$booking_id = null;
		}

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'booking_id'      => $booking_id,
				'email_type'      => (string) ( $data['email_type'] ?? '' ),
				'recipient_email' => (string) ( $data['recipient_email'] ?? '' ),
				'recipient_name'  => (string) ( $data['recipient_name'] ?? '' ),
				'subject'         => (string) ( $data['subject'] ?? '' ),
				'html_body'       => (string) ( $data['html_body'] ?? '' ),
				'params'          => isset( $data['params'] ) ? (string) $data['params'] : null,
				'scheduled_at'    => isset( $data['scheduled_at'] ) ? (string) $data['scheduled_at'] : gmdate( 'Y-m-d H:i:s' ),
				'max_attempts'    => isset( $data['max_attempts'] ) ? (int) $data['max_attempts'] : 3,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update queue row status.
	 *
	 * @param int    $id     Queue ID.
	 * @param string $status New status.
	 * @param array  $extra  Optional update fields.
	 * @return void
	 */
	public static function update_status( int $id, string $status, array $extra = array() ): void {
		global $wpdb;

		$valid_statuses = array( 'pending', 'processing', 'sent', 'failed', 'cancelled' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return;
		}

		$data    = array_merge( array( 'status' => $status ), $extra );
		$formats = array();
		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'attempts':
				case 'max_attempts':
				case 'booking_id':
					$formats[] = '%d';
					break;
				default:
					$formats[] = is_null( $value ) ? '%s' : '%s';
					break;
			}
		}

		$wpdb->update(
			$wpdb->prefix . 'bookit_email_queue',
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Fetch queue row by ID.
	 *
	 * @param int $id Queue row ID.
	 * @return array|null
	 */
	public static function get_row( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookit_email_queue WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Fetch pending, due queue rows.
	 *
	 * @param int $limit Max rows to fetch.
	 * @return array
	 */
	public static function fetch_pending( int $limit = 10 ): array {
		global $wpdb;

		$limit = max( 1, $limit );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->prefix}bookit_email_queue
				WHERE status = %s
					AND scheduled_at <= %s
				ORDER BY scheduled_at ASC
				LIMIT %d",
				'pending',
				gmdate( 'Y-m-d H:i:s' ),
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Cancel all pending rows for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public static function cancel_for_booking( int $booking_id ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bookit_email_queue',
			array( 'status' => 'cancelled' ),
			array(
				'booking_id' => $booking_id,
				'status'     => 'pending',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}
}
