<?php
/**
 * Bootstrap principal del plugin.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inicializa módulos y compatibilidades.
 */
class OWSP_Plugin {

	public const ACTION_GROUP = 'owsp';

	/**
	 * Arranque del plugin.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_missing_wc_notice' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_partial_order_status' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_partial_order_status_to_list' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'add_partial_status_to_paid_statuses' ) );

		OWSP_Emails::register();
		OWSP_Product_Settings::register();
		OWSP_Cart::register();
		OWSP_Order_Manager::register();
		OWSP_Reminders::register();
		OWSP_Admin::register();
	}

	/**
	 * Aviso si WooCommerce no está activo.
	 */
	public static function maybe_render_missing_wc_notice(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Woo Split Payments 50/50 requiere WooCommerce activo.', OWSP_TEXTDOMAIN )
		);
	}

	/**
	 * Hooks de activación.
	 */
	public static function activate(): void {
		self::schedule_reconciliation();
	}

	/**
	 * Hooks de desactivación.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'owsp_daily_reconciliation' );
		wp_clear_scheduled_hook( 'owsp_send_balance_reminder' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'owsp_daily_reconciliation', array(), self::ACTION_GROUP );
			as_unschedule_all_actions( 'owsp_send_balance_reminder', array(), self::ACTION_GROUP );
		}
	}

	/**
	 * Compatibilidad con HPOS.
	 */
	public static function declare_hpos(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OWSP_FILE, true );
		}
	}

	/**
	 * Estado personalizado para pedidos parcialmente abonados.
	 */
	public static function register_partial_order_status(): void {
		register_post_status(
			'wc-owsp-partial',
			array(
				'label'                     => _x( 'Pago 50/50', 'Order status', OWSP_TEXTDOMAIN ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'Pago 50/50 <span class="count">(%s)</span>',
					'Pago 50/50 <span class="count">(%s)</span>',
					OWSP_TEXTDOMAIN
				),
			)
		);
	}

	/**
	 * Añade el estado a la lista de WooCommerce.
	 *
	 * @param array<string, string> $statuses Estados existentes.
	 * @return array<string, string>
	 */
	public static function add_partial_order_status_to_list( array $statuses ): array {
		$new_statuses = array();

		foreach ( $statuses as $status_key => $label ) {
			$new_statuses[ $status_key ] = $label;

			if ( 'wc-processing' === $status_key ) {
				$new_statuses['wc-owsp-partial'] = __( 'Pago 50/50', OWSP_TEXTDOMAIN );
			}
		}

		if ( ! isset( $new_statuses['wc-owsp-partial'] ) ) {
			$new_statuses['wc-owsp-partial'] = __( 'Pago 50/50', OWSP_TEXTDOMAIN );
		}

		return $new_statuses;
	}

	/**
	 * Considera el estado parcial como pagado.
	 *
	 * @param string[] $statuses Estados pagados.
	 * @return string[]
	 */
	public static function add_partial_status_to_paid_statuses( array $statuses ): array {
		if ( ! in_array( 'owsp-partial', $statuses, true ) ) {
			$statuses[] = 'owsp-partial';
		}

		return $statuses;
	}

	/**
	 * Programa la reconciliación diaria.
	 */
	public static function schedule_reconciliation(): void {
		if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
			wp_clear_scheduled_hook( 'owsp_daily_reconciliation' );

			if ( ! as_next_scheduled_action( 'owsp_daily_reconciliation', array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action(
					time() + HOUR_IN_SECONDS,
					DAY_IN_SECONDS,
					'owsp_daily_reconciliation',
					array(),
					self::ACTION_GROUP
				);
			}
			return;
		}

		if ( ! wp_next_scheduled( 'owsp_daily_reconciliation' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'owsp_daily_reconciliation' );
		}
	}

	/**
	 * Formatea importes con la precisión activa.
	 */
	public static function round_amount( float $amount ): float {
		return round( $amount, wc_get_price_decimals() );
	}

	/**
	 * Fecha local YYYY-mm-dd.
	 */
	public static function today(): string {
		return wp_date( 'Y-m-d', null, wp_timezone() );
	}

	/**
	 * Convierte fecha local a timestamp en horario del sitio.
	 */
	public static function due_date_to_timestamp( string $date, int $hour = 9, int $minute = 0 ): int {
		$tz = wp_timezone();
		$dt = new DateTimeImmutable( $date . sprintf( ' %02d:%02d:00', $hour, $minute ), $tz );
		return $dt->getTimestamp();
	}
}
