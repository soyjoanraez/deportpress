<?php
/**
 * Fitxa pública d’empresa al directori.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	$empresa = ED_Directorio::format_empresa( get_post() );
	?>
<div class="ed-empresa-ficha">

	<div class="ed-empresa-ficha__header">
		<?php if ( ! empty( $empresa['logo'] ) ) : ?>
			<img class="ed-empresa-ficha__logo" src="<?php echo esc_url( $empresa['logo'] ); ?>" alt="<?php echo esc_attr( $empresa['nombre'] ); ?>" width="100" height="100" loading="eager">
		<?php endif; ?>
		<div>
			<h1 class="ed-empresa-ficha__nombre"><?php echo esc_html( $empresa['nombre'] ); ?></h1>
			<?php foreach ( $empresa['categorias'] as $cat ) : ?>
				<span class="ed-chip"><?php echo esc_html( $cat['nombre'] ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( ! empty( $empresa['descripcion'] ) ) : ?>
		<div class="ed-empresa-ficha__desc"><?php echo wp_kses_post( wpautop( $empresa['descripcion'] ) ); ?></div>
	<?php endif; ?>

	<div class="ed-empresa-ficha__nap">
		<?php if ( ! empty( $empresa['direccion'] ) ) : ?>
			<div class="ed-nap-item">📍 <?php echo esc_html( $empresa['direccion'] ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $empresa['telefono'] ) ) : ?>
			<div class="ed-nap-item">📞 <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $empresa['telefono'] ) ); ?>"><?php echo esc_html( $empresa['telefono'] ); ?></a></div>
		<?php endif; ?>
		<?php if ( ! empty( $empresa['email'] ) ) : ?>
			<div class="ed-nap-item">✉️ <a href="mailto:<?php echo esc_attr( $empresa['email'] ); ?>"><?php echo esc_html( $empresa['email'] ); ?></a></div>
		<?php endif; ?>
		<?php if ( ! empty( $empresa['web'] ) ) : ?>
			<div class="ed-nap-item">🌐 <a href="<?php echo esc_url( $empresa['web'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) wp_parse_url( $empresa['web'], PHP_URL_HOST ) ); ?></a></div>
		<?php endif; ?>
		<?php if ( ! empty( $empresa['horario'] ) ) : ?>
			<div class="ed-nap-item">🕐 <?php echo esc_html( $empresa['horario'] ); ?></div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $empresa['fotos'] ) ) : ?>
	<div class="ed-empresa-ficha__fotos">
		<?php foreach ( array_slice( $empresa['fotos'], 0, 6 ) as $foto ) : ?>
			<img src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( $empresa['nombre'] ); ?>" loading="lazy">
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'empresa_directorio' ) ?: home_url( '/' ) ); ?>" class="ed-chip">
			← <?php esc_html_e( 'Tornar al directori', 'escuela-deportiva-core' ); ?>
		</a>
	</p>
</div>
	<?php
}

get_footer();
