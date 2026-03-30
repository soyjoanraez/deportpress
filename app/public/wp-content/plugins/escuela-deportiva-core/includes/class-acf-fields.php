<?php
/**
 * Grups de camps ACF locals (versionats en codi).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Camps ACF per a Fase 1.
 */
class ED_ACF_Fields {

	/**
	 * Hooks.
	 */
	public function register(): void {
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			add_action( 'acf/init', array( $this, 'register_field_groups' ), 5 );
			add_action( 'acf/init', array( $this, 'register_field_filters' ), 15 );
			add_action( 'acf/input/admin_enqueue_scripts', array( $this, 'enqueue_acf_dynamic_js' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
		}
	}

	/**
	 * Filtres ACF (p. ex. categories només de l’esport triat).
	 */
	public function register_field_filters(): void {
		add_filter( 'acf/fields/post_object/query/name=ed_jugador_categoria', array( $this, 'filter_post_object_categoria_by_deporte' ), 10, 3 );
		add_filter( 'acf/fields/post_object/query/name=ed_eq_categoria', array( $this, 'filter_post_object_categoria_by_deporte' ), 10, 3 );
		add_filter( 'acf/prepare_field', array( $this, 'filter_sport_specific_field_visibility' ), 20 );
	}

	/**
	 * Limita les categories del desplegable a les vinculades al mateix esport.
	 *
	 * @param array<string,mixed> $args    Arguments WP_Query.
	 * @param array<string,mixed> $field   Definició del camp ACF.
	 * @param int|string          $post_id ID del post que s’edita.
	 * @return array<string,mixed>
	 */
	public function filter_post_object_categoria_by_deporte( array $args, array $field, $post_id ): array {
		$dep = 0;
		if ( isset( $_POST['ed_ajax_deporte_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$dep = (int) wp_unslash( $_POST['ed_ajax_deporte_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';
			if ( 'ed_jugador_categoria' === $name ) {
				$dep = function_exists( 'get_field' ) ? (int) get_field( 'ed_jugador_deporte', $post_id, false ) : 0;
			} elseif ( 'ed_eq_categoria' === $name ) {
				$dep = function_exists( 'get_field' ) ? (int) get_field( 'ed_eq_deporte', $post_id, false ) : 0;
			}
		}

		if ( $dep > 0 ) {
			$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
			$args['meta_query'][] = array(
				'key'   => 'ed_cat_deporte',
				'value' => $dep,
			);
		} elseif ( isset( $_POST['ed_ajax_deporte_id'] ) ) { // phpcs:ignore
			// L'usuari encara no ha seleccionat cap esport en actiu, no mostrem cap categoria a l'instant
			$args['post__in'] = array( 0 );
		}
		
		return $args;
	}

	/**
	 * Avís si ACF no està actiu.
	 */
	public function acf_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Escuela Deportiva Core necessita Advanced Custom Fields (ACF) per als camps del domini.', 'escuela-deportiva-core' );
		echo '</p></div>';
	}

	/**
	 * Registra tots els grups.
	 */
	public function register_field_groups(): void {
		$this->register_jugador();
		$this->register_deporte();
		$this->register_categoria();
		$this->register_equipo();
		$this->register_nucleo();
		$this->register_entrenamiento();
		$this->register_torneo_partido();
		$this->register_fase5_anuncio();
		$this->register_fase5_empresa_directorio();
		$this->register_sport_specific_groups();
	}

	/**
	 * Mostra camps només per al deporte corresponent del registre actual.
	 *
	 * @param array<string,mixed> $field Definició del camp ACF.
	 * @return array<string,mixed>|false
	 */
	public function filter_sport_specific_field_visibility( array $field ) {
		if ( empty( $field['ed_sport_keys'] ) || ! is_array( $field['ed_sport_keys'] ) ) {
			return $field;
		}

		$context   = isset( $field['ed_sport_context'] ) ? (string) $field['ed_sport_context'] : '';
		$post_id   = $this->resolve_current_acf_post_id();
		$sport_key = $this->get_sport_key_for_context( $post_id, $context );

		if ( 'jugador' === $context || 'equipo' === $context ) {
			$classes = ' ed-sport-specific-field';
			foreach ( $field['ed_sport_keys'] as $sk ) {
				$classes .= ' ed-sport-keys-' . $sk;
			}
			$field['wrapper']['class'] .= $classes;
			
			if ( ! $sport_key || ! in_array( $sport_key, $field['ed_sport_keys'], true ) ) {
				$field['wrapper']['style'] = ( isset( $field['wrapper']['style'] ) ? $field['wrapper']['style'] : '' ) . ' display:none;';
			}
			return $field;
		}

		if ( ! $sport_key || ! in_array( $sport_key, $field['ed_sport_keys'], true ) ) {
			return false;
		}

		return $field;
	}

	/**
	 * Obté el post ID actual des de l'editor ACF / WP.
	 */
	private function resolve_current_acf_post_id(): int {
		if ( function_exists( 'acf_get_form_data' ) ) {
			$post_id = acf_get_form_data( 'post_id' );
			if ( is_numeric( $post_id ) ) {
				return (int) $post_id;
			}
		}

		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		global $post;
		return ( $post instanceof WP_Post ) ? (int) $post->ID : 0;
	}

	/**
	 * Resol la clau normalitzada del deporte per al context actual.
	 */
	private function get_sport_key_for_context( int $post_id, string $context ): ?string {
		if ( $post_id <= 0 || ! function_exists( 'get_field' ) ) {
			return null;
		}

		$deporte_id = 0;
		switch ( $context ) {
			case 'jugador':
				$deporte_id = (int) get_field( 'ed_jugador_deporte', $post_id, false );
				break;
			case 'equipo':
				$deporte_id = (int) get_field( 'ed_eq_deporte', $post_id, false );
				break;
			case 'entrenamiento':
				$categoria_id = (int) get_field( 'ed_ent_categoria', $post_id, false );
				if ( $categoria_id > 0 ) {
					$deporte_id = (int) get_field( 'ed_cat_deporte', $categoria_id, false );
				}
				if ( $deporte_id <= 0 ) {
					$equipo_id   = (int) get_field( 'ed_ent_equipo', $post_id, false );
					$deporte_id = $equipo_id > 0 ? (int) get_field( 'ed_eq_deporte', $equipo_id, false ) : 0;
				}
				break;
			case 'torneo':
				$deporte_id = (int) get_field( 'ed_tor_deporte', $post_id, false );
				break;
			case 'partido':
				$torneo_id = (int) get_field( 'ed_par_torneo', $post_id, false );
				if ( $torneo_id > 0 ) {
					$deporte_id = (int) get_field( 'ed_tor_deporte', $torneo_id, false );
				}
				if ( $deporte_id <= 0 ) {
					$equipo_id   = (int) get_field( 'ed_par_equipo_local', $post_id, false );
					$deporte_id = $equipo_id > 0 ? (int) get_field( 'ed_eq_deporte', $equipo_id, false ) : 0;
				}
				break;
		}

		return $this->normalize_sport_key_from_post( $deporte_id );
	}

	/**
	 * Converteix el slug del post deporte a una clau estable per als grups.
	 */
	private function normalize_sport_key_from_post( int $deporte_id ): ?string {
		if ( $deporte_id <= 0 ) {
			return null;
		}

		$slug = (string) get_post_field( 'post_name', $deporte_id );
		if ( '' === $slug ) {
			return null;
		}

		return str_replace( '-', '_', sanitize_title( $slug ) );
	}

	/**
	 * Registra grups ACF específics per deporte.
	 */
	private function register_sport_specific_groups(): void {
		$this->register_jugador_sport_fields();
		$this->register_equipo_sport_fields();
		$this->register_entrenamiento_sport_fields();
		$this->register_torneo_sport_fields();
		$this->register_partido_sport_fields();
	}

	private function register_jugador_sport_fields(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_jugador_sport_specific',
				'title'    => __( 'Fitxa esportiva específica', 'escuela-deportiva-core' ),
				'fields'   => array(
					$this->sport_tab( 'field_ed_jug_tab_fut', __( 'Futbol', 'escuela-deportiva-core' ), 'jugador', array( 'futbol' ) ),
					array(
						'key'           => 'field_ed_jug_fut_pierna',
						'label'         => __( 'Cama hàbil', 'escuela-deportiva-core' ),
						'name'          => 'ed_jugador_fut_pierna_habil',
						'type'          => 'select',
						'choices'       => array(
							'derecha'    => __( 'Dreta', 'escuela-deportiva-core' ),
							'izquierda'  => __( 'Esquerra', 'escuela-deportiva-core' ),
							'ambas'      => __( 'Ambdues', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					array(
						'key'              => 'field_ed_jug_fut_licencia',
						'label'            => __( 'Llicència federativa', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_fut_licencia',
						'type'             => 'text',
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					$this->sport_tab( 'field_ed_jug_tab_basket', __( 'Baloncesto', 'escuela-deportiva-core' ), 'jugador', array( 'baloncesto' ) ),
					array(
						'key'              => 'field_ed_jug_basket_pos',
						'label'            => __( 'Posició', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_basket_posicion',
						'type'             => 'select',
						'choices'          => array(
							'base'           => __( 'Base', 'escuela-deportiva-core' ),
							'escolta'        => __( 'Escolta', 'escuela-deportiva-core' ),
							'aler'           => __( 'Aler', 'escuela-deportiva-core' ),
							'ala_pivot'      => __( 'Ala-pivot', 'escuela-deportiva-core' ),
							'pivot'          => __( 'Pivot', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					array(
						'key'              => 'field_ed_jug_basket_altura',
						'label'            => __( 'Altura (cm)', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_basket_altura_cm',
						'type'             => 'number',
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					array(
						'key'              => 'field_ed_jug_basket_mano',
						'label'            => __( 'Mà hàbil', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_basket_mano_habil',
						'type'             => 'select',
						'choices'          => array(
							'derecha'   => __( 'Dreta', 'escuela-deportiva-core' ),
							'izquierda' => __( 'Esquerra', 'escuela-deportiva-core' ),
							'ambas'     => __( 'Ambdues', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					$this->sport_tab( 'field_ed_jug_tab_pilota', __( 'Pelota Valenciana', 'escuela-deportiva-core' ), 'jugador', array( 'pelota_valenciana' ) ),
					array(
						'key'              => 'field_ed_jug_pilota_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_pilota_modalidad',
						'type'             => 'select',
						'choices'          => array(
							'raspall'         => 'Raspall',
							'escala_corda'    => 'Escala i corda',
							'galotxa'         => 'Galotxa',
							'llargues'        => 'Llargues',
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					array(
						'key'              => 'field_ed_jug_pilota_mano',
						'label'            => __( 'Mà dominant', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_pilota_mano_habil',
						'type'             => 'select',
						'choices'          => array(
							'derecha'   => __( 'Dreta', 'escuela-deportiva-core' ),
							'izquierda' => __( 'Esquerra', 'escuela-deportiva-core' ),
							'ambas'     => __( 'Ambdues', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					array(
						'key'              => 'field_ed_jug_pilota_posicion',
						'label'            => __( 'Posició de joc', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_pilota_posicion',
						'type'             => 'select',
						'choices'          => array(
							'rest'      => 'Rest',
							'mitger'    => 'Mitger',
							'punter'    => 'Punter',
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					$this->sport_tab( 'field_ed_jug_tab_gim', __( 'Gimnasia', 'escuela-deportiva-core' ), 'jugador', array( 'gimnasia' ) ),
					array(
						'key'              => 'field_ed_jug_gim_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_gim_modalidad',
						'type'             => 'select',
						'choices'          => array(
							'ritmica'    => __( 'Rítmica', 'escuela-deportiva-core' ),
							'artistica'  => __( 'Artística', 'escuela-deportiva-core' ),
							'acrobatica' => __( 'Acrobàtica', 'escuela-deportiva-core' ),
							'trampolin'  => __( 'Trampolí', 'escuela-deportiva-core' ),
							'general'    => __( 'General', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
					array(
						'key'              => 'field_ed_jug_gim_nivel',
						'label'            => __( 'Nivell', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_gim_nivel',
						'type'             => 'text',
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
					array(
						'key'              => 'field_ed_jug_gim_aparatos',
						'label'            => __( 'Aparells / especialitats', 'escuela-deportiva-core' ),
						'name'             => 'ed_jugador_gim_aparatos',
						'type'             => 'textarea',
						'rows'             => 3,
						'ed_sport_context' => 'jugador',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'jugador',
						),
					),
				),
			)
		);
	}

	private function register_equipo_sport_fields(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_equipo_sport_specific',
				'title'    => __( 'Configuració esportiva de l’equip', 'escuela-deportiva-core' ),
				'fields'   => array(
					$this->sport_tab( 'field_ed_eq_tab_fut', __( 'Futbol', 'escuela-deportiva-core' ), 'equipo', array( 'futbol' ) ),
					array(
						'key'              => 'field_ed_eq_fut_comp',
						'label'            => __( 'Competició', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_fut_competicion',
						'type'             => 'text',
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					array(
						'key'              => 'field_ed_eq_fut_federado',
						'label'            => __( 'Equip federat', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_fut_federado',
						'type'             => 'true_false',
						'ui'               => 1,
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					$this->sport_tab( 'field_ed_eq_tab_basket', __( 'Baloncesto', 'escuela-deportiva-core' ), 'equipo', array( 'baloncesto' ) ),
					array(
						'key'              => 'field_ed_eq_basket_comp',
						'label'            => __( 'Competició', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_basket_competicion',
						'type'             => 'text',
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					array(
						'key'              => 'field_ed_eq_basket_pista',
						'label'            => __( 'Pista local', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_basket_pista_local',
						'type'             => 'text',
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					$this->sport_tab( 'field_ed_eq_tab_pilota', __( 'Pelota Valenciana', 'escuela-deportiva-core' ), 'equipo', array( 'pelota_valenciana' ) ),
					array(
						'key'              => 'field_ed_eq_pilota_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_pilota_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					array(
						'key'              => 'field_ed_eq_pilota_instalacion',
						'label'            => __( 'Instal·lació habitual', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_pilota_instalacion',
						'type'             => 'text',
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					$this->sport_tab( 'field_ed_eq_tab_gim', __( 'Gimnasia', 'escuela-deportiva-core' ), 'equipo', array( 'gimnasia' ) ),
					array(
						'key'              => 'field_ed_eq_gim_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_gim_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
					array(
						'key'              => 'field_ed_eq_gim_tipo',
						'label'            => __( 'Tipus', 'escuela-deportiva-core' ),
						'name'             => 'ed_eq_gim_tipo',
						'type'             => 'select',
						'choices'          => array(
							'individual' => __( 'Individual', 'escuela-deportiva-core' ),
							'conjunto'   => __( 'Conjunt', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'equipo',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'equipo',
						),
					),
				),
			)
		);
	}

	private function register_entrenamiento_sport_fields(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_entrenamiento_sport_specific',
				'title'    => __( 'Planificació específica de l’entrenament', 'escuela-deportiva-core' ),
				'fields'   => array(
					$this->sport_tab( 'field_ed_ent_tab_fut', __( 'Futbol', 'escuela-deportiva-core' ), 'entrenamiento', array( 'futbol' ) ),
					array(
						'key'              => 'field_ed_ent_fut_objetivo',
						'label'            => __( 'Objectiu tàctic', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_fut_objetivo_tactico',
						'type'             => 'textarea',
						'rows'             => 3,
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					array(
						'key'              => 'field_ed_ent_fut_superficie',
						'label'            => __( 'Superfície', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_fut_superficie',
						'type'             => 'select',
						'choices'          => array(
							'cesped_natural'  => __( 'Gespa natural', 'escuela-deportiva-core' ),
							'cesped_artificial' => __( 'Gespa artificial', 'escuela-deportiva-core' ),
							'pabellon'        => __( 'Pavelló', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					$this->sport_tab( 'field_ed_ent_tab_basket', __( 'Baloncesto', 'escuela-deportiva-core' ), 'entrenamiento', array( 'baloncesto' ) ),
					array(
						'key'              => 'field_ed_ent_basket_enfoque',
						'label'            => __( 'Enfocament', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_basket_enfoque',
						'type'             => 'select',
						'choices'          => array(
							'tecnica'  => __( 'Tècnica', 'escuela-deportiva-core' ),
							'tactica'  => __( 'Tàctica', 'escuela-deportiva-core' ),
							'tiro'     => __( 'Tir', 'escuela-deportiva-core' ),
							'fisico'   => __( 'Físic', 'escuela-deportiva-core' ),
						),
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					array(
						'key'              => 'field_ed_ent_basket_material',
						'label'            => __( 'Material específic', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_basket_material',
						'type'             => 'textarea',
						'rows'             => 2,
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					$this->sport_tab( 'field_ed_ent_tab_pilota', __( 'Pelota Valenciana', 'escuela-deportiva-core' ), 'entrenamiento', array( 'pelota_valenciana' ) ),
					array(
						'key'              => 'field_ed_ent_pilota_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_pilota_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					array(
						'key'              => 'field_ed_ent_pilota_instalacion',
						'label'            => __( 'Instal·lació', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_pilota_instalacion',
						'type'             => 'text',
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					$this->sport_tab( 'field_ed_ent_tab_gim', __( 'Gimnasia', 'escuela-deportiva-core' ), 'entrenamiento', array( 'gimnasia' ) ),
					array(
						'key'              => 'field_ed_ent_gim_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_gim_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
					array(
						'key'              => 'field_ed_ent_gim_aparatos',
						'label'            => __( 'Aparells treballats', 'escuela-deportiva-core' ),
						'name'             => 'ed_ent_gim_aparatos',
						'type'             => 'textarea',
						'rows'             => 3,
						'ed_sport_context' => 'entrenamiento',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'entrenamiento',
						),
					),
				),
			)
		);
	}

	private function register_torneo_sport_fields(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_torneo_sport_specific',
				'title'    => __( 'Configuració esportiva del torneig', 'escuela-deportiva-core' ),
				'fields'   => array(
					$this->sport_tab( 'field_ed_tor_tab_fut', __( 'Futbol', 'escuela-deportiva-core' ), 'torneo', array( 'futbol' ) ),
					array(
						'key'              => 'field_ed_tor_fut_tipo_campo',
						'label'            => __( 'Tipus de camp', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_fut_tipo_campo',
						'type'             => 'select',
						'choices'          => array(
							'futbol_11' => 'Futbol 11',
							'futbol_8'  => 'Futbol 8',
							'futbol_7'  => 'Futbol 7',
							'futsal'    => 'Futsal',
						),
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					array(
						'key'              => 'field_ed_tor_fut_duracion',
						'label'            => __( 'Duració per part (min)', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_fut_duracion_parte',
						'type'             => 'number',
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					$this->sport_tab( 'field_ed_tor_tab_basket', __( 'Baloncesto', 'escuela-deportiva-core' ), 'torneo', array( 'baloncesto' ) ),
					array(
						'key'              => 'field_ed_tor_basket_periodos',
						'label'            => __( 'Nombre de períodes', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_basket_num_periodos',
						'type'             => 'number',
						'default_value'    => 4,
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					array(
						'key'              => 'field_ed_tor_basket_duracion',
						'label'            => __( 'Duració per període (min)', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_basket_duracion_periodo',
						'type'             => 'number',
						'default_value'    => 10,
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					$this->sport_tab( 'field_ed_tor_tab_pilota', __( 'Pelota Valenciana', 'escuela-deportiva-core' ), 'torneo', array( 'pelota_valenciana' ) ),
					array(
						'key'              => 'field_ed_tor_pilota_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_pilota_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					array(
						'key'              => 'field_ed_tor_pilota_tanteo',
						'label'            => __( 'Tanteig objectiu', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_pilota_tanteo_objetivo',
						'type'             => 'text',
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					$this->sport_tab( 'field_ed_tor_tab_gim', __( 'Gimnasia', 'escuela-deportiva-core' ), 'torneo', array( 'gimnasia' ) ),
					array(
						'key'              => 'field_ed_tor_gim_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_gim_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
					array(
						'key'              => 'field_ed_tor_gim_puntuacion',
						'label'            => __( 'Sistema de puntuació', 'escuela-deportiva-core' ),
						'name'             => 'ed_tor_gim_sistema_puntuacion',
						'type'             => 'text',
						'ed_sport_context' => 'torneo',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'torneo',
						),
					),
				),
			)
		);
	}

	private function register_partido_sport_fields(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_partido_sport_specific',
				'title'    => __( 'Dades específiques del partit / prova', 'escuela-deportiva-core' ),
				'fields'   => array(
					$this->sport_tab( 'field_ed_par_tab_fut', __( 'Futbol', 'escuela-deportiva-core' ), 'partido', array( 'futbol' ) ),
					array(
						'key'              => 'field_ed_par_fut_arbitro',
						'label'            => __( 'Àrbitre principal', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_fut_arbitro',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					array(
						'key'              => 'field_ed_par_fut_competicion',
						'label'            => __( 'Competició / jornada', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_fut_competicion',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'futbol' ),
					),
					$this->sport_tab( 'field_ed_par_tab_basket', __( 'Baloncesto', 'escuela-deportiva-core' ), 'partido', array( 'baloncesto' ) ),
					array(
						'key'              => 'field_ed_par_basket_arbitros',
						'label'            => __( 'Àrbitres', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_basket_arbitros',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					array(
						'key'              => 'field_ed_par_basket_periodo',
						'label'            => __( 'Període actual', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_basket_periodo_actual',
						'type'             => 'number',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'baloncesto' ),
					),
					$this->sport_tab( 'field_ed_par_tab_pilota', __( 'Pelota Valenciana', 'escuela-deportiva-core' ), 'partido', array( 'pelota_valenciana' ) ),
					array(
						'key'              => 'field_ed_par_pilota_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_pilota_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					array(
						'key'              => 'field_ed_par_pilota_tanteo',
						'label'            => __( 'Tanteig', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_pilota_tanteo',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'pelota_valenciana' ),
					),
					$this->sport_tab( 'field_ed_par_tab_gim', __( 'Gimnasia', 'escuela-deportiva-core' ), 'partido', array( 'gimnasia' ) ),
					array(
						'key'              => 'field_ed_par_gim_modalidad',
						'label'            => __( 'Modalitat', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_gim_modalidad',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
					array(
						'key'              => 'field_ed_par_gim_rotacion',
						'label'            => __( 'Rotació / aparell', 'escuela-deportiva-core' ),
						'name'             => 'ed_par_gim_rotacion',
						'type'             => 'text',
						'ed_sport_context' => 'partido',
						'ed_sport_keys'    => array( 'gimnasia' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'partido',
						),
					),
				),
			)
		);
	}

	/**
	 * Helper per a tabs específiques per deporte.
	 *
	 * @param string[] $sport_keys Claus de deporte permeses.
	 * @return array<string,mixed>
	 */
	private function sport_tab( string $key, string $label, string $context, array $sport_keys ): array {
		return array(
			'key'              => $key,
			'label'            => $label,
			'type'             => 'tab',
			'ed_sport_context' => $context,
			'ed_sport_keys'    => $sport_keys,
		);
	}

	/**
	 * Scripts encarregats del refresc AJAX iteratiu al panell d'admin.
	 */
	public function enqueue_acf_dynamic_js(): void {
		$deportes = get_posts( array( 'post_type' => 'deporte', 'posts_per_page' => -1 ) );
		$map = array();
		foreach ( $deportes as $d ) {
			$nk = $this->normalize_sport_key_from_post( $d->ID );
			if ( $nk ) {
				$map[ $d->ID ] = $nk;
			}
		}
		wp_localize_script( 'acf-input', 'edSportMap', $map );
		?>
		<script type="text/javascript">
		(function($) {
			if (typeof acf === 'undefined') return;

			var sportMap = window.edSportMap || {};

			function toggleSportFields(sportKey) {
				$('.ed-sport-specific-field').hide();
				if (sportKey) {
					$('.ed-sport-keys-' + sportKey).show();
				}
			}

			acf.addAction('change', function($el) {
				var name = $el.data('name');
				if (name === 'ed_jugador_deporte' || name === 'ed_eq_deporte') {
					var targetName = name === 'ed_jugador_deporte' ? 'ed_jugador_categoria' : 'ed_eq_categoria';
					var targetField = acf.findFields({name: targetName});
					if (targetField.length) {
						targetField.val(null).trigger('change'); 
					}
					var sportId = $el.val();
					var sportKey = sportMap[sportId] || null;
					toggleSportFields(sportKey);
				}
			});

			acf.addFilter('select2_ajax_data', function(data, args, $input, field, instance) {
				var fieldName = field.data('name');
				if ( fieldName === 'ed_jugador_categoria' || fieldName === 'ed_eq_categoria' ) {
					var depName = fieldName === 'ed_jugador_categoria' ? 'ed_jugador_deporte' : 'ed_eq_deporte';
					var depField = acf.findFields({name: depName});
					if (depField.length) {
						data.ed_ajax_deporte_id = depField.val() || 0;
					}
				}
				return data;
			});
		})(jQuery);
		</script>
		<?php
	}

	private function register_jugador(): void {
		acf_add_local_field_group(
			array(
				'key'                   => 'group_ed_jugador',
				'title'                 => __( 'Dades del jugador', 'escuela-deportiva-core' ),
				'fields'                => array(
					array(
						'key'   => 'field_ed_jug_nombre',
						'label' => __( 'Nom', 'escuela-deportiva-core' ),
						'name'  => 'ed_jugador_nombre',
						'type'  => 'text',
						'required' => 1,
					),
					array(
						'key'   => 'field_ed_jug_apellidos',
						'label' => __( 'Cognoms', 'escuela-deportiva-core' ),
						'name'  => 'ed_jugador_apellidos',
						'type'  => 'text',
						'required' => 1,
					),
					array(
						'key'   => 'field_ed_jug_fnac',
						'label' => __( 'Data de naixement', 'escuela-deportiva-core' ),
						'name'  => 'ed_jugador_fecha_nacimiento',
						'type'  => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format'  => 'Y-m-d',
						'required' => 1,
					),
					array(
						'key'   => 'field_ed_jug_foto',
						'label' => __( 'Foto', 'escuela-deportiva-core' ),
						'name'  => 'ed_jugador_foto',
						'type'  => 'image',
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_jug_deporte',
						'label'         => __( 'Esport', 'escuela-deportiva-core' ),
						'name'          => 'ed_jugador_deporte',
						'type'          => 'post_object',
						'post_type'     => array( 'deporte' ),
						'return_format' => 'id',
						'required'      => 1,
					),
					array(
						'key'           => 'field_ed_jug_categoria',
						'label'         => __( 'Categoria', 'escuela-deportiva-core' ),
						'name'          => 'ed_jugador_categoria',
						'type'          => 'post_object',
						'post_type'     => array( 'categoria' ),
						'return_format' => 'id',
						'instructions'  => __( 'Si es deixa buit, s’assignarà automàticament per edat (esport requerit).', 'escuela-deportiva-core' ),
					),
					array(
						'key'           => 'field_ed_jug_equipo',
						'label'         => __( 'Equip', 'escuela-deportiva-core' ),
						'name'          => 'ed_jugador_equipo',
						'type'          => 'post_object',
						'post_type'     => array( 'equipo' ),
						'return_format' => 'id',
					),
					array(
						'key'     => 'field_ed_jug_pos',
						'label'   => __( 'Posició', 'escuela-deportiva-core' ),
						'name'    => 'ed_jugador_posicion',
						'type'    => 'select',
						'choices' => array(
							'portero'        => __( 'Porter', 'escuela-deportiva-core' ),
							'defensa'        => __( 'Defensa', 'escuela-deportiva-core' ),
							'centrocampista' => __( 'Centrecampista', 'escuela-deportiva-core' ),
							'delantero'      => __( 'Davanter', 'escuela-deportiva-core' ),
						),
					),
					array(
						'key'          => 'field_ed_jug_dorsal',
						'label'        => __( 'Dorsal', 'escuela-deportiva-core' ),
						'name'         => 'ed_jugador_dorsal',
						'type'         => 'number',
						'min'          => 1,
						'max'          => 99,
					),
					array(
						'key'     => 'field_ed_jug_estado',
						'label'   => __( 'Estat', 'escuela-deportiva-core' ),
						'name'    => 'ed_jugador_estado',
						'type'    => 'select',
						'choices' => array(
							'activo'    => __( 'Actiu', 'escuela-deportiva-core' ),
							'inactivo'  => __( 'Inactiu', 'escuela-deportiva-core' ),
							'baja'      => __( 'Baixa', 'escuela-deportiva-core' ),
							'lesion'    => __( 'Lesió', 'escuela-deportiva-core' ),
						),
						'default_value' => 'activo',
					),
					array(
						'key'            => 'field_ed_jug_nucleo',
						'label'          => __( 'Nucli familiar', 'escuela-deportiva-core' ),
						'name'           => 'ed_jugador_nucleo',
						'type'           => 'post_object',
						'post_type'      => array( 'nucleo_familiar' ),
						'return_format'  => 'id',
						'required'       => 1,
						'instructions'   => __( 'En desar, el sistema vincula el jugador al nucli on estàs donat d’alta com a adult (si el camp queda sense triar o encara no assignat).', 'escuela-deportiva-core' ),
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'jugador',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
			)
		);
	}

	private function register_deporte(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_deporte',
				'title'    => __( 'Dades de l’esport', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'   => 'field_ed_dep_desc',
						'label' => __( 'Descripció', 'escuela-deportiva-core' ),
						'name'  => 'ed_dep_descripcion',
						'type'  => 'textarea',
						'rows'  => 4,
					),
					array(
						'key'             => 'field_ed_dep_coord',
						'label'           => __( 'Coordinador', 'escuela-deportiva-core' ),
						'name'            => 'ed_dep_coordinador',
						'type'            => 'user',
						'role'            => array( 'administrator', 'ed_coordinador' ),
						'return_format'   => 'id',
					),
					array(
						'key'             => 'field_ed_dep_entrenadores',
						'label'           => __( 'Entrenadors', 'escuela-deportiva-core' ),
						'name'            => 'ed_dep_entrenadores',
						'type'            => 'user',
						'multiple'        => 1,
						'return_format'   => 'id',
						'role'            => array( 'administrator', 'ed_coordinador', 'ed_entrenador' ),
						'instructions'    => __( 'Usuaris amb rol d’entrenador (o coordinador) assignats a aquest esport. Les sessions d’entrenament continuen vinculant entrenador i categoria concreta.', 'escuela-deportiva-core' ),
					),
					array(
						'key'   => 'field_ed_dep_activo',
						'label' => __( 'Actiu', 'escuela-deportiva-core' ),
						'name'  => 'ed_dep_activo',
						'type'  => 'true_false',
						'default_value' => 1,
						'ui'    => 1,
					),
					array(
						'key'   => 'field_ed_dep_importe',
						'label' => __( 'Import total (€)', 'escuela-deportiva-core' ),
						'name'  => 'ed_dep_importe_total',
						'type'  => 'number',
						'step'  => '0.01',
					),
					array(
						'key'   => 'field_ed_dep_p1',
						'label' => __( 'Data límit 1r termini (50%)', 'escuela-deportiva-core' ),
						'name'  => 'ed_dep_fecha_plazo1',
						'type'  => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format'  => 'Y-m-d',
					),
					array(
						'key'   => 'field_ed_dep_p2',
						'label' => __( 'Data límit 2n termini (50%)', 'escuela-deportiva-core' ),
						'name'  => 'ed_dep_fecha_plazo2',
						'type'  => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format'  => 'Y-m-d',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'deporte',
						),
					),
				),
			)
		);
	}

	private function register_categoria(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_categoria',
				'title'    => __( 'Rang d’edat', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'           => 'field_ed_cat_nom_hint',
						'label'         => __( 'Nom de la categoria', 'escuela-deportiva-core' ),
						'name'          => 'ed_cat_hint_titulo',
						'type'          => 'message',
						'message'       => __( 'El títol del post (a dalt) és el nom públic (p. ex. Benjamí, Aleví, Infantil, Juvenil). Crea’n un per cada esport i rang d’edat.', 'escuela-deportiva-core' ),
						'new_lines'     => 'wpautop',
					),
					array(
						'key'           => 'field_ed_cat_dep',
						'label'         => __( 'Esport', 'escuela-deportiva-core' ),
						'name'          => 'ed_cat_deporte',
						'type'          => 'post_object',
						'post_type'     => array( 'deporte' ),
						'return_format' => 'id',
						'required'      => 1,
					),
					array(
						'key'          => 'field_ed_cat_min',
						'label'        => __( 'Edat mínima', 'escuela-deportiva-core' ),
						'name'         => 'ed_cat_edad_min',
						'type'         => 'number',
						'required'     => 1,
						'instructions' => __( 'Anys complits (inclosos).', 'escuela-deportiva-core' ),
					),
					array(
						'key'          => 'field_ed_cat_max',
						'label'        => __( 'Edat màxima', 'escuela-deportiva-core' ),
						'name'         => 'ed_cat_edad_max',
						'type'         => 'number',
						'required'     => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'categoria',
						),
					),
				),
			)
		);
	}

	private function register_equipo(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_equipo',
				'title'    => __( 'Dades de l’equip', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'           => 'field_ed_eq_dep',
						'label'         => __( 'Esport', 'escuela-deportiva-core' ),
						'name'          => 'ed_eq_deporte',
						'type'          => 'post_object',
						'post_type'     => array( 'deporte' ),
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_eq_cat',
						'label'         => __( 'Categoria', 'escuela-deportiva-core' ),
						'name'          => 'ed_eq_categoria',
						'type'          => 'post_object',
						'post_type'     => array( 'categoria' ),
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_eq_esc',
						'label'         => __( 'Escut', 'escuela-deportiva-core' ),
						'name'          => 'ed_eq_escudo',
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_eq_loc',
						'label' => __( 'Localitat', 'escuela-deportiva-core' ),
						'name'  => 'ed_eq_localidad',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_ed_eq_col',
						'label' => __( 'Color corporatiu', 'escuela-deportiva-core' ),
						'name'  => 'ed_eq_color_principal',
						'type'  => 'color_picker',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'equipo',
						),
					),
				),
			)
		);
	}

	private function register_nucleo(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_nucleo',
				'title'    => __( 'Adults responsables', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'             => 'field_ed_nuc_adultos',
						'label'           => __( 'Usuaris adults', 'escuela-deportiva-core' ),
						'name'            => 'ed_nucleo_adultos',
						'type'            => 'user',
						'multiple'        => 1,
						'return_format'   => 'id',
						'required'        => 1,
						'instructions'    => __( 'Usuaris WordPress vinculats a aquest nucli.', 'escuela-deportiva-core' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'nucleo_familiar',
						),
					),
				),
			)
		);
	}

	private function register_entrenamiento(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_entrenamiento',
				'title'    => __( 'Sessió d’entrenament', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'           => 'field_ed_ent_cat',
						'label'       => __( 'Categoria', 'escuela-deportiva-core' ),
						'name'        => 'ed_ent_categoria',
						'type'        => 'post_object',
						'post_type'   => array( 'categoria' ),
						'return_format' => 'id',
						'required'    => 1,
					),
					array(
						'key'           => 'field_ed_ent_eq',
						'label'       => __( 'Equip', 'escuela-deportiva-core' ),
						'name'        => 'ed_ent_equipo',
						'type'        => 'post_object',
						'post_type'   => array( 'equipo' ),
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_ent_coach',
						'label'       => __( 'Entrenador', 'escuela-deportiva-core' ),
						'name'        => 'ed_ent_entrenador',
						'type'        => 'user',
						'role'        => array( 'administrator', 'ed_entrenador', 'ed_coordinador' ),
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_ent_fh',
						'label' => __( 'Data i hora', 'escuela-deportiva-core' ),
						'name'  => 'ed_ent_fecha_hora',
						'type'  => 'date_time_picker',
						'display_format' => 'd/m/Y H:i',
						'return_format'  => 'Y-m-d H:i',
						'required' => 1,
					),
					array(
						'key'   => 'field_ed_ent_dur',
						'label' => __( 'Durada (min)', 'escuela-deportiva-core' ),
						'name'  => 'ed_ent_duracion_minutos',
						'type'  => 'number',
						'default_value' => 90,
					),
					array(
						'key'   => 'field_ed_ent_lugar',
						'label' => __( 'Lloc', 'escuela-deportiva-core' ),
						'name'  => 'ed_ent_lugar',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_ed_ent_notas',
						'label' => __( 'Notes', 'escuela-deportiva-core' ),
						'name'  => 'ed_ent_notas',
						'type'  => 'textarea',
						'rows'  => 3,
					),
					array(
						'key'   => 'field_ed_ent_cerrada',
						'label' => __( 'Assistència tancada', 'escuela-deportiva-core' ),
						'name'  => 'ed_ent_asistencia_cerrada',
						'type'  => 'true_false',
						'ui'    => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'entrenamiento',
						),
					),
				),
			)
		);
	}

	private function register_torneo_partido(): void {
		acf_add_local_field_group(
			array(
				'key'    => 'group_ed_torneo',
				'title'  => __( 'Torneig', 'escuela-deportiva-core' ),
				'fields' => array(
					array(
						'key'           => 'field_ed_tor_dep',
						'label'         => __( 'Esport', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_deporte',
						'type'          => 'post_object',
						'post_type'     => array( 'deporte' ),
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_tor_ini',
						'label' => __( 'Data inici', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_fecha_inicio',
						'type'  => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format'  => 'Y-m-d',
					),
					array(
						'key'   => 'field_ed_tor_fin',
						'label' => __( 'Data fi', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_fecha_fin',
						'type'  => 'date_picker',
						'display_format' => 'd/m/Y',
						'return_format'  => 'Y-m-d',
					),
					array(
						'key'   => 'field_ed_tor_lug',
						'label' => __( 'Lloc', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_lugar',
						'type'  => 'text',
					),
					array(
						'key'     => 'field_ed_tor_fmt',
						'label'   => __( 'Format', 'escuela-deportiva-core' ),
						'name'    => 'ed_tor_formato',
						'type'    => 'select',
						'choices' => array(
							'eliminacion' => __( 'Eliminatòria', 'escuela-deportiva-core' ),
							'grupos'      => __( 'Grups', 'escuela-deportiva-core' ),
						),
						'default_value' => 'eliminacion',
					),
					array(
						'key'     => 'field_ed_tor_est',
						'label'   => __( 'Estat', 'escuela-deportiva-core' ),
						'name'    => 'ed_tor_estado',
						'type'    => 'select',
						'choices' => array(
							'borrador'      => __( 'Borrador', 'escuela-deportiva-core' ),
							'inscripciones' => __( 'Inscripcions', 'escuela-deportiva-core' ),
							'en_curso'      => __( 'En curs', 'escuela-deportiva-core' ),
							'finalizado'    => __( 'Finalitzat', 'escuela-deportiva-core' ),
						),
						'default_value' => 'borrador',
					),
					array(
						'key'   => 'field_ed_tor_max',
						'label' => __( 'Màx. equips', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_max_equipos',
						'type'  => 'number',
						'min'   => 0,
					),
					array(
						'key'   => 'field_ed_tor_cuota',
						'label' => __( 'Quota inscripció (€)', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_cuota_inscripcion',
						'type'  => 'number',
						'min'   => 0,
						'default_value' => 0,
					),
					array(
						'key'   => 'field_ed_tor_wc',
						'label' => __( 'ID producte WooCommerce', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_wc_product_id',
						'type'  => 'number',
						'min'   => 0,
					),
					array(
						'key'           => 'field_ed_tor_logo',
						'label'         => __( 'Logotip', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_logo',
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_tor_desc',
						'label' => __( 'Descripció curta', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_descripcion_corta',
						'type'  => 'textarea',
						'rows'  => 3,
					),
					array(
						'key'   => 'field_ed_tor_color',
						'label' => __( 'Color principal', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_color_principal',
						'type'  => 'color_picker',
					),
					array(
						'key'           => 'field_ed_tor_eq_ins',
						'label'         => __( 'Equips inscrits', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_equipos_inscritos',
						'type'          => 'relationship',
						'post_type'     => array( 'equipo' ),
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_tor_tab_ent',
						'label' => __( 'Entrades i rifa', 'escuela-deportiva-core' ),
						'type'  => 'tab',
					),
					array(
						'key'   => 'field_ed_tor_ent_act',
						'label' => __( 'Venda d’entrades activa', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_entradas_activas',
						'type'  => 'true_false',
						'ui'    => 1,
					),
					array(
						'key'   => 'field_ed_tor_ent_preu',
						'label' => __( 'Preu entrada (€)', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_precio_entrada',
						'type'  => 'number',
						'min'   => 0,
						'step'  => '0.5',
					),
					array(
						'key'   => 'field_ed_tor_aforo',
						'label' => __( 'Aforament màxim', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_aforo_maximo',
						'type'  => 'number',
						'min'   => 0,
					),
					array(
						'key'   => 'field_ed_tor_wc_ent',
						'label' => __( 'ID producte WC (entrada)', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_wc_product_entrada',
						'type'  => 'number',
						'min'   => 0,
					),
					array(
						'key'   => 'field_ed_tor_rifa_act',
						'label' => __( 'Rifa activa', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_rifa_activa',
						'type'  => 'true_false',
						'ui'    => 1,
					),
					array(
						'key'           => 'field_ed_tor_rifa_nums',
						'label'         => __( 'Números per entrada', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_rifa_numeros_por_entrada',
						'type'          => 'number',
						'default_value' => 1,
						'min'           => 1,
					),
					array(
						'key'           => 'field_ed_tor_rifa_ini',
						'label'         => __( 'Número inicial (rifa)', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_rifa_rango_inicio',
						'type'          => 'number',
						'default_value' => 0,
					),
					array(
						'key'           => 'field_ed_tor_rifa_fin',
						'label'         => __( 'Número final (rifa)', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_rifa_rango_fin',
						'type'          => 'number',
						'default_value' => 1000,
					),
					array(
						'key'   => 'field_ed_tor_tab_bar',
						'label' => __( 'Bar', 'escuela-deportiva-core' ),
						'type'  => 'tab',
					),
					array(
						'key'   => 'field_ed_tor_bar_act',
						'label' => __( 'Bar actiu', 'escuela-deportiva-core' ),
						'name'  => 'ed_tor_bar_activo',
						'type'  => 'true_false',
						'ui'    => 1,
					),
					array(
						'key'           => 'field_ed_tor_bar_fran',
						'label'         => __( 'Franges horàries', 'escuela-deportiva-core' ),
						'name'          => 'ed_tor_bar_franjas_horarias',
						'type'          => 'textarea',
						'rows'          => 4,
						'instructions'  => __( 'Una franja per línia (ex.: 10:00).', 'escuela-deportiva-core' ),
					),
					array(
						'key'          => 'field_ed_tor_bar_cie',
						'label'        => __( 'Hora tancament comandes', 'escuela-deportiva-core' ),
						'name'         => 'ed_tor_bar_hora_cierre',
						'type'         => 'text',
						'placeholder'  => '13:30',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'torneo',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'    => 'group_ed_partido',
				'title'  => __( 'Partit', 'escuela-deportiva-core' ),
				'fields' => array(
					array(
						'key'           => 'field_ed_par_tor',
						'label'         => __( 'Torneig', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_torneo',
						'type'          => 'post_object',
						'post_type'     => array( 'torneo' ),
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_par_el',
						'label'         => __( 'Equip local', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_equipo_local',
						'type'          => 'post_object',
						'post_type'     => array( 'equipo' ),
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_par_ev',
						'label'         => __( 'Equip visitant', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_equipo_visitante',
						'type'          => 'post_object',
						'post_type'     => array( 'equipo' ),
						'return_format' => 'id',
					),
					array(
						'key'            => 'field_ed_par_fh',
						'label'          => __( 'Data i hora', 'escuela-deportiva-core' ),
						'name'           => 'ed_par_fecha_hora',
						'type'           => 'date_time_picker',
						'display_format' => 'd/m/Y H:i',
						'return_format'  => 'Y-m-d H:i',
					),
					array(
						'key'   => 'field_ed_par_lugar',
						'label' => __( 'Lloc', 'escuela-deportiva-core' ),
						'name'  => 'ed_par_lugar',
						'type'  => 'text',
					),
					array(
						'key'     => 'field_ed_par_fase',
						'label'   => __( 'Fase', 'escuela-deportiva-core' ),
						'name'    => 'ed_par_fase',
						'type'    => 'select',
						'choices' => array(
							'cuartos'  => 'Cuartos',
							'semis'    => 'Semis',
							'final'    => 'Final',
							'grupo'    => __( 'Grup', 'escuela-deportiva-core' ),
							'amistoso' => __( 'Amistós', 'escuela-deportiva-core' ),
						),
						'default_value' => 'amistoso',
					),
					array(
						'key'   => 'field_ed_par_grupo',
						'label' => __( 'Grup', 'escuela-deportiva-core' ),
						'name'  => 'ed_par_grupo',
						'type'  => 'text',
					),
					array(
						'key'     => 'field_ed_par_estado',
						'label'   => __( 'Estat en viu', 'escuela-deportiva-core' ),
						'name'    => 'ed_par_estado',
						'type'    => 'select',
						'choices' => array(
							'programado'  => __( 'Programat', 'escuela-deportiva-core' ),
							'en_curso'    => __( 'En curs', 'escuela-deportiva-core' ),
							'descanso'    => __( 'Descans', 'escuela-deportiva-core' ),
							'finalizado'  => __( 'Finalitzat', 'escuela-deportiva-core' ),
							'aplazado'    => __( 'Aplazat', 'escuela-deportiva-core' ),
						),
						'default_value' => 'programado',
					),
					array(
						'key'           => 'field_ed_par_gl',
						'label'         => __( 'Gols local', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_goles_local',
						'type'          => 'number',
						'default_value' => 0,
						'min'           => 0,
					),
					array(
						'key'           => 'field_ed_par_gv',
						'label'         => __( 'Gols visitant', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_goles_visitante',
						'type'          => 'number',
						'default_value' => 0,
						'min'           => 0,
					),
					array(
						'key'           => 'field_ed_par_min',
						'label'         => __( 'Minut actual', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_minuto_actual',
						'type'          => 'number',
						'default_value' => 0,
						'min'           => 0,
					),
					array(
						'key'             => 'field_ed_par_op',
						'label'           => __( 'Operador / àrbitre', 'escuela-deportiva-core' ),
						'name'            => 'ed_par_operador',
						'type'            => 'user',
						'role'            => array( 'administrator', 'ed_operador', 'ed_coordinador' ),
						'return_format'   => 'id',
					),
					array(
						'key'           => 'field_ed_par_mvp',
						'label'         => __( 'MVP del partit', 'escuela-deportiva-core' ),
						'name'          => 'ed_par_mvp_jugador',
						'type'          => 'post_object',
						'post_type'     => array( 'jugador' ),
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_par_cro',
						'label' => __( 'Crònica', 'escuela-deportiva-core' ),
						'name'  => 'ed_par_cronica',
						'type'  => 'wysiwyg',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'partido',
						),
					),
				),
			)
		);
	}

	private function register_fase5_anuncio(): void {
		$choices = class_exists( 'ED_Publicidad' ) ? ED_Publicidad::posicion_choices() : array();
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_anuncio',
				'title'    => __( 'Dades de l’anunci', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'   => 'field_ed_an_emp',
						'label' => __( 'Nom empresa', 'escuela-deportiva-core' ),
						'name'  => 'ed_anuncio_empresa_nombre',
						'type'  => 'text',
					),
					array(
						'key'           => 'field_ed_an_img',
						'label'         => __( 'Imatge del banner', 'escuela-deportiva-core' ),
						'name'          => 'ed_anuncio_imagen',
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_an_url',
						'label' => __( 'URL de destinació', 'escuela-deportiva-core' ),
						'name'  => 'ed_anuncio_url_destino',
						'type'  => 'url',
					),
					array(
						'key'     => 'field_ed_an_pos',
						'label'   => __( 'Posició', 'escuela-deportiva-core' ),
						'name'    => 'ed_anuncio_posicion',
						'type'    => 'select',
						'choices' => $choices,
					),
					array(
						'key'           => 'field_ed_an_act',
						'label'         => __( 'Actiu', 'escuela-deportiva-core' ),
						'name'          => 'ed_anuncio_activo',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
					),
					array(
						'key'   => 'field_ed_an_sub',
						'label' => __( 'ID subscripció WooCommerce (opcional)', 'escuela-deportiva-core' ),
						'name'  => 'ed_anuncio_wc_subscription_id',
						'type'  => 'number',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'anuncio',
						),
					),
				),
			)
		);
	}

	private function register_fase5_empresa_directorio(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_ed_emp_dir',
				'title'    => __( 'Dades de l’empresa', 'escuela-deportiva-core' ),
				'fields'   => array(
					array(
						'key'   => 'field_ed_ed_desc',
						'label' => __( 'Descripció', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_descripcion',
						'type'  => 'textarea',
						'rows'  => 4,
					),
					array(
						'key'   => 'field_ed_ed_dir',
						'label' => __( 'Adreça', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_direccion',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_ed_ed_tel',
						'label' => __( 'Telèfon', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_telefono',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_ed_ed_mail',
						'label' => __( 'Correu', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_email',
						'type'  => 'email',
					),
					array(
						'key'   => 'field_ed_ed_web',
						'label' => __( 'Web', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_web',
						'type'  => 'url',
					),
					array(
						'key'   => 'field_ed_ed_hor',
						'label' => __( 'Horari', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_horario',
						'type'  => 'text',
					),
					array(
						'key'           => 'field_ed_ed_logo',
						'label'         => __( 'Logo', 'escuela-deportiva-core' ),
						'name'          => 'ed_emp_dir_logo',
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'           => 'field_ed_ed_fotos',
						'label'         => __( 'Fotos', 'escuela-deportiva-core' ),
						'name'          => 'ed_emp_dir_fotos',
						'type'          => 'gallery',
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_ed_ed_dest',
						'label' => __( 'Destacada', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_destacada',
						'type'  => 'true_false',
						'ui'    => 1,
					),
					array(
						'key'   => 'field_ed_ed_ban',
						'label' => __( 'Banner actiu', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_anuncio_activo',
						'type'  => 'true_false',
						'ui'    => 1,
					),
					array(
						'key'   => 'field_ed_ed_wc',
						'label' => __( 'ID subscripció WooCommerce (opcional)', 'escuela-deportiva-core' ),
						'name'  => 'ed_emp_dir_wc_subscription_id',
						'type'  => 'number',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'empresa_directorio',
						),
					),
				),
			)
		);
	}
}
