<?php
/**
 * Plugin Name: Woo Split Payments 50/50
 * Plugin URI: https://escolesesportivesondara.local
 * Description: Permite vender productos de WooCommerce en dos pagos 50/50, con vencimiento configurable por producto, pedidos de saldo y recordatorios automáticos.
 * Version: 0.1.1
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Joan Raez + Codex
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-split-payments-50-50
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

define( 'OWSP_VERSION', '0.1.1' );
define( 'OWSP_FILE', __FILE__ );
define( 'OWSP_DIR', plugin_dir_path( __FILE__ ) );
define( 'OWSP_URL', plugin_dir_url( __FILE__ ) );
define( 'OWSP_TEXTDOMAIN', 'woo-split-payments-50-50' );

require_once OWSP_DIR . 'includes/class-owsp-plugin.php';
require_once OWSP_DIR . 'includes/class-owsp-product-settings.php';
require_once OWSP_DIR . 'includes/class-owsp-cart.php';
require_once OWSP_DIR . 'includes/class-owsp-order-manager.php';
require_once OWSP_DIR . 'includes/class-owsp-reminders.php';
require_once OWSP_DIR . 'includes/class-owsp-emails.php';
require_once OWSP_DIR . 'includes/class-owsp-admin.php';

register_activation_hook( __FILE__, array( 'OWSP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OWSP_Plugin', 'deactivate' ) );

add_action( 'before_woocommerce_init', array( 'OWSP_Plugin', 'declare_hpos' ) );
add_action( 'plugins_loaded', array( 'OWSP_Plugin', 'init' ) );
