<?php
/**
 * Escàner QR entrades (shortcode ed_escaner_qr).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ed-escaner" id="ed-escaner">
	<div class="ed-escaner__header">
		<h1 class="ed-escaner__title"><?php esc_html_e( 'Escàner QR', 'escuela-deportiva-core' ); ?></h1>
		<p class="ed-escaner__hint"><?php esc_html_e( 'Apunta la càmera al QR de l’entrada.', 'escuela-deportiva-core' ); ?></p>
	</div>
	<div class="ed-escaner__camara" id="ed-camara-wrap">
		<video id="ed-camara-video" class="ed-escaner__video" autoplay playsinline muted></video>
		<div class="ed-escaner__visor" aria-hidden="true"></div>
	</div>
	<div class="ed-escaner__manual">
		<label class="screen-reader-text" for="ed-token-manual"><?php esc_html_e( 'Token manual', 'escuela-deportiva-core' ); ?></label>
		<input type="text" id="ed-token-manual" class="ed-escaner__input" placeholder="<?php esc_attr_e( 'Enganxa el token manualment…', 'escuela-deportiva-core' ); ?>" autocomplete="off">
		<button type="button" class="ed-btn ed-btn--primary ed-escaner__btn-manual" id="ed-btn-validar-manual">
			<?php esc_html_e( 'Validar', 'escuela-deportiva-core' ); ?>
		</button>
	</div>
	<div class="ed-escaner__resultado" id="ed-resultado" hidden></div>
	<div class="ed-escaner__stats" id="ed-stats-entradas">
		<div class="ed-stat-card">
			<div class="ed-stat-card__valor" id="stat-total">—</div>
			<div class="ed-stat-card__label"><?php esc_html_e( 'Total venudes', 'escuela-deportiva-core' ); ?></div>
		</div>
		<div class="ed-stat-card">
			<div class="ed-stat-card__valor" id="stat-usadas">—</div>
			<div class="ed-stat-card__label"><?php esc_html_e( 'Ja han entrat', 'escuela-deportiva-core' ); ?></div>
		</div>
		<div class="ed-stat-card">
			<div class="ed-stat-card__valor" id="stat-validas">—</div>
			<div class="ed-stat-card__label"><?php esc_html_e( 'Pendents', 'escuela-deportiva-core' ); ?></div>
		</div>
	</div>
</div>
