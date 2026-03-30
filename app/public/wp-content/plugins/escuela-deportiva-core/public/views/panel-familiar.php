<?php
/**
 * Vista del shortcode [ed_panel_familiar].
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ed-panel-familiar">
	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="ed-panel-familiar__guest">
			<p class="ed-panel-familiar__intro"><?php esc_html_e( 'Has d’iniciar sessió per veure el panel familiar.', 'escuela-deportiva-core' ); ?></p>
			<a class="ed-panel-familiar__btn ed-panel-familiar__btn--primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
				<?php esc_html_e( 'Accedir', 'escuela-deportiva-core' ); ?>
			</a>
		</div>
	<?php else : ?>
		<nav class="ed-panel-familiar__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Seccions del panel', 'escuela-deportiva-core' ); ?>">
			<button type="button" class="ed-panel-familiar__tab is-active" role="tab" aria-selected="true" data-pane="jugadores"><?php esc_html_e( 'Jugadors', 'escuela-deportiva-core' ); ?></button>
			<button type="button" class="ed-panel-familiar__tab" role="tab" aria-selected="false" data-pane="pagos"><?php esc_html_e( 'Pagaments', 'escuela-deportiva-core' ); ?></button>
			<button type="button" class="ed-panel-familiar__tab" role="tab" aria-selected="false" data-pane="asistencias"><?php esc_html_e( 'Assistències', 'escuela-deportiva-core' ); ?></button>
			<button type="button" class="ed-panel-familiar__tab" role="tab" aria-selected="false" data-pane="entradas"><?php esc_html_e( 'Entrades', 'escuela-deportiva-core' ); ?></button>
			<button type="button" class="ed-panel-familiar__tab" role="tab" aria-selected="false" data-pane="bar"><?php esc_html_e( 'Bar', 'escuela-deportiva-core' ); ?></button>
			<button type="button" class="ed-panel-familiar__tab" role="tab" aria-selected="false" data-pane="calendario"><?php esc_html_e( 'Calendari', 'escuela-deportiva-core' ); ?></button>
			<button type="button" class="ed-panel-familiar__tab" role="tab" aria-selected="false" data-pane="mensajes">
				<?php esc_html_e( 'Missatges', 'escuela-deportiva-core' ); ?>
				<span id="ed-badge-mensajes" class="ed-panel-familiar__tab-badge" aria-hidden="true"></span>
			</button>
		</nav>
		<div id="ed-pane-jugadores" class="ed-panel-familiar__pane is-active" role="tabpanel">
			<div id="ed-panel-familiar-app" class="ed-panel-familiar__app" aria-live="polite"></div>
			<p class="ed-panel-familiar__loading" id="ed-panel-familiar-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		</div>
		<div id="ed-pane-pagos" class="ed-panel-familiar__pane" role="tabpanel" hidden>
			<div id="ed-pagos-lista" class="ed-pagos-lista"></div>
			<p class="ed-panel-familiar__loading" id="ed-pagos-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		</div>
		<div id="ed-pane-asistencias" class="ed-panel-familiar__pane" role="tabpanel" hidden>
			<div class="ed-asistencias-toolbar">
				<label for="ed-asist-jugador-sel"><?php esc_html_e( 'Jugador', 'escuela-deportiva-core' ); ?></label>
				<select id="ed-asist-jugador-sel" class="ed-asistencias-select" aria-label="<?php esc_attr_e( 'Selecciona jugador', 'escuela-deportiva-core' ); ?>"></select>
			</div>
			<div id="ed-asistencias-cont" class="ed-asistencias-cont"></div>
			<p class="ed-panel-familiar__loading is-hidden" id="ed-asist-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		</div>
		<div id="ed-pane-entradas" class="ed-panel-familiar__pane" role="tabpanel" hidden>
			<div id="ed-entradas-lista" class="ed-entradas-lista"></div>
			<p class="ed-panel-familiar__loading is-hidden" id="ed-entradas-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		</div>
		<div id="ed-pane-bar" class="ed-panel-familiar__pane" role="tabpanel" hidden>
			<div id="ed-bar-mis-pedidos" class="ed-bar-mis-pedidos"></div>
			<p class="ed-panel-familiar__loading is-hidden" id="ed-bar-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		</div>
		<div id="ed-pane-calendario" class="ed-panel-familiar__pane" role="tabpanel" hidden>
			<p class="ed-panel-familiar__intro ed-pane-calendario__hint"><?php esc_html_e( 'Es filtra pel jugador triat a Assistències (o el primer de la llista).', 'escuela-deportiva-core' ); ?></p>
			<div id="ed-calendario" class="ed-cal-widget" data-jugador-id="" data-categoria-id=""></div>
		</div>
		<div id="ed-pane-mensajes" class="ed-panel-familiar__pane" role="tabpanel" hidden>
			<div id="ed-mensajes-lista" class="ed-mensajes-lista" aria-live="polite"></div>
			<p class="ed-panel-familiar__loading is-hidden" id="ed-mensajes-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		</div>
	<?php endif; ?>
</div>
