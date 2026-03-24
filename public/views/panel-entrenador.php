<?php
/**
 * Vista del shortcode [ed_panel_entrenador].
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

$can_coach_panel = is_user_logged_in()
	&& (
		current_user_can( 'manage_options' )
		|| current_user_can( 'manage_escuela_deportiva' )
		|| current_user_can( 'edit_entrenamientos' )
	);
?>
<div class="ed-panel-entrenador">
	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="ed-panel-entrenador__guest">
			<p class="ed-panel-entrenador__intro"><?php esc_html_e( 'Has d’iniciar sessió per accedir al panel d’entrenador.', 'escuela-deportiva-core' ); ?></p>
			<a class="ed-panel-entrenador__btn ed-panel-entrenador__btn--primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
				<?php esc_html_e( 'Accedir', 'escuela-deportiva-core' ); ?>
			</a>
		</div>
	<?php elseif ( ! $can_coach_panel ) : ?>
		<div class="ed-panel-entrenador__guest" role="alert">
			<p class="ed-panel-entrenador__intro"><?php esc_html_e( 'No tens permís per veure aquest panel.', 'escuela-deportiva-core' ); ?></p>
		</div>
	<?php else : ?>
		<p class="ed-panel-entrenador__intro" id="ed-coach-intro"><?php esc_html_e( 'Consulta el calendari setmanal i gestiona l’assistència de cada entrenament o partit.', 'escuela-deportiva-core' ); ?></p>
		<div class="ed-panel-entrenador__toolbar ed-panel-entrenador__toolbar--calendar">
			<div class="ed-panel-entrenador__week-nav" aria-label="<?php esc_attr_e( 'Canvi de setmana', 'escuela-deportiva-core' ); ?>">
				<button type="button" class="ed-panel-entrenador__btn ed-panel-entrenador__btn--ghost" id="ed-coach-week-prev">
					<?php esc_html_e( 'Setmana anterior', 'escuela-deportiva-core' ); ?>
				</button>
				<p class="ed-panel-entrenador__week-label" id="ed-coach-week-label"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
				<button type="button" class="ed-panel-entrenador__btn ed-panel-entrenador__btn--ghost" id="ed-coach-week-next">
					<?php esc_html_e( 'Setmana següent', 'escuela-deportiva-core' ); ?>
				</button>
			</div>
			<div class="ed-panel-entrenador__filters">
				<div class="ed-panel-entrenador__field">
					<label for="ed-coach-type-filter"><?php esc_html_e( 'Tipus', 'escuela-deportiva-core' ); ?></label>
					<select id="ed-coach-type-filter" class="ed-panel-entrenador__select">
						<option value=""><?php esc_html_e( 'Tots', 'escuela-deportiva-core' ); ?></option>
						<option value="entrenamiento"><?php esc_html_e( 'Entrenaments', 'escuela-deportiva-core' ); ?></option>
						<option value="partido"><?php esc_html_e( 'Partits', 'escuela-deportiva-core' ); ?></option>
					</select>
				</div>
				<div class="ed-panel-entrenador__field">
					<label for="ed-coach-category-filter"><?php esc_html_e( 'Categoria', 'escuela-deportiva-core' ); ?></label>
					<select id="ed-coach-category-filter" class="ed-panel-entrenador__select">
						<option value=""><?php esc_html_e( 'Totes', 'escuela-deportiva-core' ); ?></option>
					</select>
				</div>
			</div>
		</div>
		<p class="ed-panel-entrenador__loading" id="ed-coach-loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p>
		<div class="ed-panel-entrenador__layout">
			<div class="ed-panel-entrenador__events">
				<div class="ed-panel-entrenador__events-list" id="ed-coach-events"></div>
			</div>
			<div class="ed-panel-entrenador__detail">
				<div class="ed-panel-entrenador__empty" id="ed-coach-empty-detail">
					<p><?php esc_html_e( 'Selecciona un entrenament o un partit per veure la convocatòria i desar l’assistència.', 'escuela-deportiva-core' ); ?></p>
				</div>
				<div id="ed-coach-form" class="ed-panel-entrenador__form" hidden>
					<p class="ed-panel-entrenador__session-type" id="ed-coach-session-type"></p>
					<h3 class="ed-panel-entrenador__session-title" id="ed-coach-session-title"></h3>
					<p class="ed-panel-entrenador__session-meta" id="ed-coach-session-meta"></p>
					<div class="ed-panel-entrenador__table-wrap">
						<table class="ed-panel-entrenador__table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Jugador', 'escuela-deportiva-core' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Equip / Context', 'escuela-deportiva-core' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Estat', 'escuela-deportiva-core' ); ?></th>
								</tr>
							</thead>
							<tbody id="ed-coach-jugadores-body"></tbody>
						</table>
					</div>
					<p class="ed-panel-entrenador__actions">
						<button type="button" class="ed-panel-entrenador__btn ed-panel-entrenador__btn--primary" id="ed-coach-save">
							<?php esc_html_e( 'Desar assistència', 'escuela-deportiva-core' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>
		<div class="ed-panel-entrenador__msg" id="ed-coach-msg" role="status" aria-live="polite"></div>
	<?php endif; ?>
</div>
