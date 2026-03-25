<?php
/**
 * Plantilla partit en viu (URL /torneo/.../partido/.../).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

$partido_id = defined( 'ED_CURRENT_PARTIDO_ID' ) ? (int) constant( 'ED_CURRENT_PARTIDO_ID' ) : 0;
if ( ! $partido_id ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}
$partido = ED_Torneos::format_partido( $partido_id );
$torneo  = ! empty( $partido['torneo_id'] ) ? get_post( (int) $partido['torneo_id'] ) : null;
$el      = $partido['equipo_local'] ?? null;
$ev      = $partido['equipo_visitante'] ?? null;
?>
<div class="ed-partido-live" id="ed-partido-live" data-partido-id="<?php echo esc_attr( (string) $partido_id ); ?>" data-torneo-id="<?php echo esc_attr( (string) ( $partido['torneo_id'] ?? 0 ) ); ?>">

	<?php if ( $torneo instanceof WP_Post ) : ?>
	<div class="ed-partido-live__torneo">
		<a href="<?php echo esc_url( home_url( '/torneo/' . $torneo->post_name . '/' ) ); ?>">
			← <?php echo esc_html( $torneo->post_title ); ?>
		</a>
		<span class="ed-badge ed-badge--fase"><?php echo esc_html( (string) ( $partido['fase'] ?? '' ) ); ?></span>
	</div>
	<?php endif; ?>

	<?php echo ED_Publicidad::render( 'partido_top', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="ed-partido-live__marcador" id="live-marcador">
		<div class="ed-partido-live__equipo ed-partido-live__equipo--local">
			<div class="ed-partido-live__escudo">
				<?php if ( ! empty( $el['escudo'] ) ) : ?>
					<img src="<?php echo esc_url( $el['escudo'] ); ?>" alt="" width="56" height="56" loading="lazy">
				<?php endif; ?>
			</div>
			<div class="ed-partido-live__nombre"><?php echo esc_html( $el['nombre'] ?? '—' ); ?></div>
		</div>
		<div class="ed-partido-live__score">
			<div class="ed-partido-live__score-box">
				<span id="live-goles-local"><?php echo (int) ( $partido['goles_local'] ?? 0 ); ?></span>
				<span class="ed-partido-live__separador">:</span>
				<span id="live-goles-visitante"><?php echo (int) ( $partido['goles_visitante'] ?? 0 ); ?></span>
			</div>
			<div class="ed-partido-live__estado-wrap">
				<span id="live-estado-badge" class="ed-live-badge ed-live-badge--<?php echo esc_attr( (string) ( $partido['estado'] ?? 'programado' ) ); ?>">
					<?php echo esc_html( (string) ( $partido['estado'] ?? '' ) ); ?>
				</span>
				<span id="live-minuto" class="ed-partido-live__minuto"></span>
			</div>
			<div class="ed-partido-live__fecha"><?php echo esc_html( (string) ( $partido['fecha_hora'] ?? '' ) ); ?></div>
		</div>
		<div class="ed-partido-live__equipo ed-partido-live__equipo--visitante">
			<div class="ed-partido-live__escudo">
				<?php if ( ! empty( $ev['escudo'] ) ) : ?>
					<img src="<?php echo esc_url( $ev['escudo'] ); ?>" alt="" width="56" height="56" loading="lazy">
				<?php endif; ?>
			</div>
			<div class="ed-partido-live__nombre"><?php echo esc_html( $ev['nombre'] ?? '—' ); ?></div>
		</div>
	</div>

	<div class="ed-partido-live__timeline">
		<h3 class="ed-partido-live__timeline-title"><?php esc_html_e( 'Esdeveniments', 'escuela-deportiva-core' ); ?></h3>
		<div id="live-timeline"><p class="ed-partido-live__loading"><?php esc_html_e( 'Carregant…', 'escuela-deportiva-core' ); ?></p></div>
	</div>

	<?php echo ED_Publicidad::render( 'partido_bottom', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php
	$cronica_txt = function_exists( 'get_field' ) ? get_field( ED_Torneos::PAR_CRONICA, $partido_id, false ) : '';
	$cronica_txt = is_string( $cronica_txt ) ? $cronica_txt : '';
	if ( '' !== trim( wp_strip_all_tags( $cronica_txt ) ) ) :
		$cronica_post = (int) get_post_meta( $partido_id, '_ed_cronica_post_id', true );
		?>
	<section class="ed-partido-live__cronica">
		<h3 class="ed-partido-live__cronica-title"><?php esc_html_e( 'Crònica', 'escuela-deportiva-core' ); ?></h3>
		<div class="ed-partido-live__cronica-body"><?php echo wp_kses_post( $cronica_txt ); ?></div>
		<?php if ( $cronica_post ) : ?>
			<p><a href="<?php echo esc_url( get_permalink( $cronica_post ) ); ?>"><?php esc_html_e( 'Veure article', 'escuela-deportiva-core' ); ?></a></p>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( defined( 'ED_ONESIGNAL_APP_ID' ) ) : ?>
	<div class="ed-partido-live__seguir">
		<button type="button" class="ed-btn ed-btn--outline" id="btn-seguir-partido"><?php esc_html_e( 'Seguir aquest partit', 'escuela-deportiva-core' ); ?></button>
	</div>
	<?php endif; ?>
</div>
