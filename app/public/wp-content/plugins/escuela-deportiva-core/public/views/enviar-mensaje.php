<?php
/**
 * Formulari enviar missatge a famílies (shortcode ed_enviar_mensaje).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ed-msg-form" id="ed-msg-form">

	<h2 class="ed-msg-form__title"><?php esc_html_e( 'Nou missatge', 'escuela-deportiva-core' ); ?></h2>

	<label class="ed-msg-form__label" for="msg-tipo-destino"><?php esc_html_e( 'Destinataris', 'escuela-deportiva-core' ); ?></label>
	<select id="msg-tipo-destino" class="ed-msg-form__select" aria-label="<?php esc_attr_e( 'Tipus de destinataris', 'escuela-deportiva-core' ); ?>">
		<option value="club"><?php esc_html_e( 'Tot el club', 'escuela-deportiva-core' ); ?></option>
		<option value="deporte"><?php esc_html_e( 'Un esport…', 'escuela-deportiva-core' ); ?></option>
		<option value="categoria"><?php esc_html_e( 'Una categoria…', 'escuela-deportiva-core' ); ?></option>
		<option value="nucleo"><?php esc_html_e( 'Una família concreta…', 'escuela-deportiva-core' ); ?></option>
		<option value="jugador"><?php esc_html_e( 'Un jugador (el seu nucli)…', 'escuela-deportiva-core' ); ?></option>
	</select>

	<div id="msg-destino-selector" class="ed-msg-form__destino-wrap" hidden>
		<label class="ed-msg-form__label" for="msg-destino-id"><?php esc_html_e( 'Selecció', 'escuela-deportiva-core' ); ?></label>
		<select id="msg-destino-id" class="ed-msg-form__select"></select>
	</div>

	<label class="ed-msg-form__label" for="msg-asunto"><?php esc_html_e( 'Assumpte', 'escuela-deportiva-core' ); ?></label>
	<input type="text" id="msg-asunto" class="ed-msg-form__input" placeholder="<?php esc_attr_e( 'Assumpte del missatge', 'escuela-deportiva-core' ); ?>" autocomplete="off">

	<label class="ed-msg-form__label" for="msg-cuerpo"><?php esc_html_e( 'Missatge', 'escuela-deportiva-core' ); ?></label>
	<textarea id="msg-cuerpo" class="ed-msg-form__textarea" rows="6" placeholder="<?php esc_attr_e( 'Escriu el missatge…', 'escuela-deportiva-core' ); ?>"></textarea>

	<div class="ed-msg-form__actions">
		<button type="button" class="ed-btn ed-btn--primary" id="msg-btn-enviar"><?php esc_html_e( '📨 Enviar missatge', 'escuela-deportiva-core' ); ?></button>
	</div>

	<div id="msg-resultado" class="ed-msg-form__result" hidden></div>

	<section class="ed-msg-form__historial" aria-labelledby="ed-msg-hist-title">
		<h3 id="ed-msg-hist-title"><?php esc_html_e( 'Missatges enviats (recent)', 'escuela-deportiva-core' ); ?></h3>
		<div id="ed-msg-historial-lista" class="ed-msg-historial-lista"></div>
	</section>
</div>
