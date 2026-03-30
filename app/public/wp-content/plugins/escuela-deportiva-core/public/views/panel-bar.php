<?php
/**
 * Panel bar staff (shortcode ed_panel_bar).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ed-panel-bar" id="ed-panel-bar">
	<h1 class="ed-panel-bar__title"><?php esc_html_e( 'Bar — comandes', 'escuela-deportiva-core' ); ?></h1>
	<div class="ed-panel-bar__franjas" id="bar-franjas-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Franges', 'escuela-deportiva-core' ); ?>"></div>
	<div class="ed-panel-bar__estados" id="bar-estado-tabs" role="tablist"></div>
	<div class="ed-panel-bar__lista" id="bar-pedidos-lista"></div>
	<p class="ed-panel-bar__loading" id="bar-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
</div>
