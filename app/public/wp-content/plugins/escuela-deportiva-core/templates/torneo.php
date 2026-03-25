<?php
/**
 * Plantilla torneig públic.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

$torneo_id = defined( 'ED_CURRENT_TORNEO_ID' ) ? (int) constant( 'ED_CURRENT_TORNEO_ID' ) : 0;
if ( ! $torneo_id ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}
$torneo  = get_post( $torneo_id );
$estado  = function_exists( 'get_field' ) ? (string) get_field( 'ed_tor_estado', $torneo_id ) : '';
$cuadro  = ED_Torneos::get_cuadro( $torneo_id );
$equipos = ED_Torneos::get_equipos( $torneo_id );
$logo_id = function_exists( 'get_field' ) ? get_field( 'ed_tor_logo', $torneo_id ) : null;
$fecha_ini = function_exists( 'get_field' ) ? (string) get_field( 'ed_tor_fecha_inicio', $torneo_id ) : '';
$lug       = function_exists( 'get_field' ) ? (string) get_field( 'ed_tor_lugar', $torneo_id ) : '';
?>
<div class="ed-torneo" id="ed-torneo">
	<div class="ed-torneo__header">
		<?php if ( $logo_id ) : ?>
			<img class="ed-torneo__logo" src="<?php echo esc_url( wp_get_attachment_image_url( (int) $logo_id, 'medium' ) ); ?>" alt="">
		<?php endif; ?>
		<div>
			<h1 class="ed-torneo__title"><?php echo esc_html( $torneo->post_title ); ?></h1>
			<span class="ed-badge ed-badge--torneo-<?php echo esc_attr( $estado ); ?>"><?php echo esc_html( $estado ); ?></span>
			<div class="ed-torneo__meta">
				<?php echo esc_html( $fecha_ini ); ?>
				<?php
				if ( $lug ) {
					echo ' · ' . esc_html( $lug );
				}
				?>
			</div>
		</div>
	</div>

	<?php
	$entradas_on = function_exists( 'get_field' ) && (bool) get_field( ED_Torneos::TOR_ENTRADAS_ACTIVAS, $torneo_id );
	$precio_ent  = function_exists( 'get_field' ) ? (float) get_field( ED_Torneos::TOR_PRECIO_ENTRADA, $torneo_id ) : 0.0;
	$aforo_max   = function_exists( 'get_field' ) ? (int) get_field( ED_Torneos::TOR_AFORO_MAXIMO, $torneo_id ) : 0;
	$wc_entrada  = function_exists( 'get_field' ) ? (int) get_field( ED_Torneos::TOR_WC_PRODUCT_ENTRADA, $torneo_id ) : 0;
	$stats_ent   = ED_Entradas_QR::get_stats_torneo( $torneo_id );
	$disponibles = $aforo_max > 0 ? $aforo_max - (int) $stats_ent['total'] : 999;
	$rifa_nums   = function_exists( 'get_field' ) ? (int) ( get_field( ED_Torneos::TOR_RIFA_NUMS_ENTRADA, $torneo_id ) ?: 1 ) : 1;
	?>
	<?php if ( $entradas_on && $wc_entrada > 0 ) : ?>
	<section class="ed-torneo__section ed-entradas-section">
		<h2 class="ed-section__title"><?php esc_html_e( 'Entrades', 'escuela-deportiva-core' ); ?></h2>
		<div class="ed-entrada-cta">
			<div class="ed-entrada-cta__info">
				<span class="ed-entrada-cta__precio"><?php echo esc_html( number_format_i18n( $precio_ent, 2 ) ); ?> €</span>
				<span class="ed-entrada-cta__aforo">
					<?php if ( $aforo_max > 0 ) : ?>
						<?php echo esc_html( sprintf( __( '%d entrades disponibles', 'escuela-deportiva-core' ), max( 0, $disponibles ) ) ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Entrada general', 'escuela-deportiva-core' ); ?>
					<?php endif; ?>
				</span>
				<?php if ( function_exists( 'get_field' ) && (bool) get_field( ED_Torneos::TOR_RIFA_ACTIVA, $torneo_id ) ) : ?>
				<span class="ed-entrada-cta__bonus">
					<?php
					if ( $rifa_nums > 1 ) {
						echo esc_html( sprintf( __( 'Inclou números de rifa (%d)', 'escuela-deportiva-core' ), $rifa_nums ) );
					} else {
						esc_html_e( 'Inclou número de rifa', 'escuela-deportiva-core' );
					}
					?>
				</span>
				<?php endif; ?>
			</div>
			<?php if ( $disponibles > 0 ) : ?>
				<a href="<?php echo esc_url( get_permalink( $wc_entrada ) ); ?>" class="ed-btn ed-btn--primary ed-btn--lg">
					<?php esc_html_e( 'Comprar entrada', 'escuela-deportiva-core' ); ?>
				</a>
			<?php else : ?>
				<span class="ed-badge ed-badge--cancelado"><?php esc_html_e( 'Entrades exhaurides', 'escuela-deportiva-core' ); ?></span>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! empty( $equipos ) ) : ?>
	<section class="ed-torneo__section">
		<h2 class="ed-section__title"><?php esc_html_e( 'Equips', 'escuela-deportiva-core' ); ?> (<?php echo count( $equipos ); ?>)</h2>
		<div class="ed-torneo__equipos">
			<?php foreach ( $equipos as $eq ) : ?>
				<div class="ed-equipo-chip">
					<?php if ( ! empty( $eq['escudo'] ) ) : ?>
						<img src="<?php echo esc_url( $eq['escudo'] ); ?>" alt="">
					<?php endif; ?>
					<span><?php echo esc_html( $eq['nombre'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php foreach ( array( 'cuartos', 'semis', 'final' ) as $fase ) : ?>
		<?php if ( ! empty( $cuadro[ $fase ] ) ) : ?>
		<section class="ed-torneo__section">
			<h2 class="ed-section__title"><?php echo esc_html( ucfirst( $fase ) ); ?></h2>
			<div class="ed-cuadro-fase">
				<?php foreach ( $cuadro[ $fase ] as $p ) : ?>
					<?php
					$eloc = $p['equipo_local'] ?? null;
					$evis = $p['equipo_visitante'] ?? null;
					?>
					<a href="<?php echo esc_url( $p['url'] ?? '#' ); ?>" class="ed-partido-card">
						<div class="ed-partido-card__equipos">
							<div class="ed-partido-card__equipo">
								<?php if ( ! empty( $eloc['escudo'] ) ) : ?>
									<img src="<?php echo esc_url( $eloc['escudo'] ); ?>" alt="">
								<?php endif; ?>
								<span><?php echo esc_html( $eloc['nombre'] ?? '—' ); ?></span>
							</div>
							<div class="ed-partido-card__score">
								<?php if ( in_array( (string) ( $p['estado'] ?? '' ), array( 'en_curso', 'finalizado', 'descanso' ), true ) ) : ?>
									<strong><?php echo (int) ( $p['goles_local'] ?? 0 ); ?> : <?php echo (int) ( $p['goles_visitante'] ?? 0 ); ?></strong>
								<?php else : ?>
									<span class="ed-partido-card__vs">vs</span>
								<?php endif; ?>
							</div>
							<div class="ed-partido-card__equipo">
								<?php if ( ! empty( $evis['escudo'] ) ) : ?>
									<img src="<?php echo esc_url( $evis['escudo'] ); ?>" alt="">
								<?php endif; ?>
								<span><?php echo esc_html( $evis['nombre'] ?? '—' ); ?></span>
							</div>
						</div>
						<div class="ed-partido-card__meta">
							<span class="ed-badge"><?php echo esc_html( (string) ( $p['estado'] ?? '' ) ); ?></span>
							<?php if ( ! empty( $p['fecha_hora'] ) ) : ?>
								<span class="ed-partido-card__fh"><?php echo esc_html( (string) $p['fecha_hora'] ); ?></span>
							<?php endif; ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php
	$proximos_fed    = ED_Sync_Federacion::get_proximos_partidos( 5 );
	$resultados_fed  = ED_Sync_Federacion::get_resultados_recientes( 8 );
	$nombre_club_tpl = (string) get_option( 'ed_nombre_club', 'Ondara' );
	?>
	<?php if ( ! empty( $proximos_fed ) ) : ?>
	<section class="ed-torneo__section ed-fed-section">
		<h2 class="ed-section__title"><?php esc_html_e( 'Pròxims partits de lliga (FFCV)', 'escuela-deportiva-core' ); ?></h2>
		<?php foreach ( $proximos_fed as $p ) : ?>
			<div class="ed-partido-fed-card">
				<div class="ed-partido-fed-card__cat"><?php echo esc_html( (string) ( $p->categoria_ffcv ?? '' ) ); ?></div>
				<div class="ed-partido-fed-card__equipos">
					<span class="<?php echo ( (int) $p->es_nuestro_equipo && false !== stripos( (string) $p->equipo_local, $nombre_club_tpl ) ) ? 'ed-equipo--nuestro' : ''; ?>">
						<?php echo esc_html( (string) $p->equipo_local ); ?>
					</span>
					<span class="ed-partido-fed-card__vs">vs</span>
					<span class="<?php echo ( (int) $p->es_nuestro_equipo && false !== stripos( (string) $p->equipo_visitante, $nombre_club_tpl ) ) ? 'ed-equipo--nuestro' : ''; ?>">
						<?php echo esc_html( (string) $p->equipo_visitante ); ?>
					</span>
				</div>
				<div class="ed-partido-fed-card__meta">
					<?php if ( ! empty( $p->fecha_hora ) ) : ?>
						<span><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( (string) $p->fecha_hora ) ) ); ?></span>
					<?php else : ?>
						<span>—</span>
					<?php endif; ?>
					<?php if ( ! empty( $p->campo ) ) : ?>
						<span class="ed-partido-fed-card__campo"><?php echo esc_html( (string) $p->campo ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>

	<?php if ( ! empty( $resultados_fed ) ) : ?>
	<section class="ed-torneo__section ed-fed-section">
		<h2 class="ed-section__title"><?php esc_html_e( 'Resultats recents (FFCV)', 'escuela-deportiva-core' ); ?></h2>
		<?php foreach ( $resultados_fed as $p ) : ?>
			<div class="ed-partido-fed-card ed-partido-fed-card--jugado">
				<div class="ed-partido-fed-card__cat"><?php echo esc_html( (string) ( $p->categoria_ffcv ?? '' ) ); ?></div>
				<div class="ed-partido-fed-card__equipos">
					<span><?php echo esc_html( (string) $p->equipo_local ); ?></span>
					<strong class="ed-partido-fed-card__score">
						<?php echo esc_html( (string) ( (int) $p->goles_local . ' - ' . (int) $p->goles_visitante ) ); ?>
					</strong>
					<span><?php echo esc_html( (string) $p->equipo_visitante ); ?></span>
				</div>
				<?php if ( ! empty( $p->fecha_hora ) ) : ?>
					<div class="ed-partido-fed-card__meta"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( (string) $p->fecha_hora ) ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>
</div>
