<?php
// c:\Users\cuchi\Local Sites\deportpress\app\public\test-fecha.php
require_once dirname(__FILE__) . '/wp-load.php';

$args = array(
    'post_type' => 'product',
    's'         => 'Susripcripcion futbol',
    'posts_per_page' => 1
);
$query = new WP_Query($args);
if ($query->have_posts()) {
    $post = $query->posts[0];
    $product_id = $post->ID;
    echo "ID: $product_id\n";
    echo "Type: " . get_post_meta($product_id, '_owsp_due_type', true) . "\n";
    echo "Date: " . get_post_meta($product_id, '_owsp_due_date', true) . "\n";
    print_r(OWSP_Product_Settings::get_due_configuration($product_id));
} else {
    // try any product
    $query = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 10));
    foreach ($query->posts as $post) {
        $product_id = $post->ID;
        echo "ID: $product_id (" . $post->post_title . ")\n";
        echo "Type: " . get_post_meta($product_id, '_owsp_due_type', true) . "\n";
        echo "Date: " . get_post_meta($product_id, '_owsp_due_date', true) . "\n";
        print_r(OWSP_Product_Settings::get_due_configuration($product_id));
    }
}
