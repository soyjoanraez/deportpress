<?php
/**
 * Email plano: recordatorio del saldo.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( ! empty( $order->get_billing_first_name() ) ) {
	printf( esc_html__( 'Hola %s,', OWSP_TEXTDOMAIN ), esc_html( $order->get_billing_first_name() ) );
} else {
	esc_html_e( 'Hola,', OWSP_TEXTDOMAIN );
}

echo "\n\n";
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
echo "\n\n";

printf( "%s #%s\n", esc_html__( 'Pedido saldo:', OWSP_TEXTDOMAIN ), esc_html( $order->get_order_number() ) );
if ( $parent_order instanceof WC_Order ) {
	printf( "%s #%s\n", esc_html__( 'Pedido original:', OWSP_TEXTDOMAIN ), esc_html( $parent_order->get_order_number() ) );
}
printf( "%s %s\n", esc_html__( 'Vencimiento:', OWSP_TEXTDOMAIN ), esc_html( '' !== $due_date ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : '—' ) );
printf( "%s %s\n", esc_html__( 'Importe:', OWSP_TEXTDOMAIN ), wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
printf( "%s %s\n\n", esc_html__( 'Pasarela:', OWSP_TEXTDOMAIN ), esc_html( OWSP_Order_Manager::get_required_gateway_title( $order ) ?: OWSP_Order_Manager::get_required_gateway_id( $order ) ) );

echo esc_url_raw( $order->get_checkout_payment_url() );
echo "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
