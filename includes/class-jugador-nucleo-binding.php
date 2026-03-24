<?php
/**
 * Vincula el jugador al nucli familiar de l’adult que el dóna d’alta.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Garanteix `ed_jugador_nucleo` quan un pare/tutor (no staff) guarda el fill.
 */
class ED_Jugador_Nucleo_Binding {

	/**
	 * Evita reentrada si update_field dispara altres hooks.
	 *
	 * @var bool
	 */
	private static $running = false;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'save_post_jugador', array( __CLASS__, 'on_save_jugador' ), 100, 3 );
	}

	/**
	 * Després que ACF haja guardat (prioritat alta), assigna el nucli del tutor.
	 *
	 * @param int      $post_id ID del jugador.
	 * @param WP_Post  $post    Post.
	 * @param bool     $update  Actualització.
	 */
	public static function on_save_jugador( int $post_id, $post, bool $update ): void {
		if ( self::$running ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return;
		}

		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}

		if ( user_can( $uid, 'manage_options' ) || user_can( $uid, 'manage_escuela_deportiva' ) ) {
			return;
		}

		$nucleo = ED_Nucleo_Repository::find_for_user( $uid );
		if ( ! $nucleo instanceof WP_Post ) {
			return;
		}

		$nucleo_id = (int) $nucleo->ID;
		if ( $nucleo_id <= 0 ) {
			return;
		}

		// Entrenador sense rol de coordinació: pot editar molts jugadors; només vincular si és l’autor del jugador (alta pròpia del fill).
		if ( user_can( $uid, 'edit_entrenamientos' ) && ! user_can( $uid, 'manage_escuela_deportiva' ) ) {
			if ( (int) $post->post_author !== $uid ) {
				return;
			}
		}

		$current = (int) get_field( 'ed_jugador_nucleo', $post_id, false );
		if ( $current === $nucleo_id ) {
			return;
		}

		// Un altre nucli ja assignat: no trencar dades; només coordinació/admin pot corregir.
		if ( $current > 0 && $current !== $nucleo_id ) {
			return;
		}

		self::$running = true;
		update_field( 'field_ed_jug_nucleo', $nucleo_id, $post_id );
		self::$running = false;
	}
}
