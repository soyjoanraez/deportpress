<?php
/**
 * Recordatorios de segundo pago.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Programa y lanza avisos.
 */
class OWSP_Reminders {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'owsp_send_balance_reminder', array( __CLASS__, 'send_balance_reminder' ), 10, 2 );
		add_action( 'owsp_daily_reconciliation', array( __CLASS__, 'run_reconciliation' ) );

		OWSP_Plugin::schedule_reconciliation();
	}

	/**
	 * Programa recordatorios de 7 y 1 día.
	 */
	public static function schedule_for_balance_order( WC_Order $order ): void {
		$due_date = (string) $order->get_meta( '_owsp_due_date', true );
		if ( '' === $due_date ) {
			return;
		}

		foreach ( array( 7, 1 ) as $days_before ) {
			$timestamp = OWSP_Plugin::due_date_to_timestamp( $due_date ) - ( $days_before * DAY_IN_SECONDS );

			if ( $timestamp <= time() ) {
				continue;
			}

			self::schedule_single_reminder( (int) $order->get_id(), $days_before, $timestamp );
		}
	}

	/**
	 * Envía el recordatorio si el pedido sigue pendiente.
	 *
	 * @param int $order_id Pedido saldo.
	 * @param int $days_before Días antes del vencimiento.
	 */
	public static function send_balance_reminder( int $order_id, int $days_before ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! OWSP_Order_Manager::is_balance_order( $order ) ) {
			return;
		}

		if ( $order->is_paid() || $order->has_status( array( 'cancelled', 'refunded', 'trash' ) ) ) {
			return;
		}

		$meta_key = self::get_reminder_meta_key( $days_before );
		if ( $order->get_meta( $meta_key, true ) ) {
			return;
		}

		OWSP_Emails::send_balance_reminder_email( $order, $days_before );
		$order->update_meta_data( $meta_key, current_time( 'mysql' ) );
		$order->save();
	}

	/**
	 * Reconciliación diaria por si el programador falla.
	 */
	public static function run_reconciliation(): void {
		$orders = wc_get_orders(
			array(
				'limit'      => 200,
				'status'     => array( 'pending', 'failed', 'on-hold' ),
				'meta_key'   => '_owsp_is_balance_order',
				'meta_value' => 'yes',
				'return'     => 'objects',
			)
		);

		if ( empty( $orders ) ) {
			return;
		}

		$today = new DateTimeImmutable( 'today', wp_timezone() );

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$due_date = (string) $order->get_meta( '_owsp_due_date', true );
			if ( '' === $due_date ) {
				continue;
			}

			$due = new DateTimeImmutable( $due_date . ' 00:00:00', wp_timezone() );
			$diff = (int) $today->diff( $due )->format( '%r%a' );

			if ( in_array( $diff, array( 7, 1 ), true ) ) {
				self::send_balance_reminder( (int) $order->get_id(), $diff );
			}
		}
	}

	/**
	 * Programa un aviso individual.
	 */
	private static function schedule_single_reminder( int $order_id, int $days_before, int $timestamp ): void {
		$args = array( $order_id, $days_before );

		if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_next_scheduled_action( 'owsp_send_balance_reminder', $args, OWSP_Plugin::ACTION_GROUP ) ) {
				as_schedule_single_action( $timestamp, 'owsp_send_balance_reminder', $args, OWSP_Plugin::ACTION_GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( 'owsp_send_balance_reminder', $args ) ) {
			wp_schedule_single_event( $timestamp, 'owsp_send_balance_reminder', $args );
		}
	}

	/**
	 * Meta usada para no duplicar envíos.
	 */
	private static function get_reminder_meta_key( int $days_before ): string {
		return '_owsp_reminder_' . $days_before . '_sent_at';
	}
}
