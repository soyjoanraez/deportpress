<?php
/**
 * Roles y capabilities personalizadas.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registro de roles DeportPress.
 */
class ED_Roles {

	public const ROLE_COORDINADOR = 'ed_coordinador';
	public const ROLE_ENTRENADOR  = 'ed_entrenador';
	public const ROLE_ADULTO      = 'ed_adulto';
	public const ROLE_OPERADOR    = 'ed_operador';

	/**
	 * Instala roles y asigna capabilities al administrador.
	 */
	public static function install(): void {
		self::register_custom_caps();
		self::add_roles();
		self::grant_admin_caps();
	}

	/**
	 * Registra caps a l'administrador només si falten (idempotent i eficient).
	 * S'executa a plugins_loaded, no a cada request sense raó.
	 */
	public static function init(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		$missing = array_filter(
			self::all_plugin_caps(),
			fn( string $cap ) => ! isset( $admin->capabilities[ $cap ] )
		);

		if ( ! empty( $missing ) ) {
			// Només escriu a BD si realment falten caps.
			foreach ( $missing as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Registra capabilities para map_meta_cap de CPTs (usada a activate/upgrade).
	 */
	private static function register_custom_caps(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		$caps = self::all_plugin_caps();
		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	/**
	 * Lista de capabilities gestionadas por el plugin.
	 *
	 * @return string[]
	 */
	public static function all_plugin_caps(): array {
		$caps = array();
		foreach ( self::capability_pairs() as $pair ) {
			$s = $pair[0];
			$p = $pair[1];
			$caps[] = "edit_{$s}";
			$caps[] = "read_{$s}";
			$caps[] = "delete_{$s}";
			$caps[] = "edit_{$p}";
			$caps[] = "edit_others_{$p}";
			$caps[] = "publish_{$p}";
			$caps[] = "read_private_{$p}";
			$caps[] = "delete_{$p}";
			$caps[] = "delete_others_{$p}";
			$caps[] = "delete_private_{$p}";
			$caps[] = "delete_published_{$p}";
			$caps[] = "edit_private_{$p}";
			$caps[] = "edit_published_{$p}";
		}
		$caps[] = 'manage_escuela_deportiva';
		return array_unique( $caps );
	}

	/**
	 * Pares singular / plural para capability_type de CPTs.
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function capability_pairs(): array {
		return array(
			array( 'jugador', 'jugadores' ),
			array( 'nucleo_familiar', 'nucleo_familiars' ),
			array( 'deporte', 'deportes' ),
			array( 'categoria', 'categorias' ),
			array( 'equipo', 'equipos' ),
			array( 'entrenamiento', 'entrenamientos' ),
			array( 'torneo', 'torneos' ),
			array( 'partido', 'partidos' ),
			array( 'anuncio', 'anuncios' ),
			array( 'empresa_directorio', 'empresa_directorios' ),
		);
	}

	/**
	 * Caps Fase 5 (anuncis / directori) per a rols existents.
	 */
	public static function upgrade_fase5_caps_for_existing_roles(): void {
		$f5_caps = array(
			'edit_anuncios',
			'edit_others_anuncios',
			'read_anuncio',
			'read_private_anuncios',
			'publish_anuncios',
			'edit_empresa_directorios',
			'edit_others_empresa_directorios',
			'read_empresa_directorio',
			'read_private_empresa_directorios',
			'publish_empresa_directorios',
		);
		$coord = get_role( self::ROLE_COORDINADOR );
		if ( $coord ) {
			foreach ( $f5_caps as $c ) {
				$coord->add_cap( $c );
			}
		}
	}

	/**
	 * Caps torneo/partit i operador (Fase 3–4) en rols existents.
	 */
	public static function upgrade_fase34_caps_for_existing_roles(): void {
		$torneo_caps = array(
			'edit_torneos',
			'edit_others_torneos',
			'read_torneo',
			'read_private_torneos',
			'publish_torneos',
		);
		$partido_caps_coord = array(
			'edit_partidos',
			'edit_others_partidos',
			'read_partido',
			'read_private_partidos',
			'publish_partidos',
		);
		$partido_caps_op = array(
			'edit_partidos',
			'read_partido',
			'read_private_partidos',
			'edit_published_partidos',
		);
		$coord = get_role( self::ROLE_COORDINADOR );
		if ( $coord ) {
			foreach ( array_merge( $torneo_caps, $partido_caps_coord ) as $c ) {
				$coord->add_cap( $c );
			}
		}
		$op = get_role( self::ROLE_OPERADOR );
		if ( $op ) {
			foreach ( $partido_caps_op as $c ) {
				$op->add_cap( $c );
			}
		}
	}

	/**
	 * Afegeix caps del CPT entrenament a rols ja existents (actualització Fase 2).
	 */
	public static function upgrade_entrenamiento_caps_for_existing_roles(): void {
		$ent_caps = array(
			'edit_entrenamientos',
			'edit_others_entrenamientos',
			'read_entrenamiento',
			'read_private_entrenamientos',
			'publish_entrenamientos',
		);
		$coord = get_role( self::ROLE_COORDINADOR );
		if ( $coord ) {
			foreach ( $ent_caps as $c ) {
				$coord->add_cap( $c );
			}
		}
		$ent = get_role( self::ROLE_ENTRENADOR );
		if ( $ent ) {
			foreach ( $ent_caps as $c ) {
				$ent->add_cap( $c );
			}
		}
	}

	/**
	 * Crea roles del sistema.
	 */
	private static function add_roles(): void {
		$subscriber_caps = array( 'read' );

		if ( ! get_role( self::ROLE_COORDINADOR ) ) {
			add_role(
				self::ROLE_COORDINADOR,
				__( 'Coordinador de Deporte', 'escuela-deportiva-core' ),
				array_merge(
					$subscriber_caps,
					array(
						'edit_deportes'           => true,
						'edit_others_deportes'    => true,
						'read_deporte'            => true,
						'edit_categorias'         => true,
						'edit_others_categorias'  => true,
						'read_categoria'          => true,
						'edit_equipos'            => true,
						'edit_others_equipos'     => true,
						'read_equipo'             => true,
						'edit_jugadores'          => true,
						'edit_others_jugadores'   => true,
						'read_jugador'            => true,
						'read_private_jugadores'  => true,
						'edit_nucleo_familiars'   => true,
						'read_nucleo_familiar'    => true,
						'manage_escuela_deportiva' => true,
						'edit_entrenamientos'     => true,
						'edit_others_entrenamientos' => true,
						'read_entrenamiento'      => true,
						'read_private_entrenamientos' => true,
						'publish_entrenamientos'  => true,
						'edit_torneos'            => true,
						'edit_others_torneos'     => true,
						'read_torneo'             => true,
						'read_private_torneos'    => true,
						'publish_torneos'         => true,
						'edit_partidos'           => true,
						'edit_others_partidos'    => true,
						'read_partido'            => true,
						'read_private_partidos'   => true,
						'publish_partidos'        => true,
						'edit_anuncios'           => true,
						'edit_others_anuncios'    => true,
						'read_anuncio'            => true,
						'read_private_anuncios'  => true,
						'publish_anuncios'        => true,
						'edit_empresa_directorios' => true,
						'edit_others_empresa_directorios' => true,
						'read_empresa_directorio' => true,
						'read_private_empresa_directorios' => true,
						'publish_empresa_directorios' => true,
					)
				)
			);
		}

		if ( ! get_role( self::ROLE_ENTRENADOR ) ) {
			add_role(
				self::ROLE_ENTRENADOR,
				__( 'Entrenador', 'escuela-deportiva-core' ),
				array_merge(
					$subscriber_caps,
					array(
						'edit_jugadores'         => true,
						'read_jugador'           => true,
						'read_private_jugadores' => true,
						'read_equipo'            => true,
						'read_categoria'         => true,
						'edit_entrenamientos'    => true,
						'edit_others_entrenamientos' => true,
						'read_entrenamiento'     => true,
						'read_private_entrenamientos' => true,
						'publish_entrenamientos' => true,
					)
				)
			);
		}

		if ( ! get_role( self::ROLE_ADULTO ) ) {
			add_role(
				self::ROLE_ADULTO,
				__( 'Adulto Responsable', 'escuela-deportiva-core' ),
				array_merge(
					$subscriber_caps,
					array(
						'read_jugador'          => true,
						'read_nucleo_familiar'  => true,
						'read_nucleo_familiars' => true,
					)
				)
			);
		}

		if ( ! get_role( self::ROLE_OPERADOR ) ) {
			add_role(
				self::ROLE_OPERADOR,
				__( 'Operador de Partido', 'escuela-deportiva-core' ),
				array_merge(
					$subscriber_caps,
					array(
						'edit_partidos'         => true,
						'read_partido'          => true,
						'read_private_partidos' => true,
						'edit_published_partidos' => true,
					)
				)
			);
		}
	}

	/**
	 * Asegura que el administrador tenga todas las caps del plugin.
	 */
	private static function grant_admin_caps(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		$admin->add_cap( 'manage_escuela_deportiva' );
	}
}
