<?php
/**
 * Email HTML: recordatorio del saldo.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
<?php
if ( ! empty( $order->get_billing_first_name() ) ) {
	printf( esc_html__( 'Hola %s,', OWSP_TEXTDOMAIN ), esc_html( $order->get_billing_first_name() ) );
} else {
	esc_html_e( 'Hola,', OWSP_TEXTDOMAIN );
}
?>
</p>

<p>
<?php
echo esc_html(
	sprintf(
		/* translators: %d: days */
		_n(
			'Te recordamos que tu segundo pago vence dentro de %d día.',
			'Te recordamos que tu segundo pago vence dentro de %d días.',
			$days_before,
			OWSP_TEXTDOMAIN
		),
		$days_before
	)
);
?>
</p>

<ul>
	<li><?php esc_html_e( 'Pedido saldo:', OWSP_TEXTDOMAIN ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></li>
	<?php if ( $parent_order instanceof WC_Order ) : ?>
		<li><?php esc_html_e( 'Pedido original:', OWSP_TEXTDOMAIN ); ?> #<?php echo esc_html( $parent_order->get_order_number() ); ?></li>
	<?php endif; ?>
	<li><?php esc_html_e( 'Vencimiento:', OWSP_TEXTDOMAIN ); ?> <?php echo esc_html( '' !== $due_date ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : '—' ); ?></li>
	<li><?php esc_html_e( 'Importe:', OWSP_TEXTDOMAIN ); ?> <?php echo wp_kses_post( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></li>
	<li><?php esc_html_e( 'Pasarela:', OWSP_TEXTDOMAIN ); ?> <?php echo esc_html( OWSP_Order_Manager::get_required_gateway_title( $order ) ?: OWSP_Order_Manager::get_required_gateway_id( $order ) ); ?></li>
</ul>

<p><a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pagar ahora', OWSP_TEXTDOMAIN ); ?></a></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
