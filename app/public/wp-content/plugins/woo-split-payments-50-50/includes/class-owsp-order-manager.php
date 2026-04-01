<?php
/**
 * Gestión de pedidos padre/hijo.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convierte el primer pedido en un pedido parcial y crea los pedidos de saldo.
 */
class OWSP_Order_Manager {

	private const ORDER_META_HAS_SPLIT      = '_owsp_has_split_items';
	private const ORDER_META_CREATED        = '_owsp_balance_orders_created';
	private const ORDER_META_CHILD_IDS      = '_owsp_balance_order_ids';
	private const ORDER_META_ORIGINAL_STATE = '_owsp_original_paid_status';
	private const ORDER_META_FULLY_PAID_AT  = '_owsp_fully_paid_at';
	private const ORDER_META_IS_BALANCE     = '_owsp_is_balance_order';
	private const ORDER_META_PARENT_ID      = '_owsp_parent_order_id';
	private const ORDER_META_DUE_DATE       = '_owsp_due_date';
	private const ORDER_META_REQUIRED_GATEWAY       = '_owsp_required_gateway';
	private const ORDER_META_REQUIRED_GATEWAY_TITLE = '_owsp_required_gateway_title';
	private const ITEM_META_SELECTED        = '_owsp_split_selected';
	private const ITEM_META_DUE_DATE        = '_owsp_due_date';
	private const ITEM_META_DUE_TYPE        = '_owsp_due_type';
	private const ITEM_META_DUE_DAYS        = '_owsp_due_days';
	private static bool $gateway_notice_added = false;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_line_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_order_if_has_split_items' ), 20, 2 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_handle_paid_order' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_handle_paid_order' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_handle_paid_order' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'maybe_cancel_child_balance_orders' ), 20 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'maybe_cancel_child_balance_orders' ), 20 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_available_payment_gateways' ) );
	}

	/**
	 * Guarda metadatos de plazo por línea.
	 *
	 * @param WC_Order_Item_Product $item Línea de pedido.
	 * @param string                $cart_item_key Clave de carrito.
	 * @param array<string, mixed>  $values Valores del carrito.
	 * @param WC_Order              $order Pedido.
	 */
	public static function persist_line_item_meta( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! call_user_func( array( 'OWSP_Cart', 'is_item_split' ), $values, $cart_item_key ) ) {
			return;
		}

		$config = array(
			'type' => (string) ( $values['_owsp_due_type'] ?? '' ),
			'date' => (string) ( $values['_owsp_due_date'] ?? '' ),
			'days' => (int) ( $values['_owsp_due_days'] ?? 0 ),
		);

		$due_date = OWSP_Product_Settings::resolve_due_date( $config );

		if ( empty( $due_date ) ) {
			$product_id = isset( $values['variation_id'] ) && $values['variation_id'] > 0 ? $values['variation_id'] : $values['product_id'];
			$config     = OWSP_Product_Settings::get_due_configuration( $product_id );
			$due_date   = OWSP_Product_Settings::resolve_due_date( $config );
		}

		$item->add_meta_data( self::ITEM_META_SELECTED, 'yes', true );
		$item->add_meta_data( self::ITEM_META_DUE_TYPE, $config['type'], true );
		$item->add_meta_data( self::ITEM_META_DUE_DAYS, $config['days'], true );
		$item->add_meta_data( self::ITEM_META_DUE_DATE, $due_date, true );
	}

	/**
	 * Marca el pedido si contiene líneas 50/50.
	 *
	 * @param WC_Order             $order Pedido en creación.
	 * @param array<string, mixed> $data Datos checkout.
	 */
	public static function mark_order_if_has_split_items( WC_Order $order, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! ( WC()->cart instanceof WC_Cart ) || ! OWSP_Cart::cart_has_split_items( WC()->cart ) ) {
			return;
		}

		$order->update_meta_data( self::ORDER_META_HAS_SPLIT, 'yes' );
	}

	/**
	 * Gestiona pagos tanto de pedidos padre como de pedidos saldo.
	 *
	 * @param int $order_id ID pedido.
	 */
	public static function maybe_handle_paid_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( self::is_balance_order( $order ) ) {
			self::maybe_mark_parent_as_fully_paid( $order );
			return;
		}

		self::maybe_create_balance_orders( $order );
	}

	/**
	 * Crea uno o varios pedidos de saldo agrupados por fecha.
	 */
	private static function maybe_create_balance_orders( WC_Order $order ): void {
		if ( 'yes' !== $order->get_meta( self::ORDER_META_HAS_SPLIT, true ) ) {
			return;
		}

		if ( 'yes' === $order->get_meta( self::ORDER_META_CREATED, true ) ) {
			return;
		}

		if ( ! $order->is_paid() && ! $order->has_status( 'owsp-partial' ) ) {
			return;
		}

		$groups = self::group_split_items_by_due_date( $order );
		if ( empty( $groups ) ) {
			return;
		}

		$child_ids = array();

		foreach ( $groups as $due_date => $items ) {
			$child_order = wc_create_order(
				array(
					'customer_id' => $order->get_customer_id(),
				)
			);

			if ( is_wp_error( $child_order ) || ! $child_order instanceof WC_Order ) {
				continue;
			}

			$child_order->set_parent_id( $order->get_id() );
			$child_order->set_created_via( 'owsp-balance' );
			$child_order->set_currency( $order->get_currency() );
			$child_order->set_prices_include_tax( $order->get_prices_include_tax() );
			$child_order->set_address( $order->get_address( 'billing' ), 'billing' );
			$child_order->set_address( $order->get_address( 'shipping' ), 'shipping' );
			$child_order->set_customer_note(
				sprintf(
					/* translators: %s: parent order number */
					__( 'Segundo pago del pedido #%s.', OWSP_TEXTDOMAIN ),
					$order->get_order_number()
				)
			);
			$child_order->set_payment_method( $order->get_payment_method() );
			$child_order->set_payment_method_title( $order->get_payment_method_title() );
			$child_order->update_meta_data( self::ORDER_META_IS_BALANCE, 'yes' );
			$child_order->update_meta_data( self::ORDER_META_PARENT_ID, $order->get_id() );
			$child_order->update_meta_data( self::ORDER_META_DUE_DATE, $due_date );
			$child_order->update_meta_data( self::ORDER_META_REQUIRED_GATEWAY, $order->get_payment_method() );
			$child_order->update_meta_data( self::ORDER_META_REQUIRED_GATEWAY_TITLE, $order->get_payment_method_title() );

			foreach ( $items as $source_item ) {
				$fee_item = new WC_Order_Item_Fee();
				$fee_item->set_name(
					sprintf(
						/* translators: %s: product line name */
						__( 'Segundo 50%%: %s', OWSP_TEXTDOMAIN ),
						$source_item->get_name()
					)
				);
				$fee_item->set_amount( (float) $source_item->get_total() );
				$fee_item->set_total( (float) $source_item->get_total() );
				$fee_item->set_tax_status( $source_item->get_tax_status() );

				if ( method_exists( $fee_item, 'set_tax_class' ) ) {
					$fee_item->set_tax_class( $source_item->get_tax_class() );
				}

				$taxes = $source_item->get_taxes();
				if ( ! empty( $taxes ) ) {
					$fee_item->set_taxes( $taxes );
				}

				$fee_item->add_meta_data( '_owsp_source_item_id', $source_item->get_id(), true );
				$fee_item->add_meta_data( '_owsp_source_product_id', $source_item->get_product_id(), true );
				$child_order->add_item( $fee_item );
			}

			$child_order->calculate_totals( false );
			$child_order->save();

			$child_ids[] = $child_order->get_id();

			$child_order->add_order_note(
				sprintf(
					/* translators: 1: due date, 2: parent order number */
					__( 'Pedido de saldo generado automáticamente. Vence el %1$s. Pedido origen: #%2$s.', OWSP_TEXTDOMAIN ),
					wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ),
					$order->get_order_number()
				)
			);

			OWSP_Reminders::schedule_for_balance_order( $child_order );
		}

		if ( empty( $child_ids ) ) {
			return;
		}

		$order->update_meta_data( self::ORDER_META_CREATED, 'yes' );
		$order->update_meta_data( self::ORDER_META_CHILD_IDS, $child_ids );
		$order->update_meta_data( self::ORDER_META_ORIGINAL_STATE, $order->get_status() );
		$order->save();

		if ( ! $order->has_status( 'owsp-partial' ) ) {
			$order->update_status(
				'owsp-partial',
				__( 'Primer 50% cobrado. Pendiente el segundo pago.', OWSP_TEXTDOMAIN ),
				false
			);
		}

		OWSP_Emails::send_plan_created_email( $order, $child_ids );
	}

	/**
	 * Agrupa líneas 50/50 por fecha de vencimiento.
	 *
	 * @return array<string, WC_Order_Item_Product[]>
	 */
	private static function group_split_items_by_due_date( WC_Order $order ): array {
		$groups = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( 'yes' !== $item->get_meta( self::ITEM_META_SELECTED, true ) ) {
				continue;
			}

			$due_date = (string) $item->get_meta( self::ITEM_META_DUE_DATE, true );
			if ( '' === $due_date ) {
				continue;
			}

			if ( ! isset( $groups[ $due_date ] ) ) {
				$groups[ $due_date ] = array();
			}

			$groups[ $due_date ][] = $item;
		}

		ksort( $groups );

		return $groups;
	}

	/**
	 * Si todos los pedidos hijo están pagados, cierra el pedido padre.
	 */
	private static function maybe_mark_parent_as_fully_paid( WC_Order $balance_order ): void {
		if ( ! $balance_order->is_paid() ) {
			return;
		}

		$parent_id = (int) $balance_order->get_meta( self::ORDER_META_PARENT_ID, true );
		if ( $parent_id <= 0 ) {
			$parent_id = (int) $balance_order->get_parent_id();
		}

		if ( $parent_id <= 0 ) {
			return;
		}

		$parent_order = wc_get_order( $parent_id );
		if ( ! $parent_order instanceof WC_Order ) {
			return;
		}

		$child_ids = $parent_order->get_meta( self::ORDER_META_CHILD_IDS, true );
		if ( ! is_array( $child_ids ) || empty( $child_ids ) ) {
			return;
		}

		foreach ( $child_ids as $child_id ) {
			$child = wc_get_order( (int) $child_id );
			if ( ! $child instanceof WC_Order ) {
				return;
			}

			if ( ! $child->is_paid() ) {
				return;
			}
		}

		$final_status = (string) $parent_order->get_meta( self::ORDER_META_ORIGINAL_STATE, true );
		if ( '' === $final_status || 'owsp-partial' === $final_status ) {
			$final_status = 'processing';
		}

		if ( 'owsp-partial' === $parent_order->get_status() ) {
			$parent_order->update_status(
				$final_status,
				__( 'Todos los pagos 50/50 han quedado completados.', OWSP_TEXTDOMAIN ),
				false
			);
		}

		$parent_order->update_meta_data( self::ORDER_META_FULLY_PAID_AT, current_time( 'mysql' ) );
		$parent_order->save();
	}

	/**
	 * Indica si es un pedido saldo.
	 */
	public static function is_balance_order( WC_Order $order ): bool {
		return 'yes' === $order->get_meta( self::ORDER_META_IS_BALANCE, true );
	}

	/**
	 * Devuelve los IDs de pedidos saldo asociados al pedido principal.
	 *
	 * @return int[]
	 */
	public static function get_balance_order_ids( WC_Order $order ): array {
		$ids = $order->get_meta( self::ORDER_META_CHILD_IDS, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'absint', $ids )
			)
		);
	}

	/**
	 * Devuelve los pedidos saldo asociados al pedido principal.
	 *
	 * @return WC_Order[]
	 */
	public static function get_balance_orders( WC_Order $order ): array {
		$orders = array();

		foreach ( self::get_balance_order_ids( $order ) as $order_id ) {
			$balance_order = wc_get_order( $order_id );
			if ( $balance_order instanceof WC_Order ) {
				$orders[] = $balance_order;
			}
		}

		return $orders;
	}

	/**
	 * Fecha de vencimiento del pedido saldo.
	 */
	public static function get_due_date( WC_Order $order ): string {
		return (string) $order->get_meta( self::ORDER_META_DUE_DATE, true );
	}

	/**
	 * Pasarela obligatoria del pedido saldo.
	 */
	public static function get_required_gateway_id( WC_Order $order ): string {
		return (string) $order->get_meta( self::ORDER_META_REQUIRED_GATEWAY, true );
	}

	/**
	 * Título visible de la pasarela obligatoria.
	 */
	public static function get_required_gateway_title( WC_Order $order ): string {
		return (string) $order->get_meta( self::ORDER_META_REQUIRED_GATEWAY_TITLE, true );
	}

	/**
	 * Cancela saldos pendientes si el pedido principal se cancela o reembolsa.
	 *
	 * @param int $order_id Pedido principal.
	 */
	public static function maybe_cancel_child_balance_orders( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || self::is_balance_order( $order ) ) {
			return;
		}

		$child_ids = $order->get_meta( self::ORDER_META_CHILD_IDS, true );
		if ( ! is_array( $child_ids ) || empty( $child_ids ) ) {
			return;
		}

		foreach ( $child_ids as $child_id ) {
			$child_order = wc_get_order( (int) $child_id );
			if ( ! $child_order instanceof WC_Order || $child_order->is_paid() ) {
				continue;
			}

			if ( $child_order->has_status( array( 'cancelled', 'refunded' ) ) ) {
				continue;
			}

			$child_order->update_status(
				'cancelled',
				__( 'Pedido de saldo cancelado porque el pedido principal se canceló o reembolsó.', OWSP_TEXTDOMAIN ),
				false
			);
		}
	}

	/**
	 * Limita el pago del saldo a la misma pasarela del pedido inicial.
	 *
	 * @param array<string, WC_Payment_Gateway> $gateways Pasarelas disponibles.
	 * @return array<string, WC_Payment_Gateway>
	 */
	public static function filter_available_payment_gateways( array $gateways ): array {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}

		$order = self::get_current_balance_order_from_pay_page();
		if ( ! $order instanceof WC_Order ) {
			return $gateways;
		}

		$required_gateway = self::get_required_gateway_id( $order );
		if ( '' === $required_gateway ) {
			return $gateways;
		}

		if ( isset( $gateways[ $required_gateway ] ) ) {
			return array(
				$required_gateway => $gateways[ $required_gateway ],
			);
		}

		if ( ! self::$gateway_notice_added ) {
			self::$gateway_notice_added = true;
			wc_add_notice(
				sprintf(
					/* translators: %s: gateway title */
					__( 'Este segundo pago debe abonarse con la misma pasarela del pedido inicial (%s), pero ahora mismo no está disponible.', OWSP_TEXTDOMAIN ),
					self::get_required_gateway_title( $order ) ?: $required_gateway
				),
				'error'
			);
		}

		return array();
	}

	/**
	 * Devuelve el pedido saldo si estamos en `order-pay`.
	 */
	private static function get_current_balance_order_from_pay_page(): ?WC_Order {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
			return null;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( $order_id <= 0 ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! self::is_balance_order( $order ) ) {
			return null;
		}

		return $order;
	}
}
