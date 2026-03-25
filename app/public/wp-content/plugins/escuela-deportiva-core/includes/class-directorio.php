<?php
/**
 * Directori d’empreses (Fase 5).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Consultes, plantilles i schema.org.
 */
class ED_Directorio {

	public static function register(): void {
		add_filter( 'template_include', array( self::class, 'template_include' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ), 20 );
		add_action( 'wp_head', array( self::class, 'print_schema_single' ), 5 );
	}

	public static function enqueue_assets(): void {
		if ( ! is_post_type_archive( 'empresa_directorio' ) && ! is_singular( 'empresa_directorio' ) && ! is_tax( 'categoria_empresa' ) ) {
			return;
		}
		wp_enqueue_style(
			'ed-directorio',
			ED_PLUGIN_URL . 'assets/css/directorio.css',
			array(),
			ED_VERSION
		);
	}

	public static function print_schema_single(): void {
		if ( ! is_singular( 'empresa_directorio' ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( $id <= 0 ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_schema_org( $id );
	}

	/**
	 * @param string|null $categoria_slug Slug de taxonomia o null.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_empresas( ?string $categoria_slug = null, int $limit = 20, int $offset = 0 ): array {
		$args = array(
			'post_type'      => 'empresa_directorio',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $categoria_slug ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'categoria_empresa',
					'field'    => 'slug',
					'terms'    => sanitize_title( $categoria_slug ),
				),
			);
		}
		$posts = get_posts( $args );
		$out   = array();
		foreach ( $posts as $p ) {
			$out[] = self::format_empresa( $p );
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_empresa_by_slug( string $slug ): ?array {
		$posts = get_posts(
			array(
				'post_type'      => 'empresa_directorio',
				'name'           => sanitize_title( $slug ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);
		if ( empty( $posts ) ) {
			return null;
		}
		return self::format_empresa( $posts[0] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function format_empresa( WP_Post $post ): array {
		$pid = (int) $post->ID;
		$logo_id   = function_exists( 'get_field' ) ? get_field( 'ed_emp_dir_logo', $pid ) : null;
		$fotos_ids = function_exists( 'get_field' ) ? get_field( 'ed_emp_dir_fotos', $pid ) : array();
		$cats      = get_the_terms( $pid, 'categoria_empresa' );
		if ( is_wp_error( $cats ) || ! is_array( $cats ) ) {
			$cats = array();
		}
		$anuncio = function_exists( 'get_field' ) ? get_field( 'ed_emp_dir_anuncio_activo', $pid ) : false;

		$fotos = array();
		if ( is_array( $fotos_ids ) ) {
			foreach ( $fotos_ids as $fid ) {
				$url = wp_get_attachment_image_url( (int) $fid, 'large' );
				if ( $url ) {
					$fotos[] = $url;
				}
			}
		}

		return array(
			'id'              => $pid,
			'slug'            => $post->post_name,
			'nombre'          => $post->post_title,
			'descripcion'     => function_exists( 'get_field' ) ? (string) get_field( 'ed_emp_dir_descripcion', $pid ) : '',
			'direccion'       => function_exists( 'get_field' ) ? (string) get_field( 'ed_emp_dir_direccion', $pid ) : '',
			'telefono'        => function_exists( 'get_field' ) ? (string) get_field( 'ed_emp_dir_telefono', $pid ) : '',
			'email'           => function_exists( 'get_field' ) ? (string) get_field( 'ed_emp_dir_email', $pid ) : '',
			'web'             => function_exists( 'get_field' ) ? (string) get_field( 'ed_emp_dir_web', $pid ) : '',
			'horario'         => function_exists( 'get_field' ) ? (string) get_field( 'ed_emp_dir_horario', $pid ) : '',
			'logo'            => $logo_id ? wp_get_attachment_image_url( (int) $logo_id, 'medium' ) : null,
			'fotos'           => $fotos,
			'categorias'      => array_map(
				static function ( $t ) {
					return array(
						'nombre' => $t->name,
						'slug'   => $t->slug,
					);
				},
				$cats
			),
			'url'             => get_permalink( $pid ) ?: '',
			'destacada'       => (bool) ( function_exists( 'get_field' ) ? get_field( 'ed_emp_dir_destacada', $pid ) : false ),
			'anuncio_activo'  => (bool) $anuncio,
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_categorias(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'categoria_empresa',
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return array_map(
			static function ( $t ) {
				return array(
					'id'     => (int) $t->term_id,
					'nombre' => $t->name,
					'slug'   => $t->slug,
					'count'  => (int) $t->count,
				);
			},
			$terms
		);
	}

	public static function get_schema_org( int $empresa_id ): string {
		if ( ! function_exists( 'get_field' ) ) {
			return '';
		}
		$nombre    = get_post_field( 'post_title', $empresa_id );
		$direccion = (string) get_field( 'ed_emp_dir_direccion', $empresa_id );
		$telefono  = (string) get_field( 'ed_emp_dir_telefono', $empresa_id );
		$web       = (string) get_field( 'ed_emp_dir_web', $empresa_id );
		$logo_id   = get_field( 'ed_emp_dir_logo', $empresa_id );
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'name'     => $nombre,
			'url'      => get_permalink( $empresa_id ),
		);

		if ( $telefono ) {
			$schema['telephone'] = $telefono;
		}
		if ( $web ) {
			$schema['sameAs'] = array( $web );
		}
		if ( $logo_id ) {
			$img = wp_get_attachment_image_url( (int) $logo_id, 'full' );
			if ( $img ) {
				$schema['image'] = $img;
			}
		}
		if ( $direccion ) {
			$schema['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $direccion,
				'addressLocality' => apply_filters( 'ed_directorio_schema_locality', 'Ondara' ),
				'addressRegion'   => apply_filters( 'ed_directorio_schema_region', 'Alacant' ),
				'addressCountry'  => 'ES',
			);
		}

		return '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			. '</script>' . "\n";
	}

	public static function template_include( string $template ): string {
		if ( is_post_type_archive( 'empresa_directorio' ) || is_tax( 'categoria_empresa' ) ) {
			$file = ED_PLUGIN_DIR . 'templates/directorio-archive.php';
			if ( is_readable( $file ) ) {
				return $file;
			}
		}
		if ( is_singular( 'empresa_directorio' ) ) {
			$file = ED_PLUGIN_DIR . 'templates/empresa-directorio-single.php';
			if ( is_readable( $file ) ) {
				return $file;
			}
		}
		return $template;
	}
}
