<?php
/**
 * Panel de administración 50/50.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI del plugin.
 */
class OWSP_Admin {

	private const PAGE_SLUG          = 'owsp-plans';
	private const ORDERS_PER_PAGE    = 20;
	private const META_HAS_SPLIT     = '_owsp_has_split_items';
	private const CREATED_VIA_BALANCE = 'owsp-balance';
	private const META_DUE_DATE      = '_owsp_due_date';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_owsp_resend_plan_email', array( __CLASS__, 'handle_resend_plan_email' ) );
		add_action( 'admin_post_owsp_resend_balance_reminder', array( __CLASS__, 'handle_resend_balance_reminder' ) );
	}

	/**
	 * Submenú bajo WooCommerce.
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Pagos 50/50', OWSP_TEXTDOMAIN ),
			__( 'Pagos 50/50', OWSP_TEXTDOMAIN ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render principal.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', OWSP_TEXTDOMAIN ) );
		}

		$filters = self::get_filters();
		$plans   = self::get_parent_orders( $filters );
		$stats   = self::calculate_stats();
		$message = isset( $_GET['owsp_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['owsp_notice'] ) ) : '';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Planes de pago 50/50', OWSP_TEXTDOMAIN ) . '</h1>';
		echo '<p>' . esc_html__( 'Aquí puedes revisar pedidos parciales, saldos pendientes y reenviar correos manualmente.', OWSP_TEXTDOMAIN ) . '</p>';

		if ( '' !== $message ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( self::get_notice_label( $message ) )
			);
		}

		self::render_stats( $stats );
		self::render_filters( $filters, (int) $plans['total'] );
		self::render_table( $plans['orders'], $filters );
		self::render_pagination( $filters, (int) $plans['current_page'], (int) $plans['max_pages'] );

		echo '</div>';
	}

	/**
	 * Reenvía el email resumen del plan.
	 */
	public static function handle_resend_plan_email(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No autorizado.', OWSP_TEXTDOMAIN ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		check_admin_referer( 'owsp_resend_plan_email_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order && ! OWSP_Order_Manager::is_balance_order( $order ) ) {
			OWSP_Emails::resend_plan_created_email( $order );
		}

		self::redirect_with_notice( 'plan_email_sent' );
	}

	/**
	 * Reenvía recordatorio manual.
	 */
	public static function handle_resend_balance_reminder(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No autorizado.', OWSP_TEXTDOMAIN ) );
		}

		$order_id     = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$days_before  = isset( $_GET['days_before'] ) ? absint( wp_unslash( $_GET['days_before'] ) ) : 0;

		check_admin_referer( 'owsp_resend_balance_reminder_' . $order_id . '_' . $days_before );

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order && OWSP_Order_Manager::is_balance_order( $order ) ) {
			OWSP_Emails::resend_balance_reminder_email( $order, $days_before );
		}

		self::redirect_with_notice( 'reminder_sent' );
	}

	/**
	 * Tarjetas resumen.
	 *
	 * @param array<string, int> $stats Totales.
	 */
	private static function render_stats( array $stats ): void {
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0;">';

		foreach ( $stats as $label => $value ) {
			echo '<div style="min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;">';
			echo '<div style="font-size:12px;text-transform:uppercase;color:#646970;letter-spacing:.04em;">' . esc_html( $label ) . '</div>';
			echo '<div style="margin-top:8px;font-size:28px;font-weight:700;line-height:1;">' . esc_html( (string) $value ) . '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Filtros y búsqueda.
	 *
	 * @param array<string, mixed> $filters Filtros activos.
	 * @param int                  $total   Total encontrado.
	 */
	private static function render_filters( array $filters, int $total ): void {
		$status_options  = self::get_status_filter_options();
		$balance_options = self::get_balance_filter_options();

		echo '<form method="get" style="margin:20px 0 12px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">';

		echo '<div>';
		echo '<label for="owsp-search" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__( 'Buscar', OWSP_TEXTDOMAIN ) . '</label>';
		printf(
			'<input type="search" id="owsp-search" name="s" value="%1$s" placeholder="%2$s" class="regular-text" />',
			esc_attr( (string) $filters['search'] ),
			esc_attr__( 'Pedido, cliente, email o producto', OWSP_TEXTDOMAIN )
		);
		echo '</div>';

		echo '<div>';
		echo '<label for="owsp-status-filter" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__( 'Estado del pedido', OWSP_TEXTDOMAIN ) . '</label>';
		echo '<select id="owsp-status-filter" name="status_filter">';
		foreach ( $status_options as $status => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $status ),
				selected( (string) $filters['status'], $status, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '</div>';

		echo '<div>';
		echo '<label for="owsp-balance-filter" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__( 'Estado del saldo', OWSP_TEXTDOMAIN ) . '</label>';
		echo '<select id="owsp-balance-filter" name="balance_state">';
		foreach ( $balance_options as $state => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $state ),
				selected( (string) $filters['balance_state'], $state, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '</div>';

		echo '<div>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Filtrar', OWSP_TEXTDOMAIN ) . '</button> ';
		echo '<a class="button" href="' . esc_url( self::get_page_url() ) . '">' . esc_html__( 'Limpiar', OWSP_TEXTDOMAIN ) . '</a>';
		echo '</div>';

		echo '</div>';
		printf(
			'<p style="margin:12px 0 0;color:#646970;">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: count */
					_n( '%d plan encontrado.', '%d planes encontrados.', $total, OWSP_TEXTDOMAIN ),
					$total
				)
			)
		);
		echo '</form>';
	}

	/**
	 * Tabla de planes.
	 *
	 * @param WC_Order[]            $orders   Pedidos padre.
	 * @param array<string, mixed> $filters  Filtros activos.
	 */
	private static function render_table( array $orders, array $filters ): void {
		echo '<table class="widefat striped" style="margin-top:8px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Pedido', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Cliente', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Primer pago', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Pasarela inicial', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Estado', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Saldos', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<th>' . esc_html__( 'Acciones', OWSP_TEXTDOMAIN ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $orders ) ) {
			$message = self::has_active_filters( $filters )
				? __( 'No se han encontrado planes 50/50 con los filtros actuales.', OWSP_TEXTDOMAIN )
				: __( 'No hay planes 50/50 todavía.', OWSP_TEXTDOMAIN );
			echo '<tr><td colspan="7">' . esc_html( $message ) . '</td></tr>';
		}

		foreach ( $orders as $order ) {
			$balance_orders = OWSP_Order_Manager::get_balance_orders( $order );
			$customer_label = trim( $order->get_formatted_billing_full_name() );
			if ( '' === $customer_label ) {
				$customer_label = $order->get_billing_email();
			}

			echo '<tr>';
			echo '<td>';
			printf(
				'<a href="%1$s"><strong>#%2$s</strong></a><br><span style="color:#646970;">%3$s</span>',
				esc_url( get_edit_post_link( $order->get_id() ) ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() ) ),
				esc_html( $order->get_order_number() ),
				esc_html( wc_format_datetime( $order->get_date_created() ) )
			);
			echo '</td>';
			echo '<td>' . esc_html( $customer_label ) . '</td>';
			echo '<td>' . wp_kses_post( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) . '</td>';
			echo '<td>' . esc_html( $order->get_payment_method_title() ?: $order->get_payment_method() ) . '</td>';
			echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
			echo '<td>' . self::render_balances_list( $balance_orders ) . '</td>';
			echo '<td>' . self::render_parent_actions( $order ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Paginación del listado.
	 *
	 * @param array<string, mixed> $filters Filtros activos.
	 * @param int                  $current_page Página actual.
	 * @param int                  $max_pages Máximo de páginas.
	 */
	private static function render_pagination( array $filters, int $current_page, int $max_pages ): void {
		if ( $max_pages < 2 ) {
			return;
		}

		$base_url = add_query_arg(
			'paged',
			'%#%',
			self::get_page_url( self::get_filter_query_args( $filters, false ) )
		);

		$links = paginate_links(
			array(
				'base'      => $base_url,
				'format'    => '',
				'current'   => max( 1, $current_page ),
				'total'     => $max_pages,
				'prev_text' => __( '&laquo; Anterior', OWSP_TEXTDOMAIN ),
				'next_text' => __( 'Siguiente &raquo;', OWSP_TEXTDOMAIN ),
				'type'      => 'plain',
			)
		);

		if ( empty( $links ) ) {
			return;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages" style="margin:16px 0 0;">' . wp_kses_post( $links ) . '</div></div>';
	}

	/**
	 * Lista HTML de saldos.
	 *
	 * @param WC_Order[] $balance_orders Pedidos saldo.
	 */
	private static function render_balances_list( array $balance_orders ): string {
		if ( empty( $balance_orders ) ) {
			return '<span style="color:#646970;">' . esc_html__( 'Sin saldos creados', OWSP_TEXTDOMAIN ) . '</span>';
		}

		$output = '<div style="display:grid;gap:10px;">';

		foreach ( $balance_orders as $balance_order ) {
			$due_date = OWSP_Order_Manager::get_due_date( $balance_order );
			$output  .= '<div style="padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">';
			$output  .= sprintf(
				'<div><a href="%1$s"><strong>#%2$s</strong></a> · %3$s · %4$s</div>',
				esc_url( get_edit_post_link( $balance_order->get_id() ) ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $balance_order->get_id() ) ),
				esc_html( $balance_order->get_order_number() ),
				wp_kses_post( wc_price( (float) $balance_order->get_total(), array( 'currency' => $balance_order->get_currency() ) ) ),
				esc_html( wc_get_order_status_name( $balance_order->get_status() ) )
			);
			$output  .= '<div style="margin-top:4px;color:#646970;">';
			$output  .= esc_html__(
				'Vence:',
				OWSP_TEXTDOMAIN
			) . ' ' . esc_html( '' !== $due_date ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : '—' );
			$output  .= ' · ';
			$output  .= esc_html__(
				'Pasarela:',
				OWSP_TEXTDOMAIN
			) . ' ' . esc_html( OWSP_Order_Manager::get_required_gateway_title( $balance_order ) ?: OWSP_Order_Manager::get_required_gateway_id( $balance_order ) );
			$output  .= '</div>';
			$output  .= '<div style="margin-top:6px;">' . self::render_balance_actions( $balance_order ) . '</div>';
			$output  .= '</div>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Acciones del pedido padre.
	 */
	private static function render_parent_actions( WC_Order $order ): string {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'owsp_resend_plan_email',
					'order_id'    => $order->get_id(),
					'redirect_to' => self::get_current_view_url(),
				),
				admin_url( 'admin-post.php' )
			),
			'owsp_resend_plan_email_' . $order->get_id()
		);

		return sprintf(
			'<a class="button button-small" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Reenviar plan', OWSP_TEXTDOMAIN )
		);
	}

	/**
	 * Acciones por saldo.
	 */
	private static function render_balance_actions( WC_Order $balance_order ): string {
		$links = array();

		$links[] = sprintf(
			'<a class="button button-small" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $balance_order->get_checkout_payment_url() ),
			esc_html__( 'Abrir pago', OWSP_TEXTDOMAIN )
		);

		if ( ! $balance_order->is_paid() ) {
			foreach ( array( 7, 1 ) as $days_before ) {
				$reminder_url = wp_nonce_url(
					add_query_arg(
						array(
							'action'      => 'owsp_resend_balance_reminder',
							'order_id'    => $balance_order->get_id(),
							'days_before' => $days_before,
							'redirect_to' => self::get_current_view_url(),
						),
						admin_url( 'admin-post.php' )
					),
					'owsp_resend_balance_reminder_' . $balance_order->get_id() . '_' . $days_before
				);

				$links[] = sprintf(
					'<a class="button button-small" href="%1$s">%2$s</a>',
					esc_url( $reminder_url ),
					esc_html(
						sprintf(
							/* translators: %d: days */
							__( 'Enviar aviso %d d', OWSP_TEXTDOMAIN ),
							$days_before
						)
					)
				);
			}
		}

		return implode( ' ', $links );
	}

	/**
	 * Recupera los pedidos padre 50/50.
	 *
	 * @param array<string, mixed> $filters Filtros activos.
	 * @return array{orders:WC_Order[],total:int,current_page:int,max_pages:int}
	 */
	private static function get_parent_orders( array $filters ): array {
		$query_args = self::get_parent_order_query_args( $filters, true, 'objects' );

		if ( 'all' !== $filters['balance_state'] ) {
			$parent_ids = self::get_filtered_parent_ids( $filters );

			if ( empty( $parent_ids ) ) {
				return array(
					'orders'        => array(),
					'total'         => 0,
					'current_page'  => max( 1, (int) $filters['page'] ),
					'max_pages'     => 0,
				);
			}

			$query_args['post__in'] = $parent_ids;
			$query_args['orderby']  = 'post__in';
		}

		$orders = wc_get_orders( $query_args );

		if ( ! is_object( $orders ) || ! isset( $orders->orders, $orders->total, $orders->max_num_pages ) ) {
			return array(
				'orders'        => array(),
				'total'         => 0,
				'current_page'  => 1,
				'max_pages'     => 0,
			);
		}

		$current_page = max( 1, (int) $filters['page'] );
		$max_pages    = max( 0, (int) $orders->max_num_pages );

		if ( empty( $orders->orders ) && $current_page > 1 && $max_pages > 0 ) {
			$query_args['page'] = $max_pages;
			$orders             = wc_get_orders( $query_args );
			$current_page       = $max_pages;
		}

		return array(
			'orders'        => is_object( $orders ) && isset( $orders->orders ) && is_array( $orders->orders ) ? $orders->orders : array(),
			'total'         => is_object( $orders ) && isset( $orders->total ) ? (int) $orders->total : 0,
			'current_page'  => $current_page,
			'max_pages'     => is_object( $orders ) && isset( $orders->max_num_pages ) ? (int) $orders->max_num_pages : 0,
		);
	}

	/**
	 * Estadísticas rápidas del panel.
	 *
	 * @return array<string, int>
	 */
	private static function calculate_stats(): array {
		$plans            = count( self::get_all_parent_order_ids() );
		$pending_balances = count( self::get_balance_order_ids_by_state( 'pending' ) );
		$overdue_balances = count( self::get_balance_order_ids_by_state( 'overdue' ) );
		$paid_balances    = count( self::get_balance_order_ids_by_state( 'paid' ) );

		return array(
			__( 'Planes', OWSP_TEXTDOMAIN )          => $plans,
			__( 'Saldos pendientes', OWSP_TEXTDOMAIN ) => $pending_balances,
			__( 'Saldos vencidos', OWSP_TEXTDOMAIN )   => $overdue_balances,
			__( 'Saldos pagados', OWSP_TEXTDOMAIN )    => $paid_balances,
		);
	}

	/**
	 * Estado de los filtros del panel.
	 *
	 * @return array{page:int,status:string,balance_state:string,search:string}
	 */
	private static function get_filters(): array {
		$page          = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		$status        = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : 'all';
		$balance_state = isset( $_GET['balance_state'] ) ? sanitize_text_field( wp_unslash( $_GET['balance_state'] ) ) : 'all';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$status_options  = self::get_status_filter_options();
		$balance_options = self::get_balance_filter_options();

		if ( ! isset( $status_options[ $status ] ) ) {
			$status = 'all';
		}

		if ( ! isset( $balance_options[ $balance_state ] ) ) {
			$balance_state = 'all';
		}

		return array(
			'page'          => max( 1, $page ),
			'status'        => $status,
			'balance_state' => $balance_state,
			'search'        => trim( $search ),
		);
	}

	/**
	 * Opciones del filtro de estado.
	 *
	 * @return array<string, string>
	 */
	private static function get_status_filter_options(): array {
		$options = array(
			'all' => __( 'Todos los estados', OWSP_TEXTDOMAIN ),
		);

		foreach ( wc_get_order_statuses() as $status => $label ) {
			$options[ $status ] = $label;
		}

		return $options;
	}

	/**
	 * Opciones del filtro de saldo.
	 *
	 * @return array<string, string>
	 */
	private static function get_balance_filter_options(): array {
		return array(
			'all'     => __( 'Todos los saldos', OWSP_TEXTDOMAIN ),
			'pending' => __( 'Con saldo pendiente', OWSP_TEXTDOMAIN ),
			'overdue' => __( 'Con saldo vencido', OWSP_TEXTDOMAIN ),
			'paid'    => __( 'Con saldo pagado', OWSP_TEXTDOMAIN ),
		);
	}

	/**
	 * Determina si hay filtros activos.
	 *
	 * @param array<string, mixed> $filters Filtros activos.
	 */
	private static function has_active_filters( array $filters ): bool {
		return 'all' !== $filters['status'] || 'all' !== $filters['balance_state'] || '' !== $filters['search'];
	}

	/**
	 * Construye la consulta base de pedidos padre.
	 *
	 * @param array<string, mixed> $filters  Filtros activos.
	 * @param bool                 $paginate Si debe paginar.
	 * @param string               $return   Formato de retorno.
	 * @return array<string, mixed>
	 */
	private static function get_parent_order_query_args( array $filters, bool $paginate, string $return ): array {
		$args = array(
			'limit'      => $paginate ? self::ORDERS_PER_PAGE : -1,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'return'     => $return,
			'meta_key'   => self::META_HAS_SPLIT,
			'meta_value' => 'yes',
		);

		if ( $paginate ) {
			$args['page']     = max( 1, (int) $filters['page'] );
			$args['paginate'] = true;
		}

		if ( 'all' !== $filters['status'] ) {
			$args['status'] = $filters['status'];
		}

		if ( '' !== $filters['search'] ) {
			$args['s'] = $filters['search'];
		}

		return $args;
	}

	/**
	 * IDs de todos los pedidos padre.
	 *
	 * @return int[]
	 */
	private static function get_all_parent_order_ids(): array {
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'ids',
				'meta_key'   => self::META_HAS_SPLIT,
				'meta_value' => 'yes',
			)
		);

		return is_array( $orders ) ? array_map( 'absint', $orders ) : array();
	}

	/**
	 * IDs padre filtrados por estado del saldo.
	 *
	 * @param array<string, mixed> $filters Filtros activos.
	 * @return int[]
	 */
	private static function get_filtered_parent_ids( array $filters ): array {
		$parent_ids        = wc_get_orders( self::get_parent_order_query_args( $filters, false, 'ids' ) );
		$balance_parent_ids = self::get_parent_ids_from_balance_state( (string) $filters['balance_state'] );

		if ( ! is_array( $parent_ids ) || empty( $parent_ids ) || empty( $balance_parent_ids ) ) {
			return array();
		}

		$allowed_parents = array_fill_keys( array_map( 'absint', $balance_parent_ids ), true );
		$filtered_ids    = array();

		foreach ( $parent_ids as $parent_id ) {
			$parent_id = absint( $parent_id );

			if ( isset( $allowed_parents[ $parent_id ] ) ) {
				$filtered_ids[] = $parent_id;
			}
		}

		return array_values( array_unique( $filtered_ids ) );
	}

	/**
	 * IDs padre asociados a un estado de saldo.
	 *
	 * @param string $balance_state Estado solicitado.
	 * @return int[]
	 */
	private static function get_parent_ids_from_balance_state( string $balance_state ): array {
		$balance_order_ids = self::get_balance_order_ids_by_state( $balance_state );

		if ( empty( $balance_order_ids ) ) {
			return array();
		}

		$parent_ids = array();

		foreach ( $balance_order_ids as $balance_order_id ) {
			$balance_order = wc_get_order( $balance_order_id );
			if ( ! $balance_order instanceof WC_Order ) {
				continue;
			}

			$parent_id = $balance_order->get_parent_id();
			if ( $parent_id > 0 ) {
				$parent_ids[] = $parent_id;
			}
		}

		return array_values( array_unique( array_map( 'absint', $parent_ids ) ) );
	}

	/**
	 * Devuelve IDs de pedidos saldo por estado.
	 *
	 * @param string $state Estado agregado del saldo.
	 * @return int[]
	 */
	private static function get_balance_order_ids_by_state( string $state ): array {
		$args = array(
			'limit'       => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'return'      => 'ids',
			'created_via' => self::CREATED_VIA_BALANCE,
		);

		if ( 'paid' === $state ) {
			$args['status'] = self::get_paid_balance_statuses();
		} elseif ( in_array( $state, array( 'pending', 'overdue' ), true ) ) {
			$args['status'] = self::get_unpaid_balance_statuses();
		}

		if ( 'overdue' === $state ) {
			$args['meta_key']     = self::META_DUE_DATE;
			$args['meta_value']   = OWSP_Plugin::today();
			$args['meta_compare'] = '<';
			$args['meta_type']    = 'DATE';
		}

		$orders = wc_get_orders( $args );

		return is_array( $orders ) ? array_map( 'absint', $orders ) : array();
	}

	/**
	 * Estados considerados como cobrados para pedidos saldo.
	 *
	 * @return string[]
	 */
	private static function get_paid_balance_statuses(): array {
		return array_values(
			array_filter(
				(array) apply_filters(
					'owsp_admin_paid_balance_statuses',
					array( 'wc-processing', 'wc-completed' )
				)
			)
		);
	}

	/**
	 * Estados considerados pendientes para pedidos saldo.
	 *
	 * @return string[]
	 */
	private static function get_unpaid_balance_statuses(): array {
		$statuses = array_keys( wc_get_order_statuses() );
		$exclude  = array_merge(
			self::get_paid_balance_statuses(),
			array( 'wc-cancelled', 'wc-refunded', 'trash' )
		);

		return array_values( array_diff( $statuses, $exclude ) );
	}

	/**
	 * URL base del panel.
	 *
	 * @param array<string, scalar|null> $args Args extra.
	 */
	private static function get_page_url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
	}

	/**
	 * Query args de los filtros.
	 *
	 * @param array<string, mixed> $filters      Filtros activos.
	 * @param bool                 $include_page Si incluye la página actual.
	 * @return array<string, scalar>
	 */
	private static function get_filter_query_args( array $filters, bool $include_page ): array {
		$args = array();

		if ( '' !== $filters['search'] ) {
			$args['s'] = $filters['search'];
		}

		if ( 'all' !== $filters['status'] ) {
			$args['status_filter'] = $filters['status'];
		}

		if ( 'all' !== $filters['balance_state'] ) {
			$args['balance_state'] = $filters['balance_state'];
		}

		if ( $include_page && (int) $filters['page'] > 1 ) {
			$args['paged'] = (int) $filters['page'];
		}

		return $args;
	}

	/**
	 * URL completa de la vista actual.
	 */
	private static function get_current_view_url(): string {
		return self::get_page_url( self::get_filter_query_args( self::get_filters(), true ) );
	}

	/**
	 * Redirección tras acciones manuales.
	 */
	private static function redirect_with_notice( string $notice ): void {
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		$redirect_to = wp_validate_redirect( $redirect_to, self::get_page_url() );
		$redirect_to = add_query_arg( 'owsp_notice', $notice, $redirect_to );

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Etiquetas de aviso post-acción.
	 */
	private static function get_notice_label( string $notice ): string {
		$labels = array(
			'plan_email_sent' => __( 'Se ha reenviado el email del plan 50/50.', OWSP_TEXTDOMAIN ),
			'reminder_sent'   => __( 'Se ha reenviado el recordatorio del saldo.', OWSP_TEXTDOMAIN ),
		);

		return $labels[ $notice ] ?? __( 'Acción completada.', OWSP_TEXTDOMAIN );
	}
}
