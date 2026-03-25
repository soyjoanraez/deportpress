<?php
/**
 * Email HTML: plan 50/50 creado.
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

<p><?php esc_html_e( 'Hemos recibido correctamente el primer 50% de tu pedido. Estos son los saldos pendientes de tu plan:', OWSP_TEXTDOMAIN ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e7eb;" border="1">
	<thead>
		<tr>
			<th style="text-align:left;"><?php esc_html_e( 'Pedido saldo', OWSP_TEXTDOMAIN ); ?></th>
			<th style="text-align:left;"><?php esc_html_e( 'Vencimiento', OWSP_TEXTDOMAIN ); ?></th>
			<th style="text-align:left;"><?php esc_html_e( 'Importe', OWSP_TEXTDOMAIN ); ?></th>
			<th style="text-align:left;"><?php esc_html_e( 'Pago', OWSP_TEXTDOMAIN ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $child_orders as $child_order ) : ?>
			<tr>
				<td>#<?php echo esc_html( $child_order->get_order_number() ); ?></td>
				<td>
					<?php
					$due_date = OWSP_Order_Manager::get_due_date( $child_order );
					echo esc_html( '' !== $due_date ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : '—' );
					?>
				</td>
				<td><?php echo wp_kses_post( wc_price( (float) $child_order->get_total(), array( 'currency' => $child_order->get_currency() ) ) ); ?></td>
				<td><a href="<?php echo esc_url( $child_order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pagar ahora', OWSP_TEXTDOMAIN ); ?></a></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p style="margin-top:16px;"><?php esc_html_e( 'El segundo pago mantendrá la misma pasarela que utilizaste en el pedido inicial.', OWSP_TEXTDOMAIN ); ?></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
