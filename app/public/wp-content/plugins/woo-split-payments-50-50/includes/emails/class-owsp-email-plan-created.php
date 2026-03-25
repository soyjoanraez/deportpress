<?php
/**
 * Email nativo WooCommerce: plan 50/50 creado.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email resumen del plan.
 */
class OWSP_Email_Plan_Created extends WC_Email {

	/**
	 * Pedidos saldo.
	 *
	 * @var WC_Order[]
	 */
	protected array $child_orders = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'owsp_plan_created';
		$this->customer_email = true;
		$this->title          = __( 'Plan 50/50 creado', OWSP_TEXTDOMAIN );
		$this->description    = __( 'Envía al cliente el resumen de saldos cuando se crea un pedido en dos pagos.', OWSP_TEXTDOMAIN );
		$this->template_html  = 'emails/owsp-plan-created.php';
		$this->template_plain = 'emails/plain/owsp-plan-created.php';
		$this->template_base  = OWSP_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
		);

		parent::__construct();
	}

	/**
	 * Asunto por defecto.
	 */
	public function get_default_subject() {
		return __( 'Tu plan 50/50 del pedido #{order_number}', OWSP_TEXTDOMAIN );
	}

	/**
	 * Cabecera por defecto.
	 */
	public function get_default_heading() {
		return __( 'Tu plan de pago ya está activo', OWSP_TEXTDOMAIN );
	}

	/**
	 * Contenido adicional por defecto.
	 */
	public function get_default_additional_content() {
		return __( 'Puedes usar los enlaces de pago tantas veces como necesites mientras el saldo siga pendiente.', OWSP_TEXTDOMAIN );
	}

	/**
	 * Disparo del email.
	 *
	 * @param int   $order_id Pedido principal.
	 * @param int[] $child_order_ids Pedidos saldo.
	 */
	public function trigger( $order_id, $child_order_ids = array() ) {
		$this->setup_locale();

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			$this->restore_locale();
			return;
		}

		$this->object                         = $order;
		$this->recipient                      = $order->get_billing_email();
		$this->child_orders                   = array();
		$this->placeholders['{order_number}'] = $order->get_order_number();

		foreach ( is_array( $child_order_ids ) ? $child_order_ids : array() as $child_order_id ) {
			$child_order = wc_get_order( (int) $child_order_id );
			if ( $child_order instanceof WC_Order ) {
				$this->child_orders[] = $child_order;
			}
		}

		if ( empty( $this->child_orders ) ) {
			$this->child_orders = OWSP_Order_Manager::get_balance_orders( $order );
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * HTML.
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'child_orders'       => $this->child_orders,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plano.
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'child_orders'       => $this->child_orders,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
