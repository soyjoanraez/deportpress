<?php
/**
 * Custom Post Types.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registro de CPTs DeportPress (Fase 1).
 */
class ED_CPT {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'before_delete_post', array( $this, 'delete_associated_media' ), 10, 1 );
	}

	/**
	 * Elimina las imágenes destacadas físicamente del disco al borrar el CPT.
	 */
	public function delete_associated_media( int $post_id ): void {
		$pt = get_post_type( $post_id );
		
		if ( in_array( $pt, array( 'jugador', 'equipo', 'deporte', 'torneo', 'categoria' ), true ) ) {
			$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $thumbnail_id ) {
				wp_delete_attachment( (int) $thumbnail_id, true );
			}
		}
	}

	/**
	 * Registra CPTs privados (solo admin / lógica interna).
	 */
	public function register_post_types(): void {
		$this->register_torneo_y_partido();
		$this->register_fase5_types();
		$this->register_type(
			'nucleo_familiar',
			__( 'Nuclis familiars', 'escuela-deportiva-core' ),
			__( 'Nucli familiar', 'escuela-deportiva-core' ),
			array( 'nucleo_familiar', 'nucleo_familiars' )
		);

		$this->register_type(
			'jugador',
			__( 'Jugadors', 'escuela-deportiva-core' ),
			__( 'Jugador', 'escuela-deportiva-core' ),
			array( 'jugador', 'jugadores' )
		);

		$this->register_type(
			'deporte',
			__( 'Esports', 'escuela-deportiva-core' ),
			__( 'Esport', 'escuela-deportiva-core' ),
			array( 'deporte', 'deportes' )
		);

		$this->register_type(
			'categoria',
			__( 'Categories', 'escuela-deportiva-core' ),
			__( 'Categoria', 'escuela-deportiva-core' ),
			array( 'categoria', 'categorias' )
		);

		$this->register_type(
			'equipo',
			__( 'Equips', 'escuela-deportiva-core' ),
			__( 'Equip', 'escuela-deportiva-core' ),
			array( 'equipo', 'equipos' )
		);

		$this->register_type(
			'entrenamiento',
			__( 'Entrenaments', 'escuela-deportiva-core' ),
			__( 'Entrenament', 'escuela-deportiva-core' ),
			array( 'entrenamiento', 'entrenamientos' )
		);
	}

	/**
	 * CPTs públics torneig / partit (Fase 3–4).
	 */
	private function register_torneo_y_partido(): void {
		register_post_type(
			'torneo',
			array(
				'labels'             => array(
					'name'          => __( 'Torneigs', 'escuela-deportiva-core' ),
					'singular_name' => __( 'Torneig', 'escuela-deportiva-core' ),
					'add_new_item'  => __( 'Afig torneig', 'escuela-deportiva-core' ),
					'edit_item'     => __( 'Edita torneig', 'escuela-deportiva-core' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => 'torneo', 'with_front' => false ),
				'capability_type'    => array( 'torneo', 'torneos' ),
				'map_meta_cap'       => true,
				'supports'           => array( 'title', 'thumbnail', 'editor' ),
			)
		);

		register_post_type(
			'partido',
			array(
				'labels'             => array(
					'name'          => __( 'Partits', 'escuela-deportiva-core' ),
					'singular_name' => __( 'Partit', 'escuela-deportiva-core' ),
					'add_new_item'  => __( 'Afig partit', 'escuela-deportiva-core' ),
					'edit_item'     => __( 'Edita partit', 'escuela-deportiva-core' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => 'partido', 'with_front' => false ),
				'capability_type'    => array( 'partido', 'partidos' ),
				'map_meta_cap'       => true,
				'supports'           => array( 'title' ),
			)
		);
	}

	/**
	 * CPTs Fase 5: publicitat i directori.
	 */
	private function register_fase5_types(): void {
		register_taxonomy(
			'categoria_empresa',
			array( 'empresa_directorio' ),
			array(
				'labels'            => array(
					'name'          => __( 'Categories d’empresa', 'escuela-deportiva-core' ),
					'singular_name' => __( 'Categoria d’empresa', 'escuela-deportiva-core' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'directorio-categoria', 'with_front' => false ),
			)
		);

		register_post_type(
			'anuncio',
			array(
				'labels'             => array(
					'name'          => __( 'Anuncis', 'escuela-deportiva-core' ),
					'singular_name' => __( 'Anunci', 'escuela-deportiva-core' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'supports'           => array( 'title' ),
				'capability_type'    => array( 'anuncio', 'anuncios' ),
				'map_meta_cap'       => true,
			)
		);

		register_post_type(
			'empresa_directorio',
			array(
				'labels'             => array(
					'name'          => __( 'Directori', 'escuela-deportiva-core' ),
					'singular_name' => __( 'Empresa', 'escuela-deportiva-core' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => 'directorio', 'with_front' => false ),
				'capability_type'    => array( 'empresa_directorio', 'empresa_directorios' ),
				'map_meta_cap'       => true,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'taxonomies'         => array( 'categoria_empresa' ),
			)
		);
	}

	/**
	 * @param string               $slug   Slug CPT.
	 * @param string               $label  Plural.
	 * @param string               $single Singular.
	 * @param array{0:string,1:string} $cap_pair capability_type.
	 */
	private function register_type( string $slug, string $label, string $single, array $cap_pair ): void {
		$labels = array(
			'name'               => $label,
			'singular_name'      => $single,
			'add_new'            => __( 'Afig nou', 'escuela-deportiva-core' ),
			'add_new_item'       => sprintf(
				/* translators: %s: singular post type label */
				__( 'Afig %s', 'escuela-deportiva-core' ),
				$single
			),
			'edit_item'          => sprintf(
				/* translators: %s: singular post type label */
				__( 'Edita %s', 'escuela-deportiva-core' ),
				$single
			),
			'new_item'           => sprintf(
				/* translators: %s: singular post type label */
				__( 'Nou %s', 'escuela-deportiva-core' ),
				$single
			),
			'view_item'          => sprintf(
				/* translators: %s: singular post type label */
				__( 'Vore %s', 'escuela-deportiva-core' ),
				$single
			),
			'search_items'       => sprintf(
				/* translators: %s: plural post type label */
				__( 'Cerca %s', 'escuela-deportiva-core' ),
				$label
			),
			'not_found'          => __( 'No s’ha trobat res.', 'escuela-deportiva-core' ),
			'not_found_in_trash' => __( 'No hi ha res a la paperera.', 'escuela-deportiva-core' ),
			'menu_name'          => $label,
		);

		register_post_type(
			$slug,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => $cap_pair,
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'thumbnail' ),
			)
		);
	}
}
