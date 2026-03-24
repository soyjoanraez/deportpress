<?php
/**
 * Assignació automàtica de categoria per edat.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Si el camp categoria del jugador ja té valor, no es sobreescriu.
 */
class ED_Categoria_Manager {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'save_post_jugador', array( $this, 'maybe_assign_categoria' ), 25, 3 );
	}

	/**
	 * Assigna categoria segons edat i esport si el camp està buit.
	 *
	 * @param int      $post_id ID del jugador.
	 * @param WP_Post  $post    Post.
	 * @param bool     $update  Si és actualització.
	 */
	public function maybe_assign_categoria( int $post_id, $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return;
		}

		$existing = get_field( 'ed_jugador_categoria', $post_id );
		if ( ! empty( $existing ) ) {
			return;
		}

		$deporte_id = (int) get_field( 'ed_jugador_deporte', $post_id );
		$fnac       = get_field( 'ed_jugador_fecha_nacimiento', $post_id );
		if ( $deporte_id <= 0 || empty( $fnac ) ) {
			return;
		}

		$age = self::calc_age_years( (string) $fnac );
		if ( $age < 0 ) {
			return;
		}

		$cats = get_posts(
			array(
				'post_type'      => 'categoria',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => 'ed_cat_deporte',
						'value' => $deporte_id,
					),
				),
			)
		);

		foreach ( $cats as $cat ) {
			$min = (int) get_field( 'ed_cat_edad_min', $cat->ID );
			$max = (int) get_field( 'ed_cat_edad_max', $cat->ID );
			if ( $age >= $min && $age <= $max ) {
				update_field( 'ed_jugador_categoria', $cat->ID, $post_id );
				break;
			}
		}
	}

	/**
	 * Anys complits respecte a avui.
	 */
	public static function calc_age_years( string $birth_ymd ): int {
		try {
			$b = new DateTime( $birth_ymd );
			$n = new DateTime( 'today' );
			return (int) $n->diff( $b )->y;
		} catch ( Exception $e ) {
			return -1;
		}
	}
}
