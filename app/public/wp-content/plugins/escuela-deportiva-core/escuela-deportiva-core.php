<?php
/**
 * Plugin Name: Escuela Deportiva Core
 * Plugin URI: https://escolesesportivesondara.local
 * Description: DeportPress — nucli funcional (CPTs, rols, ACF, REST, panel familiar). La presentació va al tema fill.
 * Version: 2.7.0
 * Requires at least: 6.3
 * Requires PHP: 8.2
 * Author: Escoles Esportives Ondara
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: escuela-deportiva-core
 * Domain Path: /languages
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

define( 'ED_VERSION', '2.7.0' );
define( 'ED_PLUGIN_FILE', __FILE__ );
define( 'ED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( ED_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once ED_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once ED_PLUGIN_DIR . 'includes/class-activator.php';
require_once ED_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, array( 'ED_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ED_Deactivator', 'deactivate' ) );

require_once ED_PLUGIN_DIR . 'includes/class-loader.php';

ED_Loader::instance();
