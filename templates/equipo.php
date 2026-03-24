<?php
/**
 * Pàgina pública /equipo/{slug}/.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ED_CURRENT_EQUIPO_ID' ) ) {
	return;
}

$equipo_id    = (int) ED_CURRENT_EQUIPO_ID;
$categoria_id = function_exists( 'get_field' ) ? (int) get_field( 'ed_eq_categoria', $equipo_id, false ) : 0;
$deporte_id   = function_exists( 'get_field' ) ? (int) get_field( 'ed_eq_deporte', $equipo_id, false ) : 0;

$hoy     = wp_date( 'Y-m-d' );
$fin_30  = gmdate( 'Y-m-d', strtotime( $hoy . ' 12:00:00 +30 days' ) );
$ini_30  = gmdate( 'Y-m-d', strtotime( $hoy . ' 12:00:00 -30 days' ) );

$ventana_prox = ED_Calendario::get_eventos_semana( $hoy, $fin_30, $categoria_id ?: null );
$proximos     = array_values(
	array_filter(
		$ventana_prox,
		static function ( $e ) use ( $hoy ) {
			if ( ! in_array( $e['tipo'] ?? '', array( 'partido_torneo', 'partido_liga' ), true ) ) {
				return false;
			}
			$dia = substr( (string) ( $e['fecha_hora'] ?? '' ), 0, 10 );
			if ( '' === $dia || $dia < $hoy ) {
				return false;
			}
			$est = (string) ( $e['estado'] ?? '' );
			return ! in_array( $est, array( 'finalizado', 'jugado' ), true );
		}
	)
);

$ventana_res = ED_Calendario::get_eventos_semana( $ini_30, $hoy, $categoria_id ?: null );
$resultados  = array_values(
	array_filter(
		$ventana_res,
		static function ( $e ) {
			if ( ! in_array( $e['tipo'] ?? '', array( 'partido_torneo', 'partido_liga' ), true ) ) {
				return false;
			}
			$est = (string) ( $e['estado'] ?? '' );
			return in_array( $est, array( 'finalizado', 'jugado' ), true );
		}
	)
);
usort(
	$resultados,
	static function ( $a, $b ) {
		return strcmp( (string) ( $b['fecha_hora'] ?? '' ), (string) ( $a['fecha_hora'] ?? '' ) );
	}
);

$clasificacion = $categoria_id ? ED_Sync_Federacion::get_clasificacion_categoria( $categoria_id ) : array();
?>
<div class="ed-equipo-page">

	<div class="ed-equipo-page__header">
		<?php if ( has_post_thumbnail( $equipo_id ) ) : ?>
			<img class="ed-equipo-page__escudo" src="<?php echo esc_url( get_the_post_thumbnail_url( $equipo_id, 'medium' ) ); ?>" alt="">
		<?php endif; ?>
		<div>
			<h1><?php echo esc_html( get_the_title( $equipo_id ) ); ?></h1>
			<?php if ( $deporte_id ) : ?>
				<span class="ed-chip"><?php echo esc_html( get_the_title( $deporte_id ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="ed-equipo-page__grid">

		<section>
			<h2 class="ed-section__title"><?php esc_html_e( 'Propers partits', 'escuela-deportiva-core' ); ?></h2>
			<?php if ( empty( $proximos ) ) : ?>
				<p class="ed-equipo-page__muted"><?php esc_html_e( 'Sense partits programats.', 'escuela-deportiva-core' ); ?></p>
			<?php else : ?>
				<?php foreach ( array_slice( $proximos, 0, 5 ) as $e ) : ?>
					<div class="ed-proximo-item">
						<span class="ed-proximo-item__fecha"><?php echo esc_html( date_i18n( 'd M H:i', strtotime( (string) ( $e['fecha_hora'] ?? '' ) ) ) ); ?></span>
						<span class="ed-proximo-item__titulo"><?php echo esc_html( (string) ( $e['titulo'] ?? '' ) ); ?></span>
						<?php if ( isset( $e['tipo'] ) && 'partido_liga' === $e['tipo'] ) : ?>
							<span class="ed-badge ed-badge--liga"><?php esc_html_e( 'Lliga', 'escuela-deportiva-core' ); ?></span>
						<?php else : ?>
							<span class="ed-badge ed-badge--torneo"><?php esc_html_e( 'Torneig', 'escuela-deportiva-core' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<section>
			<h2 class="ed-section__title"><?php esc_html_e( 'Últims resultats', 'escuela-deportiva-core' ); ?></h2>
			<?php if ( empty( $resultados ) ) : ?>
				<p class="ed-equipo-page__muted"><?php esc_html_e( 'Encara no hi ha resultats recents.', 'escuela-deportiva-core' ); ?></p>
			<?php else : ?>
				<?php foreach ( array_slice( $resultados, 0, 5 ) as $r ) : ?>
					<?php
					$titulo = isset( $r['titulo'] ) ? (string) $r['titulo'] : '';
					$parts  = explode( ' vs ', $titulo, 2 );
					?>
					<div class="ed-resultado-item">
						<div class="ed-resultado-item__equipos">
							<span class="ed-resultado-item__equipo"><?php echo esc_html( $parts[0] ?? '' ); ?></span>
							<strong class="ed-resultado-item__marcador"><?php echo esc_html( (string) ( $r['marcador'] ?? '' ) ); ?></strong>
							<span class="ed-resultado-item__equipo"><?php echo esc_html( $parts[1] ?? '' ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

	</div>

	<?php if ( ! empty( $clasificacion ) ) : ?>
		<section class="ed-equipo-page__clasificacion">
			<h2 class="ed-section__title"><?php esc_html_e( 'Classificació', 'escuela-deportiva-core' ); ?></h2>
			<div class="ed-clasificacion">
				<table class="ed-clasi-table">
					<thead>
						<tr>
							<th>#</th>
							<th><?php esc_html_e( 'Equip', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'PJ', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'PG', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'PE', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'PP', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'GF', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'GC', 'escuela-deportiva-core' ); ?></th>
							<th><?php esc_html_e( 'Pts', 'escuela-deportiva-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $clasificacion as $fila ) : ?>
							<tr class="<?php echo ! empty( $fila->es_nuestro ) ? 'ed-clasi-table__row--nuestro' : ''; ?>">
								<td><?php echo (int) $fila->posicion; ?></td>
								<td><?php echo esc_html( (string) $fila->equipo ); ?></td>
								<td><?php echo (int) $fila->pj; ?></td>
								<td><?php echo (int) $fila->pg; ?></td>
								<td><?php echo (int) $fila->pe; ?></td>
								<td><?php echo (int) $fila->pp; ?></td>
								<td><?php echo (int) $fila->gf; ?></td>
								<td><?php echo (int) $fila->gc; ?></td>
								<td><strong><?php echo (int) $fila->puntos; ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>

</div>
