<?php
/**
 * Integració FFCV / Novanet (Fase 7).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
	wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
}

global $wpdb;

$config      = ED_Sync_Federacion::get_config();
$ultima_sync = get_option( 'ed_ffcv_ultima_sync' );
$nom_club    = (string) get_option( 'ed_nombre_club', 'Ondara' );
$temporada   = (string) get_option( 'ed_temporada_actual', '2025-26' );
$categorias_json = wp_json_encode( $config['categorias'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

$tabla_mapeo = $wpdb->prefix . 'ed_fed_mapeo';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$mapeos      = $wpdb->get_results( "SELECT * FROM {$tabla_mapeo} ORDER BY categoria_ffcv ASC" );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Integració FFCV (Novanet)', 'escuela-deportiva-core' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuració desada.', 'escuela-deportiva-core' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sincronització completada.', 'escuela-deportiva-core' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['cache'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Memòria cau del scraper buidada.', 'escuela-deportiva-core' ); ?></p></div>
	<?php endif; ?>

	<div class="card" style="max-width:920px;margin-top:16px;">
		<h2><?php esc_html_e( 'Estat', 'escuela-deportiva-core' ); ?></h2>
		<p>
			<?php esc_html_e( 'Sincronització activa:', 'escuela-deportiva-core' ); ?>
			<strong><?php echo ! empty( $config['activa'] ) ? esc_html__( 'Sí', 'escuela-deportiva-core' ) : esc_html__( 'No', 'escuela-deportiva-core' ); ?></strong><br>
			<?php esc_html_e( 'Última sincronització:', 'escuela-deportiva-core' ); ?>
			<strong><?php echo $ultima_sync ? esc_html( (string) $ultima_sync ) : esc_html__( 'Mai', 'escuela-deportiva-core' ); ?></strong><br>
			<?php esc_html_e( 'Categories configurades:', 'escuela-deportiva-core' ); ?>
			<strong><?php echo esc_html( (string) count( $config['categorias'] ) ); ?></strong>
		</p>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ed_ffcv_sync_manual' ), 'ed_ffcv_sync' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Sincronitzar ara', 'escuela-deportiva-core' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ed_ffcv_limpiar_cache' ), 'ed_ffcv_cache' ) ); ?>" class="button">
				<?php esc_html_e( 'Buidar memòria cau del scraper', 'escuela-deportiva-core' ); ?>
			</a>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:920px;margin-top:20px;">
		<?php wp_nonce_field( 'ed_federacion_guardar' ); ?>
		<input type="hidden" name="action" value="ed_federacion_guardar">

		<div class="card">
			<h2><?php esc_html_e( 'Opcions generals', 'escuela-deportiva-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ed_nombre_club"><?php esc_html_e( 'Nom del club (com apareix a FFCV)', 'escuela-deportiva-core' ); ?></label></th>
					<td><input name="ed_nombre_club" id="ed_nombre_club" type="text" class="regular-text" value="<?php echo esc_attr( $nom_club ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ed_temporada_actual"><?php esc_html_e( 'Temporada activa', 'escuela-deportiva-core' ); ?></label></th>
					<td><input name="ed_temporada_actual" id="ed_temporada_actual" type="text" class="regular-text" value="<?php echo esc_attr( $temporada ); ?>" placeholder="2025-26"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sincronització automàtica', 'escuela-deportiva-core' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ed_ffcv_activa" value="1" <?php checked( ! empty( $config['activa'] ) ); ?>>
							<?php esc_html_e( 'Activar cron (cada 6 h)', 'escuela-deportiva-core' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ed_ffcv_categorias_json"><?php esc_html_e( 'Categories a sincronitzar (JSON)', 'escuela-deportiva-core' ); ?></label></th>
					<td>
						<textarea name="ed_ffcv_categorias_json" id="ed_ffcv_categorias_json" rows="14" class="large-text code"><?php echo esc_textarea( $categorias_json ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Array d’objectes amb: nombre, competicion_id, grupo_id (opcional), competicion_nombre (opcional), categoria_local (ID post categoria local, opcional).', 'escuela-deportiva-core' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Desar configuració', 'escuela-deportiva-core' ) ); ?>
		</div>
	</form>

	<div class="card" style="max-width:920px;margin-top:20px;">
		<h2><?php esc_html_e( 'Mapatge categories FFCV → categoria local', 'escuela-deportiva-core' ); ?></h2>
		<p class="description"><?php esc_html_e( 'El camp competició buit aplica a totes les competicions per aquest nom FFCV.', 'escuela-deportiva-core' ); ?></p>

		<table class="widefat striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Categoria FFCV', 'escuela-deportiva-core' ); ?></th>
					<th><?php esc_html_e( 'Competició', 'escuela-deportiva-core' ); ?></th>
					<th><?php esc_html_e( 'ID categoria local', 'escuela-deportiva-core' ); ?></th>
					<th><?php esc_html_e( 'Accions', 'escuela-deportiva-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $mapeos ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Sense mapes.', 'escuela-deportiva-core' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $mapeos as $m ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $m->categoria_ffcv ); ?></td>
					<td><?php echo esc_html( (string) $m->competicion ); ?></td>
					<td><?php echo esc_html( (string) (int) $m->categoria_id ); ?></td>
					<td>
						<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ed_federacion_mapeo_borrar', 'id' => (int) $m->id ), admin_url( 'admin-post.php' ) ), 'ed_federacion_mapeo_borrar' ) ); ?>">
							<?php esc_html_e( 'Eliminar', 'escuela-deportiva-core' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
			<?php wp_nonce_field( 'ed_federacion_mapeo_add' ); ?>
			<input type="hidden" name="action" value="ed_federacion_mapeo_add">
			<p>
				<input type="text" name="categoria_ffcv" class="regular-text" placeholder="<?php esc_attr_e( 'Nom categoria FFCV', 'escuela-deportiva-core' ); ?>" required>
				<input type="text" name="competicion" class="regular-text" placeholder="<?php esc_attr_e( 'Competició (opcional)', 'escuela-deportiva-core' ); ?>">
				<input type="number" name="categoria_id" min="1" class="small-text" placeholder="ID" required>
				<?php submit_button( __( 'Afegir mapa', 'escuela-deportiva-core' ), 'secondary', 'submit', false ); ?>
			</p>
		</form>
	</div>
</div>
