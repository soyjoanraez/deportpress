<?php
require_once "wp-load.php";

echo "Check what WC update_order_review does with post_data:\n";
$reflection = new ReflectionMethod('WC_AJAX', 'update_order_review');
echo "File: " . $reflection->getFileName() . "\n";
