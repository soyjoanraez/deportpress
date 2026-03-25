<?php
/**
 * Email nativo WooCommerce: recordatorio del saldo.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email de recordatorio del segundo pago.
 */
class OWSP_Email_Balance_Reminder extends WC_Email {

	/**
	 * Días antes del vencimiento.
	 */
	protected int $days_before = 0;

	/**
	 * Pedido principal asociado.
	 *
	 * @var WC_Order|false
	 */
	protected $parent_order = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'owsp_balance_reminder';
		$this->customer_email = true;
		$this->title          = __( 'Recordatorio segundo pago 50/50', OWSP_TEXTDOMAIN );
		$this->description    = __( 'Recuerda al cliente que el pedido saldo está próximo a vencer.', OWSP_TEXTDOMAIN );
		$this->template_html  = 'emails/owsp-balance-reminder.php';
		$this->template_plain = 'emails/plain/owsp-balance-reminder.php';
		$this->template_base  = OWSP_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
			'{due_date}'     => '',
		);

		parent::__construct();
	}

	/**
	 * Asunto por defecto.
	 */
	public function get_default_subject() {
		return __( 'Recordatorio: saldo pendiente del pedido #{order_number}', OWSP_TEXTDOMAIN );
	}

	/**
	 * Cabecera por defecto.
	 */
	public function get_default_heading() {
		return __( 'Tu segundo pago está pendiente', OWSP_TEXTDOMAIN );
	}

	/**
	 * Contenido adicional por defecto.
	 */
	public function get_default_additional_content() {
		return __( 'Si ya has pagado, puedes ignorar este mensaje.', OWSP_TEXTDOMAIN );
	}

	/**
	 * Lanza el email.
	 *
	 * @param int  $order_id Pedido saldo.
	 * @param int  $days_before Días restantes.
	 * @param bool $force Reenvío manual.
	 */
	public function trigger( $order_id, $days_before = 0, $force = false ) {
		$this->setup_locale();

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! OWSP_Order_Manager::is_balance_order( $order ) ) {
			$this->restore_locale();
			return;
		}

		if ( ! $force && ( $order->is_paid() || $order->has_status( array( 'cancelled', 'refunded', 'trash' ) ) ) ) {
			$this->restore_locale();
			return;
		}

		$due_date = OWSP_Order_Manager::get_due_date( $order );
		$parent_id = (int) $order->get_parent_id();

		$this->object                         = $order;
		$this->parent_order                   = $parent_id > 0 ? wc_get_order( $parent_id ) : false;
		$this->days_before                    = absint( (int) $days_before );
		$this->recipient                      = $order->get_billing_email();
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{due_date}']     = '' !== $due_date ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : '';

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
				'parent_order'       => $this->parent_order,
				'days_before'        => $this->days_before,
				'due_date'           => OWSP_Order_Manager::get_due_date( $this->object ),
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
				'parent_order'       => $this->parent_order,
				'days_before'        => $this->days_before,
				'due_date'           => OWSP_Order_Manager::get_due_date( $this->object ),
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
