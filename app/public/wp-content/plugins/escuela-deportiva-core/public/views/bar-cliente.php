<?php
/**
 * Comanda bar pública (shortcode ed_pedir_bar).
 *
 * @package escuela-deportiva-core
 *
 * @var int $ed_bar_torneo_id ID torneig.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ed-bar-cliente" id="ed-bar-cliente" data-torneo-id="<?php echo esc_attr( (string) (int) $ed_bar_torneo_id ); ?>">
	<h2 class="ed-bar-cliente__title"><?php esc_html_e( 'Comanda anticipada — bar', 'escuela-deportiva-core' ); ?></h2>
	<p class="ed-bar-cliente__intro"><?php esc_html_e( 'Tria productes, franja de recollida i mètode de pagament.', 'escuela-deportiva-core' ); ?></p>
	<div class="ed-bar-cliente__productos" id="ed-bar-productos"></div>
	<form class="ed-bar-cliente__form" id="ed-bar-form">
		<label for="ed-bar-nombre"><?php esc_html_e( 'Nom', 'escuela-deportiva-core' ); ?></label>
		<input type="text" id="ed-bar-nombre" name="nombre" required class="ed-bar-cliente__input">
		<label for="ed-bar-tel"><?php esc_html_e( 'Telèfon (opcional)', 'escuela-deportiva-core' ); ?></label>
		<input type="tel" id="ed-bar-tel" name="telefono" class="ed-bar-cliente__input">
		<label for="ed-bar-franja"><?php esc_html_e( 'Franja horària', 'escuela-deportiva-core' ); ?></label>
		<select id="ed-bar-franja" name="franja" required class="ed-bar-cliente__input"></select>
		<label for="ed-bar-pago"><?php esc_html_e( 'Pagament', 'escuela-deportiva-core' ); ?></label>
		<select id="ed-bar-pago" name="metodo" class="ed-bar-cliente__input">
			<option value="efectivo"><?php esc_html_e( 'Efectiu a recollida', 'escuela-deportiva-core' ); ?></option>
			<option value="online"><?php esc_html_e( 'Online (WooCommerce)', 'escuela-deportiva-core' ); ?></option>
		</select>
		<label for="ed-bar-notas"><?php esc_html_e( 'Notes', 'escuela-deportiva-core' ); ?></label>
		<textarea id="ed-bar-notas" name="notas" class="ed-bar-cliente__input" rows="2"></textarea>
		<button type="submit" class="ed-btn ed-btn--primary"><?php esc_html_e( 'Enviar comanda', 'escuela-deportiva-core' ); ?></button>
	</form>
	<div class="ed-bar-cliente__msg" id="ed-bar-msg" hidden></div>
</div>
