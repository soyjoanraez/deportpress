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
		add_filter( 'acf/fields/post_object/result', array( $this, 'append_deporte_to_categoria_title' ), 10, 4 );
		add_filter( 'acf/fields/relationship/result', array( $this, 'append_deporte_to_categoria_title' ), 10, 4 );
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
	 * Anys complits respecte al 31 de desembre de l'any en què comença la temporada.
	 * Així evitem que els jugadors canvien de categoria automàticament al gener.
	 */
	public static function calc_age_years( string $birth_ymd ): int {
		try {
			$birth_date = new DateTime( $birth_ymd );
			
			// 1. Intentar deduir de l'opció del sistema (ex: "2025-26" -> 2025)
			$temp = get_option( 'ed_temporada_actual', '' );
			$season_start_year = 0;
			
			if ( preg_match( '/^(\d{4})/', $temp, $matches ) ) {
				$season_start_year = (int) $matches[1];
			}
			
			// 2. Fallback heurístic si no està definit: les temporades solen començar al juliol/agost
			if ( 0 === $season_start_year ) {
				$current_month = (int) current_time( 'n' );
				$current_year  = (int) current_time( 'Y' );
				$season_start_year = $current_month >= 7 ? $current_year : $current_year - 1;
			}
			
			$dec_31 = new DateTime( $season_start_year . '-12-31' );
			return (int) $dec_31->diff( $birth_date )->y;
		} catch ( Exception $e ) {
			return -1;
		}
	}

	/**
	 * Afegeix el nom de l'esport al costat del nom de la categoria als dropdowns d'ACF.
	 * Així evitem que is vegen "4 Benjamí" iguals quan en realitat cadascun és d'un esport.
	 * 
	 * @param string  $text    El text de l'opció ACF.
	 * @param WP_Post $post    L'objecte post.
	 * @param array   $field   El camp ACF.
	 * @param int     $post_id Post ID on s'edita.
	 */
	public function append_deporte_to_categoria_title( $text, $post, $field, $post_id ) {
		if ( is_object( $post ) && 'categoria' === $post->post_type && function_exists( 'get_field' ) ) {
			$deporte_id = (int) get_field( 'ed_cat_deporte', $post->ID );
			if ( $deporte_id > 0 ) {
				$deporte_title = get_the_title( $deporte_id );
				if ( $deporte_title ) {
					$text .= ' (' . $deporte_title . ')';
				}
			}
		}
		return $text;
	}
}
