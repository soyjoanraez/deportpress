<?php
/**
 * Cerca de nucli familiar per usuari adult.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Consultes sobre nucleo_familiar.
 */
class ED_Nucleo_Repository {

	/**
	 * Troba el nucli on l'usuari és adult responsable.
	 *
	 * Optimitzat: cerca directa per meta + caché estàtica per evitar N+1 queries.
	 * L'ACF user field emmagatzema l'ID com a enter en sèrie (i:N;) o valor simple.
	 */
	public static function find_for_user( int $user_id ): ?WP_Post {
		if ( $user_id <= 0 ) {
			return null;
		}

		// Caché estàtica per request: evita múltiples crides al mateix user en un sol request.
		static $cache = array();
		if ( array_key_exists( $user_id, $cache ) ) {
			return $cache[ $user_id ];
		}

		global $wpdb;

		// Consulta directa a postmeta per trobar el nucli sense carregar tots els posts.
		// ACF user field pot emmagatzemar com a valor simple (= $user_id)
		// o dins d'un array serialitzat (;i:USER_ID; o ;s:N:"USER_ID";).
		$nucleo_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = 'ed_nucleo_adultos'
				AND p.post_type = 'nucleo_familiar'
				AND p.post_status IN ('publish','draft','private')
				AND (
					pm.meta_value = %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
				)
				LIMIT 1",
				(string) $user_id,
				"%i:{$user_id};%",
				"%\"{$user_id}\"%"
			)
		);

		$result = $nucleo_id ? get_post( (int) $nucleo_id ) : null;

		// Verificació de seguretat: confirma amb ACF per garantir coherència.
		// Si el LIKE retorna un fals positiu, la verificació ACF el descarta.
		if ( $result && ! self::user_in_nucleo( $user_id, $result->ID ) ) {
			$result = null;
		}

		// Fallback: si la cerca SQL no ha retornat res (possible si ACF usa un format
		// de serialització no cobert pels patrons LIKE), recórrer tots els nucleos.
		// Garanteix que cap usuari perdi accés per un canvi de format d'emmagatzematge.
		if ( null === $result ) {
			$posts = get_posts(
				array(
					'post_type'      => 'nucleo_familiar',
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				)
			);
			foreach ( $posts as $p ) {
				if ( self::user_in_nucleo( $user_id, $p->ID ) ) {
					$result = $p;
					break;
				}
			}
		}

		$cache[ $user_id ] = $result;
		return $result;
	}

	/**
	 * Comprova si l'usuari està entre els adults del nucli.
	 */
	public static function user_in_nucleo( int $user_id, int $nucleo_id ): bool {
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}
		$users = get_field( 'ed_nucleo_adultos', $nucleo_id );
		if ( empty( $users ) ) {
			return false;
		}
		if ( ! is_array( $users ) ) {
			$users = array( $users );
		}
		$users = array_map( 'intval', $users );
		return in_array( $user_id, $users, true );
	}

	/**
	 * IDs de jugadors vinculats al nucli (camp ACF al jugador).
	 *
	 * @return int[]
	 */
	public static function get_jugador_ids_for_nucleo( int $nucleo_id ): array {
		$q = new WP_Query(
			array(
				'post_type'      => 'jugador',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => 'ed_jugador_nucleo',
						'value' => $nucleo_id,
					),
				),
			)
		);
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Comprova si el jugador pertany al nucli.
	 */
	public static function jugador_belongs_to_nucleo( int $jugador_id, int $nucleo_id ): bool {
		if ( ! function_exists( 'get_field' ) ) {
			return (int) get_post_meta( $jugador_id, 'ed_jugador_nucleo', true ) === $nucleo_id;
		}
		return (int) get_field( 'ed_jugador_nucleo', $jugador_id ) === $nucleo_id;
	}

	/**
	 * Primer usuari adult del nucli (per comandes WooCommerce / correu).
	 */
	public static function get_first_adult_user_id( int $nucleo_id ): int {
		if ( ! function_exists( 'get_field' ) || $nucleo_id <= 0 ) {
			return 0;
		}
		$users = get_field( 'ed_nucleo_adultos', $nucleo_id );
		if ( empty( $users ) ) {
			return 0;
		}
		if ( ! is_array( $users ) ) {
			return (int) $users;
		}
		return (int) reset( $users );
	}

	/**
	 * Comprova que el jugador pertany al nucli i a l'esport indicat.
	 */
	public static function jugador_matches_nucleo_and_deporte( int $jugador_id, int $nucleo_id, int $deporte_id ): bool {
		if ( ! self::jugador_belongs_to_nucleo( $jugador_id, $nucleo_id ) ) {
			return false;
		}
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}
		return (int) get_field( 'ed_jugador_deporte', $jugador_id ) === $deporte_id;
	}
}
