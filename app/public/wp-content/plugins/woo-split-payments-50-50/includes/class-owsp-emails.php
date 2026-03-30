<?php
/**
 * Registro y disparo de emails nativos de WooCommerce.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gestor de emails 50/50.
 */
class OWSP_Emails {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'add_email_classes' ) );
	}

	/**
	 * Registra las clases de email en el mailer de WooCommerce.
	 *
	 * @param array<string, WC_Email> $emails Emails existentes.
	 * @return array<string, WC_Email>
	 */
	public static function add_email_classes( array $emails ): array {
		require_once OWSP_DIR . 'includes/emails/class-owsp-email-plan-created.php';
		require_once OWSP_DIR . 'includes/emails/class-owsp-email-balance-reminder.php';

		$emails['OWSP_Email_Plan_Created']     = new OWSP_Email_Plan_Created();
		$emails['OWSP_Email_Balance_Reminder'] = new OWSP_Email_Balance_Reminder();

		return $emails;
	}

	/**
	 * Envía el email de creación del plan 50/50.
	 *
	 * @param WC_Order $parent_order Pedido principal.
	 * @param int[]    $child_order_ids Pedidos saldo.
	 */
	public static function send_plan_created_email( WC_Order $parent_order, array $child_order_ids ): void {
		$email = self::get_email_instance( 'OWSP_Email_Plan_Created' );
		if ( $email instanceof OWSP_Email_Plan_Created ) {
			$email->trigger( $parent_order->get_id(), $child_order_ids );
		}
	}

	/**
	 * Envía el recordatorio del segundo pago.
	 */
	public static function send_balance_reminder_email( WC_Order $balance_order, int $days_before ): void {
		$email = self::get_email_instance( 'OWSP_Email_Balance_Reminder' );
		if ( $email instanceof OWSP_Email_Balance_Reminder ) {
			$email->trigger( $balance_order->get_id(), $days_before );
		}
	}

	/**
	 * Reenvío manual del email del plan.
	 *
	 * @param WC_Order $parent_order Pedido principal.
	 */
	public static function resend_plan_created_email( WC_Order $parent_order ): void {
		self::send_plan_created_email( $parent_order, OWSP_Order_Manager::get_balance_order_ids( $parent_order ) );
	}

	/**
	 * Reenvío manual del recordatorio.
	 */
	public static function resend_balance_reminder_email( WC_Order $balance_order, int $days_before ): void {
		$email = self::get_email_instance( 'OWSP_Email_Balance_Reminder' );
		if ( $email instanceof OWSP_Email_Balance_Reminder ) {
			$email->trigger( $balance_order->get_id(), $days_before, true );
		}
	}

	/**
	 * Obtiene una instancia registrada del mailer.
	 */
	private static function get_email_instance( string $key ): ?WC_Email {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$mailer = WC()->mailer();
		$emails = $mailer ? $mailer->get_emails() : array();

		return isset( $emails[ $key ] ) && $emails[ $key ] instanceof WC_Email ? $emails[ $key ] : null;
	}
}
