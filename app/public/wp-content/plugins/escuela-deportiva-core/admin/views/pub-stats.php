<?php
/**
 * Estadístiques bàsiques d’anuncis (últims 30 dies).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

$anuncios = get_posts(
	array(
		'post_type'      => 'anuncio',
		'post_status'    => 'any',
		'posts_per_page' => 200,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Estadístiques de publicitat', 'escuela-deportiva-core' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Impressions i clics (últims 30 dies, per anunci).', 'escuela-deportiva-core' ); ?></p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Anunci', 'escuela-deportiva-core' ); ?></th>
				<th><?php esc_html_e( 'Impressions', 'escuela-deportiva-core' ); ?></th>
				<th><?php esc_html_e( 'Clics', 'escuela-deportiva-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $anuncios as $p ) : ?>
				<?php
				$imp = 0;
				$clk = 0;
				foreach ( ED_Publicidad::get_estadisticas( (int) $p->ID, 30 ) as $row ) {
					if ( 'impresion' === $row->tipo ) {
						$imp += (int) $row->total;
					}
					if ( 'click' === $row->tipo ) {
						$clk += (int) $row->total;
					}
				}
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
						<span class="description">(ID <?php echo (int) $p->ID; ?>)</span>
					</td>
					<td><?php echo (int) $imp; ?></td>
					<td><?php echo (int) $clk; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
