<?php
/**
 * Pàgina pública /resultados/ — resultats per setmana i categoria.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

$semana_get = isset( $_GET['semana'] ) ? sanitize_text_field( wp_unslash( $_GET['semana'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$fecha_ref  = '' !== $semana_get ? $semana_get : wp_date( 'Y-m-d' );
$datos      = ED_Calendario::get_resultados_semana( $fecha_ref );

$tz       = wp_timezone();
$d_now    = new DateTimeImmutable( 'now', $tz );
$lunes_hoy = $d_now->modify( 'monday this week' )->format( 'Y-m-d' );

$lunes_prev = gmdate( 'Y-m-d', strtotime( $datos['semana_inicio'] . ' 12:00:00 -7 days' ) );
$lunes_next = gmdate( 'Y-m-d', strtotime( $datos['semana_inicio'] . ' 12:00:00 +7 days' ) );
$es_actual  = ( $datos['semana_inicio'] === $lunes_hoy );

$base = trailingslashit( home_url( '/resultados' ) );
?>
<div class="ed-resultados">

	<div class="ed-resultados__nav">
		<a href="<?php echo esc_url( add_query_arg( 'semana', $lunes_prev, $base ) ); ?>" class="ed-btn ed-btn--outline">‹ <?php esc_html_e( 'Setmana anterior', 'escuela-deportiva-core' ); ?></a>
		<h2 class="ed-resultados__titulo">
			<?php
			if ( $es_actual ) {
				esc_html_e( 'Aquesta setmana', 'escuela-deportiva-core' );
			} else {
				echo esc_html(
					sprintf(
						/* translators: %s: start date of week */
						__( 'Setmana del %s', 'escuela-deportiva-core' ),
						date_i18n( 'j M', strtotime( $datos['semana_inicio'] . ' 12:00:00' ) )
					)
				);
			}
			?>
		</h2>
		<a href="<?php echo esc_url( add_query_arg( 'semana', $lunes_next, $base ) ); ?>" class="ed-btn ed-btn--outline"><?php esc_html_e( 'Setmana següent', 'escuela-deportiva-core' ); ?> ›</a>
	</div>

	<?php if ( empty( $datos['categorias'] ) ) : ?>
		<div class="ed-resultados__vacio">
			<p><?php esc_html_e( 'No hi ha resultats registrats per a aquesta setmana.', 'escuela-deportiva-core' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $datos['categorias'] as $categoria => $partidos ) : ?>
			<section class="ed-resultados__categoria">
				<h3 class="ed-resultados__cat-titulo"><?php echo esc_html( (string) $categoria ); ?></h3>
				<div class="ed-resultados__lista">
					<?php foreach ( $partidos as $p ) : ?>
						<?php
						$titulo = isset( $p['titulo'] ) ? (string) $p['titulo'] : '';
						$parts  = explode( ' vs ', $titulo, 2 );
						$eq_a   = $parts[0] ?? '';
						$eq_b   = $parts[1] ?? '';
						?>
						<div class="ed-resultado-item <?php echo ( isset( $p['tipo'] ) && 'partido_liga' === $p['tipo'] ) ? 'ed-resultado-item--liga' : 'ed-resultado-item--torneo'; ?>">
							<?php if ( isset( $p['tipo'] ) && 'partido_liga' === $p['tipo'] ) : ?>
								<span class="ed-resultado-item__badge"><?php esc_html_e( 'Lliga', 'escuela-deportiva-core' ); ?></span>
							<?php else : ?>
								<span class="ed-resultado-item__badge ed-resultado-item__badge--torneo"><?php esc_html_e( 'Torneig', 'escuela-deportiva-core' ); ?></span>
							<?php endif; ?>
							<div class="ed-resultado-item__equipos">
								<span class="ed-resultado-item__equipo"><?php echo esc_html( $eq_a ); ?></span>
								<strong class="ed-resultado-item__marcador"><?php echo esc_html( (string) ( $p['marcador'] ?? '' ) ); ?></strong>
								<span class="ed-resultado-item__equipo"><?php echo esc_html( $eq_b ); ?></span>
							</div>
							<?php if ( ! empty( $p['url'] ) ) : ?>
								<a href="<?php echo esc_url( (string) $p['url'] ); ?>" class="ed-resultado-item__link"><?php esc_html_e( 'Veure detalls →', 'escuela-deportiva-core' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
