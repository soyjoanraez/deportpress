<?php
/**
 * Estat de cròniques IA per partits finalitzats.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

$partidos = get_posts(
	array(
		'post_type'      => 'partido',
		'posts_per_page' => 40,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'   => ED_Torneos::PAR_ESTADO,
				'value' => 'finalizado',
			),
		),
	)
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Cròniques IA', 'escuela-deportiva-core' ); ?></h1>
	<?php if ( isset( $_GET['mensaje'] ) && 'encolat' === $_GET['mensaje'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'S’ha afegit el partit a la cua de generació.', 'escuela-deportiva-core' ); ?></p></div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Partit', 'escuela-deportiva-core' ); ?></th>
				<th><?php esc_html_e( 'Estat crònica', 'escuela-deportiva-core' ); ?></th>
				<th><?php esc_html_e( 'Data partit', 'escuela-deportiva-core' ); ?></th>
				<th><?php esc_html_e( 'Accions', 'escuela-deportiva-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $partidos as $p ) : ?>
				<?php
				$pid          = (int) $p->ID;
				$cronica      = function_exists( 'get_field' ) ? (string) get_field( ED_Torneos::PAR_CRONICA, $pid, false ) : '';
				$tiene        = '' !== trim( wp_strip_all_tags( $cronica ) );
				$cronica_post = (int) get_post_meta( $pid, '_ed_cronica_post_id', true );
				$cola         = class_exists( 'ED_IA_Cronicas' ) ? ED_IA_Cronicas::get_estado_cola( $pid ) : null;
				$fh           = function_exists( 'get_field' ) ? (string) get_field( ED_Torneos::PAR_FECHA_HORA, $pid ) : '';
				?>
				<tr>
					<td><?php echo esc_html( get_the_title( $p ) ); ?></td>
					<td>
						<?php if ( $tiene ) : ?>
							<span class="dashicons dashicons-yes" style="color:green;" aria-hidden="true"></span>
							<?php esc_html_e( 'Generada', 'escuela-deportiva-core' ); ?>
							<?php if ( $cronica_post ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $cronica_post ) ); ?>"><?php esc_html_e( 'Editar post', 'escuela-deportiva-core' ); ?></a>
							<?php endif; ?>
						<?php elseif ( 'procesando' === $cola ) : ?>
							<span style="color:orange;"><?php esc_html_e( 'Processant…', 'escuela-deportiva-core' ); ?></span>
						<?php elseif ( 'error' === $cola ) : ?>
							<span style="color:red;"><?php esc_html_e( 'Error', 'escuela-deportiva-core' ); ?></span>
						<?php else : ?>
							<span style="color:#757575;"><?php esc_html_e( 'Sense crònica', 'escuela-deportiva-core' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $fh ); ?></td>
					<td>
						<?php if ( ! $tiene ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ed_generar_cronica&partido_id=' . $pid ), 'ed_cronica_' . $pid ) ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Generar ara', 'escuela-deportiva-core' ); ?></a>
						<?php else : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ed_generar_cronica&partido_id=' . $pid . '&force=1' ), 'ed_cronica_' . $pid ) ); ?>" class="button button-small"><?php esc_html_e( 'Regenerar', 'escuela-deportiva-core' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
