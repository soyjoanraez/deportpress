<?php
/**
 * Email plano: plan 50/50 creado.
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
echo esc_html__( 'Hemos recibido correctamente el primer 50% de tu pedido. Estos son los saldos pendientes de tu plan:', OWSP_TEXTDOMAIN );
echo "\n\n";

foreach ( $child_orders as $child_order ) {
	$due_date = OWSP_Order_Manager::get_due_date( $child_order );
	printf(
		"%1\$s #%2\$s | %3\$s | %4\$s\n%5\$s\n\n",
		esc_html__( 'Saldo', OWSP_TEXTDOMAIN ),
		esc_html( $child_order->get_order_number() ),
		esc_html( '' !== $due_date ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : '—' ),
		wp_strip_all_tags( wc_price( (float) $child_order->get_total(), array( 'currency' => $child_order->get_currency() ) ) ),
		esc_url_raw( $child_order->get_checkout_payment_url() )
	);
}

echo esc_html__( 'El segundo pago mantendrá la misma pasarela que utilizaste en el pedido inicial.', OWSP_TEXTDOMAIN );
echo "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
