<?php
if ( isset($_GET['run_e2e_test']) ) {
    add_action('wp_loaded', function() {
        include_once dirname(__FILE__) . '/../plugins/escuela-deportiva-core/test-e2e-logic.php';
        exit;
    });
}
