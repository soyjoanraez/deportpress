<?php
/**
 * Contenidor widget MVP (shortcode ed_votar_mvp).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	class="ed-mvp-widget"
	id="ed-mvp-widget"
	data-partido-id="<?php echo esc_attr( (string) (int) $pid ); ?>"
></div>
