<?php
/**
 * Contenidor rankings (shortcode ed_rankings).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	class="ed-rankings-widget"
	id="ed-rankings-widget"
	data-torneo-id="<?php echo esc_attr( (string) $torneo_id ); ?>"
	data-tipo="<?php echo esc_attr( $tipo ); ?>"
	data-limit="<?php echo esc_attr( (string) $limit ); ?>"
></div>
