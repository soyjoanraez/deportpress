<?php
/**
 * Arxiu del directori d’empreses / taxonomia categoria_empresa.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

get_header();

$categorias = ED_Directorio::get_categorias();
$cat_actual = null;
if ( is_tax( 'categoria_empresa' ) ) {
	$term = get_queried_object();
	if ( $term instanceof WP_Term ) {
		$cat_actual = $term->slug;
	}
}

$empresas = ED_Directorio::get_empresas( $cat_actual, 48, 0 );
$arch_url = get_post_type_archive_link( 'empresa_directorio' );
$total    = array_sum( array_column( $categorias, 'count' ) );
?>
<div class="ed-directorio">

	<?php echo ED_Publicidad::render( 'directorio_top', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<h1 class="ed-directorio__titulo"><?php esc_html_e( 'Directori d’empreses', 'escuela-deportiva-core' ); ?></h1>
	<p class="ed-directorio__subtitulo"><?php esc_html_e( 'Empreses i negocis que donen suport al club.', 'escuela-deportiva-core' ); ?></p>

	<?php if ( ! empty( $categorias ) ) : ?>
	<div class="ed-directorio__filtros">
		<a href="<?php echo esc_url( $arch_url ?: home_url( '/' ) ); ?>" class="ed-chip <?php echo $cat_actual ? '' : 'ed-chip--activo'; ?>">
			<?php esc_html_e( 'Totes', 'escuela-deportiva-core' ); ?> (<?php echo (int) $total; ?>)
		</a>
		<?php foreach ( $categorias as $cat ) : ?>
			<?php
			$term_link = '';
			$term_obj  = get_term_by( 'slug', $cat['slug'], 'categoria_empresa' );
			if ( $term_obj instanceof WP_Term ) {
				$tlink = get_term_link( $term_obj );
				if ( ! is_wp_error( $tlink ) ) {
					$term_link = $tlink;
				}
			}
			?>
			<a href="<?php echo esc_url( $term_link ?: '#' ); ?>" class="ed-chip <?php echo $cat_actual === $cat['slug'] ? 'ed-chip--activo' : ''; ?>">
				<?php echo esc_html( $cat['nombre'] ); ?> (<?php echo (int) $cat['count']; ?>)
			</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="ed-directorio__grid">
		<?php foreach ( $empresas as $empresa ) : ?>
			<a href="<?php echo esc_url( $empresa['url'] ); ?>" class="ed-empresa-card <?php echo ! empty( $empresa['destacada'] ) ? 'ed-empresa-card--destacada' : ''; ?>">
				<div class="ed-empresa-card__logo">
					<?php if ( ! empty( $empresa['logo'] ) ) : ?>
						<img src="<?php echo esc_url( $empresa['logo'] ); ?>" alt="" loading="lazy" width="56" height="56">
					<?php else : ?>
						<span class="ed-empresa-card__avatar"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $empresa['nombre'], 0, 1 ) : substr( $empresa['nombre'], 0, 1 ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="ed-empresa-card__info">
					<strong class="ed-empresa-card__nombre"><?php echo esc_html( $empresa['nombre'] ); ?></strong>
					<?php if ( ! empty( $empresa['categorias'][0]['nombre'] ) ) : ?>
						<span class="ed-empresa-card__cat"><?php echo esc_html( $empresa['categorias'][0]['nombre'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $empresa['telefono'] ) ) : ?>
						<span class="ed-empresa-card__tel"><?php echo esc_html( $empresa['telefono'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $empresa['destacada'] ) ) : ?>
					<span class="ed-empresa-card__badge">⭐ <?php esc_html_e( 'Destacada', 'escuela-deportiva-core' ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php
get_footer();
