<?php
if ( ! defined( 'ABSPATH' ) ) {
	require_once 'wp-load.php';
}

if ( ! WC()->session ) {
	WC()->session = new WC_Session_Handler();
	WC()->session->init();
}

if ( ! WC()->cart ) {
    WC()->cart = new WC_Cart();
}

$cart = WC()->cart->get_cart();

foreach ( $cart as $key => $item ) {
    $target_product_id = isset( $item['variation_id'] ) && $item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id'];
    echo "Product / Variation: " . $item['product_id'] . " / " . $item['variation_id'] . "\n";
    echo "Saved Date: " . ( $item['_owsp_due_date'] ?? 'NOT_SET' ) . "\n";
    $product_config = OWSP_Product_Settings::get_due_configuration( $target_product_id );
    print_r( $product_config );
}
