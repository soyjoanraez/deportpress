<?php
/**
 * REST API namespace ed/v1.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints panel familiar (Fase 1).
 */
class ED_REST_API {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra rutes.
	 */
	public function register_routes(): void {
		// --- Fase 4: stats abans de /jugador/{id} ---
		register_rest_route(
			'ed/v1',
			'/jugador/(?P<id>\d+)/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_jugador_stats_ranking' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'        => array(
						'required'          => true,
						'validate_callback' => function ( $p ) {
							return is_numeric( $p ) && (int) $p > 0;
						},
					),
					'torneo_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/rankings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rankings_globales' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'tipo'  => array(
						'required' => false,
						'type'     => 'string',
						'default'  => 'goles',
					),
					'limit' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 10,
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/push/suscribir',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_push_suscribir' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'onesignal_id'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => fn( $v ) => ! empty( trim( (string) $v ) ) && strlen( $v ) <= 255,
					),
					'tipo'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'enum'              => array( 'categoria', 'equipo', 'torneo', 'general' ),
					),
					'referencia_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/cuadro',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cuadro_torneo' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/equipos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_equipos_torneo_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/inscribir',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_torneo_inscribir' ),
				'permission_callback' => array( $this, 'is_admin_or_coordinador_rest' ),
				'args'                => array(
					'equipo_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/generar-cuadro',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_generar_cuadro' ),
				'permission_callback' => array( $this, 'is_admin_or_coordinador_rest' ),
				'args'                => array(
					'equipos_ids' => array( 'required' => true, 'type' => 'array' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/rankings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rankings_torneo' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats_torneo_eventos' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/live',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_partido_live_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/eventos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_partido_eventos_poll' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'desde_id' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/evento',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_partido_evento' ),
				'permission_callback' => array( $this, 'is_operador_partido_rest' ),
				'args'                => array(
					'tipo'        => array( 'required' => true, 'type' => 'string' ),
					'jugador_id'  => array( 'required' => false, 'type' => 'integer' ),
					'equipo_id'   => array( 'required' => false, 'type' => 'integer' ),
					'minuto'      => array( 'required' => false, 'type' => 'integer' ),
					'descripcion' => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/jugadores',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_partido_jugadores_rest' ),
				'permission_callback' => array( $this, 'is_operador_partido_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/mvp',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mvp_estado' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/mvp/votar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_mvp_votar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'jugador_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/cronica',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cronica_partido' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/partido/(?P<id>\d+)/cronica/generar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_generar_cronica' ),
				'permission_callback' => array( $this, 'can_manage_cronicas_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/directorio/categorias',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_directorio_categorias' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/directorio',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_directorio_list' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'categoria' => array(
						'required' => false,
						'type'     => 'string',
					),
					'limit'     => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 20,
					),
					'offset'    => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/directorio/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_directorio_empresa' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/publicidad/(?P<posicion>[a-z_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_publicidad_posicion' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/publicidad/(?P<id>\d+)/click',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_publicidad_click' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/nucleo',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_nucleo' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/nucleo/jugadores',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_nucleo_jugadores' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/jugador/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_jugador' ),
				'permission_callback' => array( $this, 'require_login' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/pagos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pagos' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/pagos/stripe/intent',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_stripe_intent' ),
				'permission_callback' => array( $this, 'require_login' ),
				'args'                => array(
					'jugador_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'deporte_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/pagos/stripe/confirmar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirmar_pago_stripe' ),
				'permission_callback' => array( $this, 'require_login' ),
				'args'                => array(
					'payment_intent_id' => array(
						'required' => true,
						'type'     => 'string',
					),
					'jugador_id'        => array(
						'required' => true,
						'type'     => 'integer',
					),
					'deporte_id'        => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/redsys/notificacion',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'ED_Pagos_Redsys', 'procesar_notificacion' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/stripe/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'stripe_webhook_dispatch' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/asistencias/jugador/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_asistencias_jugador' ),
				'permission_callback' => array( $this, 'require_login' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/asistencias/sesion',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_asistencias_sesion' ),
				'permission_callback' => array( $this, 'is_entrenador_rest' ),
				'args'                => array(
					'sesion_id'   => array( 'required' => true, 'type' => 'integer' ),
					'tipo_sesion' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'entrenamiento', 'partido' ),
					),
					'fecha'       => array( 'required' => true, 'type' => 'string' ),
					'registros'   => array( 'required' => true, 'type' => 'array' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/entrenamientos/hoy',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_entrenamientos_hoy' ),
				'permission_callback' => array( $this, 'is_entrenador_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/entrenamiento/(?P<id>\d+)/jugadores',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_jugadores_entrenamiento' ),
				'permission_callback' => array( $this, 'is_entrenador_rest' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/coach/calendario',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_coach_calendario' ),
				'permission_callback' => array( $this, 'is_entrenador_rest' ),
				'args'                => array(
					'fecha'       => array(
						'required' => false,
						'type'     => 'string',
					),
					'tipo_evento' => array(
						'required' => false,
						'type'     => 'string',
					),
					'categoria_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/coach/sesion/(?P<tipo>[a-z_]+)/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_coach_sesion_detail' ),
				'permission_callback' => array( $this, 'is_entrenador_rest' ),
				'args'                => array(
					'tipo' => array(
						'required' => true,
						'type'     => 'string',
					),
					'id'   => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		// --- Fase 6: entrades QR, rifa, bar ---
		register_rest_route(
			'ed/v1',
			'/entradas',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_entradas_usuario_rest' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/entrada/validar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_validar_entrada' ),
				'permission_callback' => array( $this, 'is_staff_entradas_rest' ),
				'args'                => array(
					'token' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/entrada/(?P<token>[a-f0-9]{32})/qr',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_entrada_qr_redirect' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/entradas/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats_entradas_torneo' ),
				'permission_callback' => array( $this, 'is_staff_entradas_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/rifa/ganadores',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rifa_ganadores_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/rifa/sorteo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_rifa_sorteo' ),
				'permission_callback' => array( $this, 'is_admin_coordinador_manage_ed_rest' ),
				'args'                => array(
					'premios' => array( 'required' => true, 'type' => 'array' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/rifa/sorteo-aleatorio',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_rifa_sorteo_aleatorio' ),
				'permission_callback' => array( $this, 'is_admin_coordinador_manage_ed_rest' ),
				'args'                => array(
					'premios_desc' => array( 'required' => true, 'type' => 'array' ),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/bar/productos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bar_productos_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/bar/franjas',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bar_franjas_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/bar/pedido',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_bar_pedido' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'nombre_cliente' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => fn( $v ) => '' !== trim( (string) $v ),
					),
					'franja_horaria' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'metodo_pago'    => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'efectivo', 'tarjeta', 'online' ),
					),
					'lineas'         => array(
						'required' => true,
						'type'     => 'array',
					),
					'notas'          => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'telefono'       => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/torneo/(?P<id>\d+)/bar/pedidos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bar_pedidos_rest' ),
				'permission_callback' => array( $this, 'is_staff_bar_rest' ),
				'args'                => array(
					'franja' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'estado' => array(
						'required' => false,
						'type'     => 'string',
						'enum'     => array( 'pendiente', 'preparando', 'listo', 'entregado', 'cancelado' ),
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/bar/pedido/(?P<id>\d+)/estado',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_bar_pedido_estado' ),
				'permission_callback' => array( $this, 'is_staff_bar_rest' ),
				'args'                => array(
					'estado' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'pendiente', 'preparando', 'listo', 'entregado', 'cancelado' ),
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/bar/mis-pedidos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bar_mis_pedidos' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		// --- Fase 7: FFCV / federació ---
		register_rest_route(
			'ed/v1',
			'/federacion/proximos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fed_proximos' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 10,
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/federacion/resultados',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fed_resultados' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 10,
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/federacion/clasificacion/(?P<categoria_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fed_clasificacion' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ed/v1',
			'/federacion/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_fed_sync' ),
				'permission_callback' => array( $this, 'is_fed_sync_rest' ),
			)
		);

		// --- Fase 8: calendari unificat ---
		register_rest_route(
			'ed/v1',
			'/calendario/semana',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendario_semana' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'fecha'        => array(
						'required' => false,
						'type'     => 'string',
					),
					'categoria_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
					'jugador_id'   => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/calendario/mes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendario_mes' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'year'         => array(
						'required' => false,
						'type'     => 'integer',
					),
					'month'        => array(
						'required' => false,
						'type'     => 'integer',
					),
					'categoria_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/resultados/semana',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_resultados_semana_rest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'fecha' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);

		// --- Fase 9: comunicacions ---
		register_rest_route(
			'ed/v1',
			'/mensajes/no-leidos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mensajes_no_leidos_rest' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/mensajes/enviar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_enviar_mensaje_rest' ),
				'permission_callback' => array( $this, 'is_staff_comunicaciones_rest' ),
				'args'                => array(
					'tipo_destino' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'club', 'deporte', 'categoria', 'nucleo', 'jugador' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'destino_id'   => array(
						'required' => false,
						'type'     => 'integer',
					),
					'asunto'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'cuerpo'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'wp_kses_post',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/mensajes/enviados',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mensajes_enviados_rest' ),
				'permission_callback' => array( $this, 'is_staff_comunicaciones_rest' ),
				'args'                => array(
					'limit' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 30,
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/mensajes/destinos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mensajes_destinos_rest' ),
				'permission_callback' => array( $this, 'is_staff_comunicaciones_rest' ),
				'args'                => array(
					'tipo' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/mensajes/(?P<id>\d+)/leer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_marcar_leido_mensaje_rest' ),
				'permission_callback' => array( $this, 'require_login' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $p ) {
							return is_numeric( $p ) && (int) $p > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'ed/v1',
			'/mensajes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mensajes_rest' ),
				'permission_callback' => array( $this, 'require_login' ),
				'args'                => array(
					'limit' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 20,
					),
				),
			)
		);

		// --- Fase 10: dashboard admin ---
		register_rest_route(
			'ed/v1',
			'/admin/exportar/(?P<tipo>[a-z_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_exportar_csv' ),
				'permission_callback' => array( $this, 'is_dashboard_export_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/admin/kpis',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_kpis_rest' ),
				'permission_callback' => array( $this, 'is_dashboard_view_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/admin/ingresos-mes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_ingresos_mes_rest' ),
				'permission_callback' => array( $this, 'is_dashboard_view_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/admin/inscripciones-deporte',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_inscripciones_deporte_rest' ),
				'permission_callback' => array( $this, 'is_dashboard_view_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/admin/asistencia-categoria',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_asistencia_categoria_rest' ),
				'permission_callback' => array( $this, 'is_dashboard_view_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/admin/impagos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_impagos_rest' ),
				'permission_callback' => array( $this, 'is_dashboard_view_rest' ),
			)
		);

		register_rest_route(
			'ed/v1',
			'/admin/baja-asistencia',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_baja_asistencia_rest' ),
				'permission_callback' => array( $this, 'is_dashboard_view_rest' ),
			)
		);
	}

	/**
	 * Webhook Stripe (exit dins del handler).
	 */
	public function stripe_webhook_dispatch(): void {
		ED_Pagos_Stripe::handle_webhook();
	}

	/**
	 * Només usuaris autenticats (cookie + nonce o altres proveïdors REST).
	 */
	public function require_login(): bool {
		return is_user_logged_in();
	}

	/**
	 * Entrenador, coordinador o administració.
	 * Usa capabilities en lloc de noms de rol per ser robust davant rols compostos.
	 */
	public function is_entrenador_rest(): bool {
		return is_user_logged_in() && current_user_can( 'edit_entrenamientos' );
	}

	/**
	 * Administració, coordinador o entrenador (missatges a famílies).
	 */
	public function is_staff_comunicaciones_rest(): bool {
		return is_user_logged_in() && current_user_can( 'manage_escuela_deportiva' );
	}

	public function is_dashboard_view_rest(): bool {
		return is_user_logged_in() && ED_Dashboard_Admin::current_user_can_view_dashboard();
	}

	public function is_dashboard_export_rest(): bool {
		return is_user_logged_in() && ED_Dashboard_Admin::current_user_can_export();
	}

	/**
	 * GET /ed/v1/nucleo
	 */
	public function get_nucleo( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( ! $n ) {
			return new WP_Error( 'ed_no_nucleo', __( 'No s’ha trobat nucli familiar per a aquest usuari.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'id'     => $n->ID,
				'titulo' => get_the_title( $n->ID ),
			),
			200
		);
	}

	/**
	 * GET /ed/v1/nucleo/jugadores
	 */
	public function get_nucleo_jugadores( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( ! $n && ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'ed_no_nucleo', __( 'No s’ha trobat nucli familiar per a aquest usuari.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}

		if ( user_can( $user, 'manage_options' ) && ! $n ) {
			return new WP_REST_Response( array(), 200 );
		}

		$ids = ED_Nucleo_Repository::get_jugador_ids_for_nucleo( $n->ID );
		$out = array();
		foreach ( $ids as $jid ) {
			$item = $this->serialize_jugador_public( (int) $jid );
			if ( $item ) {
				$out[] = $item;
			}
		}

		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * GET /ed/v1/jugador/{id}
	 */
	public function get_jugador( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user      = wp_get_current_user();
		$jugador_id = (int) $request->get_param( 'id' );

		if ( get_post_type( $jugador_id ) !== 'jugador' ) {
			return new WP_Error( 'ed_not_found', __( 'Jugador no trobat.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}

		if ( ! $this->user_can_access_jugador( (int) $user->ID, $jugador_id ) ) {
			return new WP_Error( 'ed_forbidden', __( 'No tens permís per veure aquest jugador.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}

		$data = $this->serialize_jugador_detail( $jugador_id );
		if ( ! $data ) {
			return new WP_Error( 'ed_not_found', __( 'Jugador no trobat.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /ed/v1/pagos
	 */
	public function get_pagos( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( ! $n ) {
			return new WP_Error( 'ed_no_nucleo', __( 'Sense nucli familiar.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}
		$plazos = ED_Pagos::get_plazos_nucleo( $n->ID );
		$out    = array();
		foreach ( $plazos as $p ) {
			$factura = null;
			if ( ! empty( $p->wc_order_id ) && function_exists( 'wc_get_order' ) ) {
				$ord = wc_get_order( (int) $p->wc_order_id );
				if ( $ord ) {
					$factura = $ord->get_view_order_url();
				}
			}
			$out[] = array(
				'id'             => (int) $p->id,
				'jugador_id'     => (int) $p->jugador_id,
				'deporte_nombre' => $p->deporte_nombre ?? '',
				'plazo'          => (int) $p->plazo,
				'importe'        => (float) $p->importe,
				'estado'         => (string) $p->estado,
				'fecha_prevista' => $p->fecha_prevista,
				'fecha_pagado'   => $p->fecha_pagado,
				'wc_order_id'    => (int) ( $p->wc_order_id ?? 0 ),
				'factura_url'    => $factura,
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * POST /ed/v1/pagos/stripe/intent
	 */
	public function create_stripe_intent( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( ! $n ) {
			return new WP_Error( 'ed_no_nucleo', __( 'Sense nucli familiar.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}
		$jugador_id = (int) $request->get_param( 'jugador_id' );
		$deporte_id = (int) $request->get_param( 'deporte_id' );
		if ( ! ED_Nucleo_Repository::jugador_matches_nucleo_and_deporte( $jugador_id, $n->ID, $deporte_id ) ) {
			return new WP_Error( 'ed_forbidden', __( 'Dades no vàlides.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}
		if ( ! function_exists( 'get_field' ) ) {
			return new WP_Error( 'ed_acf', __( 'ACF no disponible.', 'escuela-deportiva-core' ), array( 'status' => 500 ) );
		}
		$total   = (float) get_field( 'ed_dep_importe_total', $deporte_id );
		$importe = round( $total / 2, 2 );
		if ( $importe <= 0 ) {
			return new WP_Error( 'ed_importe', __( 'Import no configurat.', 'escuela-deportiva-core' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( \Stripe\StripeClient::class ) ) {
			return new WP_Error( 'ed_stripe', __( 'Stripe SDK no instal·lat.', 'escuela-deportiva-core' ), array( 'status' => 500 ) );
		}
		try {
			$data = ED_Pagos_Stripe::crear_payment_intent(
				$importe,
				'eur',
				array(
					'ed_jugador_id'  => (string) $jugador_id,
					'ed_deporte_id'  => (string) $deporte_id,
					'ed_nucleo_id'   => (string) $n->ID,
				)
			);
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'ed_stripe', $e->getMessage(), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * POST /ed/v1/pagos/stripe/confirmar
	 */
	public function confirmar_pago_stripe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( ! $n ) {
			return new WP_Error( 'ed_no_nucleo', __( 'Sense nucli familiar.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}
		$pi_id      = sanitize_text_field( (string) $request->get_param( 'payment_intent_id' ) );
		$jugador_id = (int) $request->get_param( 'jugador_id' );
		$deporte_id = (int) $request->get_param( 'deporte_id' );

		if ( ! ED_Nucleo_Repository::jugador_matches_nucleo_and_deporte( $jugador_id, $n->ID, $deporte_id ) ) {
			return new WP_Error( 'ed_forbidden', __( 'Dades no vàlides.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}

		// Guard d'idempotència: comprova si el payment_intent_id ja ha estat processat.
		// Evita doble processament si el frontend crida l'endpoint dues vegades.
		global $wpdb;
		$pi_already = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ed_pagos_plazos
				WHERE token_pago LIKE %s AND nucleo_id = %d",
				'%' . $wpdb->esc_like( $pi_id ) . '%',
				$n->ID
			)
		);
		if ( $pi_already > 0 ) {
			return new WP_Error(
				'ed_duplicate',
				__( 'Aquest pagament ja ha estat processat.', 'escuela-deportiva-core' ),
				array( 'status' => 409 )
			);
		}

		if ( ! class_exists( \Stripe\StripeClient::class ) ) {
			return new WP_Error( 'ed_stripe', __( 'Stripe SDK no instal·lat.', 'escuela-deportiva-core' ), array( 'status' => 500 ) );
		}
		$confirm = ED_Pagos_Stripe::confirmar_pago( $pi_id );
		if ( empty( $confirm['ok'] ) ) {
			return new WP_Error( 'ed_stripe', __( 'Pagament no confirmat.', 'escuela-deportiva-core' ), array( 'status' => 400 ) );
		}
		$pm  = (string) ( $confirm['payment_method'] ?? '' );
		$cus = (string) ( $confirm['customer_id'] ?? '' );
		$tok = ( $pm && $cus ) ? $pm . '|' . $cus : null;
		$res = ED_Pagos::crear_plazos( $n->ID, $jugador_id, $deporte_id, 'stripe', $tok );
		if ( ! empty( $res['error'] ) ) {
			return new WP_Error( 'ed_plazos', $res['error'], array( 'status' => 400 ) );
		}
		ED_Pagos::marcar_pagado( (int) $res['plazo1_id'], 'stripe', $pi_id );
		return new WP_REST_Response(
			array(
				'ok'     => true,
				'plazos' => $res,
			),
			200
		);
	}

	/**
	 * GET /ed/v1/asistencias/jugador/{id}
	 */
	public function get_asistencias_jugador( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user       = wp_get_current_user();
		$jugador_id = (int) $request->get_param( 'id' );
		if ( ! $this->user_can_access_jugador( (int) $user->ID, $jugador_id ) ) {
			return new WP_Error( 'ed_forbidden', __( 'Sense permís.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}
		return new WP_REST_Response(
			array(
				'jugador_id'         => $jugador_id,
				'pct_entrenamientos' => ED_Asistencias::get_porcentaje( $jugador_id, 'entrenamiento' ),
				'pct_partidos'       => ED_Asistencias::get_porcentaje( $jugador_id, 'partido' ),
				'registros'          => ED_Asistencias::get_por_jugador( $jugador_id ),
			),
			200
		);
	}

	/**
	 * POST /ed/v1/asistencias/sesion
	 */
	public function post_asistencias_sesion( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sesion_id   = (int) $request->get_param( 'sesion_id' );
		$tipo_sesion = sanitize_text_field( (string) $request->get_param( 'tipo_sesion' ) );
		$fecha       = sanitize_text_field( (string) $request->get_param( 'fecha' ) );
		$registros   = $request->get_param( 'registros' );
		if ( 'entrenamiento' !== $tipo_sesion && 'partido' !== $tipo_sesion ) {
			return new WP_Error( 'ed_tipo', __( 'Tipus de sessió no vàlid.', 'escuela-deportiva-core' ), array( 'status' => 400 ) );
		}
		if ( 'entrenamiento' === $tipo_sesion && get_post_type( $sesion_id ) !== 'entrenamiento' ) {
			return new WP_Error( 'ed_sesion', __( 'Sessió no trobada.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}
		if ( 'partido' === $tipo_sesion && get_post_type( $sesion_id ) !== 'partido' ) {
			return new WP_Error( 'ed_sesion', __( 'Partit no trobat.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}
		$uid = (int) wp_get_current_user()->ID;
		if ( 'entrenamiento' === $tipo_sesion && ! $this->user_can_manage_entrenamiento_session( $uid, $sesion_id ) ) {
			return new WP_Error( 'ed_forbidden', __( 'Sense permís per a aquesta sessió.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}
		if ( 'partido' === $tipo_sesion && ! $this->user_can_manage_partido_session( $uid, $sesion_id ) ) {
			return new WP_Error( 'ed_forbidden', __( 'Sense permís per a aquest partit.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}
		if ( function_exists( 'get_field' ) && get_field( 'ed_ent_asistencia_cerrada', $sesion_id ) && 'entrenamiento' === $tipo_sesion ) {
			return new WP_Error( 'ed_cerrada', __( 'La llista d’assistència està tancada.', 'escuela-deportiva-core' ), array( 'status' => 400 ) );
		}
		if ( ! is_array( $registros ) ) {
			return new WP_Error( 'ed_registros', __( 'Registres no vàlids.', 'escuela-deportiva-core' ), array( 'status' => 400 ) );
		}
		ED_Asistencias::guardar_sesion( $sesion_id, $tipo_sesion, $fecha, $registros );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * GET /ed/v1/entrenamientos/hoy
	 */
	public function get_entrenamientos_hoy( WP_REST_Request $request ): WP_REST_Response {
		$today = wp_date( 'Y-m-d' );
		$uid   = (int) get_current_user_id();

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => 'ed_ent_fecha_hora',
				'value'   => $today,
				'compare' => 'LIKE',
			),
		);

		// Entrenadors sense capability de coordinador/admin: sessions on són entrenador assignat o categoria del seu esport (ed_dep_entrenadores).
		$pure_entrenador = user_can( $uid, 'edit_entrenamientos' )
			&& ! user_can( $uid, 'manage_escuela_deportiva' );
		if ( $pure_entrenador ) {
			$cats = class_exists( 'ED_Comunicaciones', false )
				? ED_Comunicaciones::get_categoria_ids_entrenador( $uid )
				: array();
			$or = array(
				'relation' => 'OR',
				array(
					'key'   => 'ed_ent_entrenador',
					'value' => $uid,
				),
			);
			if ( array() !== $cats ) {
				$or[] = array(
					'key'     => 'ed_ent_categoria',
					'value'   => array_map( 'intval', $cats ),
					'compare' => 'IN',
					'type'    => 'NUMERIC',
				);
			}
			$meta_query[] = $or;
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'entrenamiento',
				'post_status'    => 'any',
				'posts_per_page' => 30,
				'orderby'        => 'meta_value',
				'meta_key'       => 'ed_ent_fecha_hora',
				'order'          => 'ASC',
				'meta_query'     => $meta_query,
			)
		);

		$out = array();
		foreach ( $q->posts as $p ) {
			$fh = function_exists( 'get_field' ) ? (string) get_field( 'ed_ent_fecha_hora', $p->ID ) : '';
			$out[] = array(
				'id'         => $p->ID,
				'titulo'     => get_the_title( $p->ID ),
				'fecha_hora' => $fh,
				'lugar'      => function_exists( 'get_field' ) ? (string) get_field( 'ed_ent_lugar', $p->ID ) : '',
			);
		}

		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * GET /ed/v1/entrenamiento/{id}/jugadores
	 */
	public function get_jugadores_entrenamiento( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sid = (int) $request->get_param( 'id' );
		if ( get_post_type( $sid ) !== 'entrenamiento' ) {
			return new WP_Error( 'ed_not_found', __( 'Sessió no trobada.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
		}
		$uid = (int) wp_get_current_user()->ID;
		if ( ! $this->user_can_manage_entrenamiento_session( $uid, $sid ) ) {
			return new WP_Error( 'ed_forbidden', __( 'Sense permís.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
		}
		$items = $this->query_jugadores_entrenamiento_rows( $sid );
		return new WP_REST_Response( $items, 200 );
	}

	public function get_coach_calendario( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$uid   = (int) get_current_user_id();
		$fecha = $request->get_param( 'fecha' );
		$fecha = $fecha ? sanitize_text_field( (string) $fecha ) : wp_date( 'Y-m-d' );

		try {
			$tz    = wp_timezone();
			$d     = new DateTimeImmutable( $fecha . ' 12:00:00', $tz );
			$lunes = $d->modify( 'monday this week' );
		} catch ( Exception $e ) {
			$d     = new DateTimeImmutable( 'now', wp_timezone() );
			$lunes = $d->modify( 'monday this week' );
		}

		$domingo     = $lunes->modify( '+6 days' );
		$fecha_inicio = $lunes->format( 'Y-m-d' );
		$fecha_fin    = $domingo->format( 'Y-m-d' );
		$categoria_id = (int) $request->get_param( 'categoria_id' );
		$tipo_evento  = sanitize_key( (string) $request->get_param( 'tipo_evento' ) );

		$events = ED_Calendario::get_eventos_semana(
			$fecha_inicio,
			$fecha_fin,
			$categoria_id > 0 ? $categoria_id : null
		);
		$events = $this->filter_events_for_coach( $events, $uid );
		if ( in_array( $tipo_evento, array( 'entrenamiento', 'partido', 'partido_torneo', 'partido_liga' ), true ) ) {
			$events = array_values(
				array_filter(
					$events,
					static function ( array $event ) use ( $tipo_evento ): bool {
						if ( 'partido' === $tipo_evento ) {
							return in_array( (string) $event['tipo'], array( 'partido_torneo', 'partido_liga' ), true );
						}
						return (string) $event['tipo'] === $tipo_evento;
					}
				)
			);
		}

		$categorias = $this->get_coach_category_filter_options( $uid );
		$out        = array();
		foreach ( $events as $event ) {
			$out[] = $this->format_coach_calendar_event( $event, $uid );
		}

		return new WP_REST_Response(
			array(
				'semana_inicio' => $fecha_inicio,
				'semana_fin'    => $fecha_fin,
				'eventos'       => $out,
				'categorias'    => $categorias,
			),
			200
		);
	}

	public function get_coach_sesion_detail( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$tipo = sanitize_key( (string) $request->get_param( 'tipo' ) );
		$id   = (int) $request->get_param( 'id' );
		$uid  = (int) get_current_user_id();

		if ( 'entrenamiento' === $tipo ) {
			if ( get_post_type( $id ) !== 'entrenamiento' ) {
				return new WP_Error( 'ed_not_found', __( 'Sessió no trobada.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
			}
			if ( ! $this->user_can_manage_entrenamiento_session( $uid, $id ) ) {
				return new WP_Error( 'ed_forbidden', __( 'Sense permís.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
			}
			return new WP_REST_Response( $this->build_entrenamiento_session_detail( $id ), 200 );
		}

		if ( 'partido' === $tipo ) {
			if ( get_post_type( $id ) !== 'partido' ) {
				return new WP_Error( 'ed_not_found', __( 'Partit no trobat.', 'escuela-deportiva-core' ), array( 'status' => 404 ) );
			}
			if ( ! $this->user_can_manage_partido_session( $uid, $id ) ) {
				return new WP_Error( 'ed_forbidden', __( 'Sense permís.', 'escuela-deportiva-core' ), array( 'status' => 403 ) );
			}
			return new WP_REST_Response( $this->build_partido_session_detail( $id ), 200 );
		}

		return new WP_Error( 'ed_invalid_tipo', __( 'Tipus de sessió no vàlid.', 'escuela-deportiva-core' ), array( 'status' => 400 ) );
	}

	/**
	 * Admin: accés a qualsevol jugador. Adult: només del seu nucli.
	 */
	private function user_can_access_jugador( int $user_id, int $jugador_id ): bool {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$n = ED_Nucleo_Repository::find_for_user( $user_id );
		if ( ! $n ) {
			return false;
		}

		return ED_Nucleo_Repository::jugador_belongs_to_nucleo( $jugador_id, $n->ID );
	}

	/**
	 * Edició de llista d’assistència per a una sessió d’entrenament.
	 * Usa capabilities per ser robust davant futurs canvis de rols.
	 */
	private function user_can_manage_entrenamiento_session( int $user_id, int $sesion_id ): bool {
		// Admins i coordinadors poden gestionar qualsevol sessió.
		if ( user_can( $user_id, 'manage_escuela_deportiva' ) ) {
			return true;
		}
		if ( ! user_can( $user_id, 'edit_entrenamientos' ) ) {
			return false;
		}
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}
		$coach = (int) get_field( 'ed_ent_entrenador', $sesion_id );
		if ( $coach === $user_id ) {
			return true;
		}
		$cat = (int) get_field( 'ed_ent_categoria', $sesion_id, false );
		return class_exists( 'ED_Comunicaciones', false )
			&& ED_Comunicaciones::user_entrenador_deporte_de_categoria( $user_id, $cat );
	}

	private function user_can_manage_partido_session( int $user_id, int $partido_id ): bool {
		if ( user_can( $user_id, 'manage_escuela_deportiva' ) ) {
			return true;
		}
		if ( ! user_can( $user_id, 'edit_entrenamientos' ) || ! function_exists( 'get_field' ) ) {
			return false;
		}

		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$cats         = array_filter(
			array(
				$local_id > 0 ? (int) get_field( 'ed_eq_categoria', $local_id, false ) : 0,
				$visitante_id > 0 ? (int) get_field( 'ed_eq_categoria', $visitante_id, false ) : 0,
			)
		);

		if ( array() === $cats ) {
			return false;
		}

		$coach_cats = class_exists( 'ED_Comunicaciones', false )
			? ED_Comunicaciones::get_categoria_ids_entrenador( $user_id )
			: array();

		return array() !== array_intersect( array_map( 'intval', $cats ), array_map( 'intval', $coach_cats ) );
	}

	/**
	 * @return array<int, array{id:int,nombre:string,apellidos:string}>
	 */
	private function query_jugadores_entrenamiento_rows( int $sesion_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}
		$cat = (int) get_field( 'ed_ent_categoria', $sesion_id );
		$eq  = (int) get_field( 'ed_ent_equipo', $sesion_id );
		if ( $cat <= 0 ) {
			return array();
		}
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'   => 'ed_jugador_categoria',
				'value' => $cat,
			),
		);
		if ( $eq > 0 ) {
			$meta_query[] = array(
				'key'   => 'ed_jugador_equipo',
				'value' => $eq,
			);
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'jugador',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => $meta_query,
			)
		);
		$out = array();
		foreach ( $q->posts as $p ) {
			$out[] = array(
				'id'        => $p->ID,
				'nombre'    => (string) get_field( 'ed_jugador_nombre', $p->ID ),
				'apellidos' => (string) get_field( 'ed_jugador_apellidos', $p->ID ),
			);
		}
		return $out;
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private function query_jugadores_partido_rows( int $partido_id ): array {
		$plantillas = ED_Partidos_Eventos::get_jugadores_partido( $partido_id );
		$out        = array();
		foreach ( $plantillas as $rol => $bloque ) {
			$equipo_nombre = (string) ( $bloque['equipo_nombre'] ?? '' );
			$equipo_id     = isset( $bloque['equipo_id'] ) ? (int) $bloque['equipo_id'] : 0;
			$jugadores     = isset( $bloque['jugadores'] ) && is_array( $bloque['jugadores'] ) ? $bloque['jugadores'] : array();
			foreach ( $jugadores as $jugador ) {
				$nombre = (string) ( $jugador['nombre'] ?? '' );
				if ( '' !== trim( $nombre ) ) {
					$parts     = preg_split( '/\s+/', trim( $nombre ) );
					$apellidos = '';
					if ( is_array( $parts ) && count( $parts ) > 1 ) {
						$nombre    = (string) array_shift( $parts );
						$apellidos = implode( ' ', $parts );
					}
				} else {
					$apellidos = '';
				}
				$out[] = array(
					'id'           => (int) ( $jugador['id'] ?? 0 ),
					'nombre'       => $nombre,
					'apellidos'    => $apellidos,
					'equipo'       => $equipo_nombre,
					'equipo_id'    => $equipo_id,
					'lado'         => $rol,
					'dorsal'       => isset( $jugador['dorsal'] ) ? (int) $jugador['dorsal'] : 0,
				);
			}
		}

		usort(
			$out,
			static function ( array $a, array $b ): int {
				$team_cmp = strcmp( (string) $a['equipo'], (string) $b['equipo'] );
				if ( 0 !== $team_cmp ) {
					return $team_cmp;
				}
				return strcmp(
					trim( (string) $a['nombre'] . ' ' . (string) $a['apellidos'] ),
					trim( (string) $b['nombre'] . ' ' . (string) $b['apellidos'] )
				);
			}
		);

		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $events
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_events_for_coach( array $events, int $user_id ): array {
		if ( user_can( $user_id, 'manage_escuela_deportiva' ) ) {
			return $events;
		}

		$cats = class_exists( 'ED_Comunicaciones', false )
			? ED_Comunicaciones::get_categoria_ids_entrenador( $user_id )
			: array();
		$cats = array_map( 'intval', $cats );

		return array_values(
			array_filter(
				$events,
				static function ( array $event ) use ( $cats ): bool {
					$cat_id = isset( $event['categoria_id'] ) ? (int) $event['categoria_id'] : 0;
					return $cat_id > 0 && in_array( $cat_id, $cats, true );
				}
			)
		);
	}

	/**
	 * @return array<int,array{id:int,titulo:string}>
	 */
	private function get_coach_category_filter_options( int $user_id ): array {
		if ( user_can( $user_id, 'manage_escuela_deportiva' ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'categoria',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
		} else {
			$ids = class_exists( 'ED_Comunicaciones', false )
				? ED_Comunicaciones::get_categoria_ids_entrenador( $user_id )
				: array();
		}

		$out = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $cid ) {
			if ( $cid <= 0 ) {
				continue;
			}
			$out[] = array(
				'id'     => $cid,
				'titulo' => get_the_title( $cid ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $event
	 * @return array<string,mixed>
	 */
	private function format_coach_calendar_event( array $event, int $user_id ): array {
		$tipo           = (string) ( $event['tipo'] ?? '' );
		$source_id      = isset( $event['source_id'] ) ? (int) $event['source_id'] : 0;
		$attendance_ok  = false;

		if ( 'entrenamiento' === $tipo ) {
			$attendance_ok = $source_id > 0 && $this->user_can_manage_entrenamiento_session( $user_id, $source_id );
		} elseif ( 'partido_torneo' === $tipo ) {
			$attendance_ok = $source_id > 0 && $this->user_can_manage_partido_session( $user_id, $source_id );
		}

		return array(
			'id'                 => (string) ( $event['id'] ?? '' ),
			'tipo'               => $tipo,
			'source_id'          => $source_id,
			'titulo'             => (string) ( $event['titulo'] ?? '' ),
			'fecha_hora'         => (string) ( $event['fecha_hora'] ?? '' ),
			'lugar'              => (string) ( $event['lugar'] ?? '' ),
			'categoria_id'       => isset( $event['categoria_id'] ) ? (int) $event['categoria_id'] : 0,
			'categoria'          => isset( $event['categoria'] ) ? (string) $event['categoria'] : '',
			'estado'             => (string) ( $event['estado'] ?? '' ),
			'marcador'           => (string) ( $event['marcador'] ?? '' ),
			'color'              => (string) ( $event['color'] ?? '' ),
			'icono'              => (string) ( $event['icono'] ?? '' ),
			'url'                => isset( $event['url'] ) ? (string) $event['url'] : '',
			'can_take_attendance'=> $attendance_ok,
			'attendance_type'    => 'partido_torneo' === $tipo ? 'partido' : ( 'entrenamiento' === $tipo ? 'entrenamiento' : '' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_entrenamiento_session_detail( int $sesion_id ): array {
		$items   = $this->query_jugadores_entrenamiento_rows( $sesion_id );
		$map     = ED_Asistencias::get_mapa_sesion( $sesion_id, 'entrenamiento', wp_list_pluck( $items, 'id' ) );
		$fh      = function_exists( 'get_field' ) ? (string) get_field( 'ed_ent_fecha_hora', $sesion_id ) : '';
		$lugar   = function_exists( 'get_field' ) ? (string) get_field( 'ed_ent_lugar', $sesion_id ) : '';
		$cerrada = function_exists( 'get_field' ) ? (bool) get_field( 'ed_ent_asistencia_cerrada', $sesion_id ) : false;

		foreach ( $items as &$item ) {
			$estado_data      = $map[ (int) $item['id'] ] ?? array();
			$item['estado']   = isset( $estado_data['estado'] ) ? (string) $estado_data['estado'] : 'asistio';
			$item['motivo']   = isset( $estado_data['motivo'] ) ? (string) $estado_data['motivo'] : '';
		}

		return array(
			'tipo'       => 'entrenamiento',
			'id'         => $sesion_id,
			'titulo'     => get_the_title( $sesion_id ),
			'fecha_hora' => $fh,
			'fecha'      => $fh ? substr( $fh, 0, 10 ) : wp_date( 'Y-m-d' ),
			'lugar'      => $lugar,
			'cerrada'    => $cerrada,
			'jugadores'  => $items,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_partido_session_detail( int $partido_id ): array {
		$items = $this->query_jugadores_partido_rows( $partido_id );
		$map   = ED_Asistencias::get_mapa_sesion( $partido_id, 'partido', wp_list_pluck( $items, 'id' ) );
		$fh    = function_exists( 'get_field' ) ? (string) get_field( ED_Torneos::PAR_FECHA_HORA, $partido_id ) : '';
		$lugar = function_exists( 'get_field' ) ? (string) get_field( ED_Torneos::PAR_LUGAR, $partido_id ) : '';

		foreach ( $items as &$item ) {
			$estado_data      = $map[ (int) $item['id'] ] ?? array();
			$item['estado']   = isset( $estado_data['estado'] ) ? (string) $estado_data['estado'] : 'convocado';
			$item['motivo']   = isset( $estado_data['motivo'] ) ? (string) $estado_data['motivo'] : '';
		}

		return array(
			'tipo'       => 'partido',
			'id'         => $partido_id,
			'titulo'     => get_the_title( $partido_id ),
			'fecha_hora' => $fh,
			'fecha'      => $fh ? substr( $fh, 0, 10 ) : wp_date( 'Y-m-d' ),
			'lugar'      => $lugar,
			'cerrada'    => false,
			'jugadores'  => $items,
		);
	}

	/**
	 * Resum per a llistat (panel).
	 *
	 * @return array<string,mixed>|null
	 */
	private function serialize_jugador_public( int $id ): ?array {
		if ( get_post_type( $id ) !== 'jugador' ) {
			return null;
		}

		$has_acf = function_exists( 'get_field' );
		$foto_id = $has_acf ? (int) get_field( 'ed_jugador_foto', $id ) : 0;
		$foto    = $foto_id ? wp_get_attachment_image_url( $foto_id, 'medium' ) : null;
		$dep_id  = $has_acf ? (int) get_field( 'ed_jugador_deporte', $id ) : 0;
		$cat_id  = $has_acf ? (int) get_field( 'ed_jugador_categoria', $id ) : 0;

		return array(
			'id'        => $id,
			'nombre'    => $has_acf ? (string) get_field( 'ed_jugador_nombre', $id ) : '',
			'apellidos' => $has_acf ? (string) get_field( 'ed_jugador_apellidos', $id ) : '',
			'foto_url'  => $foto,
			'deporte'   => $this->term_like_post( $dep_id ),
			'categoria' => $this->term_like_post( $cat_id ),
			'estado'    => $has_acf ? (string) get_field( 'ed_jugador_estado', $id ) : '',
		);
	}

	/**
	 * Fitxa completa (autoritzada).
	 *
	 * @return array<string,mixed>|null
	 */
	private function serialize_jugador_detail( int $id ): ?array {
		$base = $this->serialize_jugador_public( $id );
		if ( ! $base ) {
			return null;
		}

		$eq_id = function_exists( 'get_field' ) ? (int) get_field( 'ed_jugador_equipo', $id ) : 0;

		$base['fecha_nacimiento'] = function_exists( 'get_field' ) ? (string) get_field( 'ed_jugador_fecha_nacimiento', $id ) : '';
		$base['posicion']         = function_exists( 'get_field' ) ? (string) get_field( 'ed_jugador_posicion', $id ) : '';
		$base['dorsal']           = function_exists( 'get_field' ) ? (int) get_field( 'ed_jugador_dorsal', $id ) : 0;
		$base['equipo']            = $this->term_like_post( $eq_id );

		return $base;
	}

	/**
	 * @return array{id:int,titulo:string}|null
	 */
	private function term_like_post( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}
		$t = get_the_title( $post_id );
		if ( '' === $t ) {
			return null;
		}
		return array(
			'id'     => $post_id,
			'titulo' => $t,
		);
	}

	public function is_admin_or_coordinador_rest(): bool {
		return is_user_logged_in() && current_user_can( 'manage_escuela_deportiva' );
	}

	/**
	 * Administració, coordinador o capability del plugin (sorteig rifa, etc.).
	 */
	public function is_admin_coordinador_manage_ed_rest(): bool {
		return is_user_logged_in() && current_user_can( 'manage_escuela_deportiva' );
	}

	/**
	 * Personal de porta: operador, coordinador o administració.
	 * Operador té edit_partidos; coordinador té manage_escuela_deportiva.
	 */
	public function is_staff_entradas_rest(): bool {
		return is_user_logged_in()
			&& ( current_user_can( 'manage_escuela_deportiva' ) || current_user_can( 'edit_partidos' ) );
	}

	/**
	 * Bar: coordinador o administració.
	 */
	public function is_staff_bar_rest(): bool {
		return is_user_logged_in() && current_user_can( 'manage_escuela_deportiva' );
	}

	/**
	 * Forçar sync FFCV des de REST.
	 */
	public function is_fed_sync_rest(): bool {
		return is_user_logged_in()
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'manage_escuela_deportiva' ) );
	}

	public function is_operador_partido_rest( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$partido_id = (int) $request->get_param( 'id' );
		if ( get_post_type( $partido_id ) !== 'partido' || ! function_exists( 'get_field' ) ) {
			return false;
		}
		$op = (int) get_field( ED_Torneos::PAR_OPERADOR, $partido_id, false );
		return $op > 0 && $op === (int) get_current_user_id();
	}

	public function get_jugador_stats_ranking( WP_REST_Request $request ): WP_REST_Response {
		$jid = (int) $request->get_param( 'id' );
		$tid = $request->get_param( 'torneo_id' );
		$tid = null !== $tid && '' !== $tid ? (int) $tid : null;
		return new WP_REST_Response(
			array(
				'jugador_id' => $jid,
				'stats'      => ED_Rankings::get_stats_jugador( $jid, $tid ),
			),
			200
		);
	}

	public function get_rankings_globales( WP_REST_Request $request ): WP_REST_Response {
		$tipo  = sanitize_key( (string) $request->get_param( 'tipo' ) );
		$limit = min( 50, max( 1, (int) $request->get_param( 'limit' ) ) );
		if ( ! in_array( $tipo, array( 'goles', 'mvp', 'amarillas', 'rojas' ), true ) ) {
			$tipo = 'goles';
		}
		return new WP_REST_Response( ED_Rankings::get_ranking( $tipo, null, $limit ), 200 );
	}

	public function get_rankings_torneo( WP_REST_Request $request ): WP_REST_Response {
		$tid = (int) $request->get_param( 'id' );
		return new WP_REST_Response(
			array(
				'goles'     => ED_Rankings::get_ranking( 'goles', $tid ),
				'mvp'       => ED_Rankings::get_ranking( 'mvp', $tid ),
				'amarillas' => ED_Rankings::get_ranking( 'amarillas', $tid ),
				'rojas'     => ED_Rankings::get_ranking( 'rojas', $tid ),
			),
			200
		);
	}

	public function get_stats_torneo_eventos( WP_REST_Request $request ): WP_REST_Response {
		$tid = (int) $request->get_param( 'id' );
		return new WP_REST_Response(
			array(
				'goleadores' => ED_Partidos_Eventos::get_goleadores( $tid ),
				'tarjetas'   => ED_Partidos_Eventos::get_tarjetas( $tid ),
			),
			200
		);
	}

	public function post_push_suscribir( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$tipo = sanitize_key( (string) $request->get_param( 'tipo' ) );
		if ( ! in_array( $tipo, array( 'partido', 'equipo', 'torneo', 'categoria', 'nucleo' ), true ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'tipo' ), 400 );
		}
		$oid = sanitize_text_field( (string) $request->get_param( 'onesignal_id' ) );
		if ( strlen( $oid ) < 8 ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$ref = (int) $request->get_param( 'referencia_id' );
		$wpdb->replace(
			$wpdb->prefix . 'ed_push_suscripciones',
			array(
				'user_id'         => is_user_logged_in() ? (int) get_current_user_id() : 0,
				'onesignal_id'    => $oid,
				'tipo'            => $tipo,
				'referencia_id'   => $ref,
			),
			array( '%d', '%s', '%s', '%d' )
		);
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function get_cuadro_torneo( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( ED_Torneos::get_cuadro( (int) $request->get_param( 'id' ) ), 200 );
	}

	public function get_equipos_torneo_rest( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( ED_Torneos::get_equipos( (int) $request->get_param( 'id' ) ), 200 );
	}

	public function post_torneo_inscribir( WP_REST_Request $request ): WP_REST_Response {
		$ok = ED_Torneos::inscribir_equipo( (int) $request->get_param( 'id' ), (int) $request->get_param( 'equipo_id' ) );
		return new WP_REST_Response( array( 'ok' => $ok ), $ok ? 200 : 400 );
	}

	public function post_generar_cuadro( WP_REST_Request $request ): WP_REST_Response {
		$res = ED_Torneos::generar_cuadro_eliminatorio(
			(int) $request->get_param( 'id' ),
			(array) $request->get_param( 'equipos_ids' )
		);
		$err = isset( $res['error'] );
		return new WP_REST_Response( $res, $err ? 400 : 200 );
	}

	public function get_partido_live_rest( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			ED_Partidos_Eventos::get_partido_live( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function get_partido_eventos_poll( WP_REST_Request $request ): WP_REST_Response {
		$partido_id = (int) $request->get_param( 'id' );
		$desde_id   = (int) $request->get_param( 'desde_id' );
		if ( ! function_exists( 'get_field' ) ) {
			return new WP_REST_Response( array(), 200 );
		}
		$estado   = (string) get_field( ED_Torneos::PAR_ESTADO, $partido_id );
		$marcador = array(
			'local'      => (int) get_field( ED_Torneos::PAR_GOLES_LOCAL, $partido_id ),
			'visitante'  => (int) get_field( ED_Torneos::PAR_GOLES_VIS, $partido_id ),
			'minuto'     => (int) get_field( ED_Torneos::PAR_MINUTO, $partido_id ),
			'estado'     => $estado,
			'goles_local' => (int) get_field( ED_Torneos::PAR_GOLES_LOCAL, $partido_id ),
			'goles_visitante' => (int) get_field( ED_Torneos::PAR_GOLES_VIS, $partido_id ),
			'minuto_actual' => (int) get_field( ED_Torneos::PAR_MINUTO, $partido_id ),
		);
		$eventos = $desde_id > 0
			? ED_Partidos_Eventos::get_eventos_desde( $partido_id, $desde_id )
			: ED_Partidos_Eventos::get_eventos( $partido_id );
		$ultimo_id = $desde_id;
		foreach ( $eventos as $e ) {
			if ( isset( $e['id'] ) ) {
				$ultimo_id = max( $ultimo_id, (int) $e['id'] );
			}
		}
		return new WP_REST_Response(
			array(
				'marcador'  => $marcador,
				'eventos'   => $eventos,
				'ultimo_id' => $ultimo_id,
			),
			200
		);
	}

	public function post_partido_evento( WP_REST_Request $request ): WP_REST_Response {
		$partido_id = (int) $request->get_param( 'id' );
		$eid        = ED_Partidos_Eventos::registrar(
			$partido_id,
			sanitize_key( (string) $request->get_param( 'tipo' ) ),
			$request->get_param( 'jugador_id' ) ? (int) $request->get_param( 'jugador_id' ) : null,
			$request->get_param( 'equipo_id' ) ? (int) $request->get_param( 'equipo_id' ) : null,
			$request->get_param( 'minuto' ) !== null && $request->get_param( 'minuto' ) !== '' ? (int) $request->get_param( 'minuto' ) : null,
			sanitize_text_field( (string) $request->get_param( 'descripcion' ) )
		);
		if ( ! $eid ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		return new WP_REST_Response(
			array(
				'ok'        => true,
				'evento_id' => $eid,
			),
			200
		);
	}

	public function get_partido_jugadores_rest( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			ED_Partidos_Eventos::get_jugadores_partido( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function get_mvp_estado( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			ED_MVP::get_estado_votacion( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function post_mvp_votar( WP_REST_Request $request ): WP_REST_Response {
		$partido_id  = (int) $request->get_param( 'id' );
		$jugador_id  = (int) $request->get_param( 'jugador_id' );
		$user_id     = is_user_logged_in() ? (int) get_current_user_id() : null;
		$fingerprint = ED_MVP::generar_fingerprint();
		$res         = ED_MVP::votar( $partido_id, $jugador_id, $user_id, $fingerprint );
		return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
	}

	public function can_manage_cronicas_rest(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_escuela_deportiva' );
	}

	public function get_cronica_partido( WP_REST_Request $request ): WP_REST_Response {
		$partido_id = (int) $request->get_param( 'id' );
		$cronica    = function_exists( 'get_field' ) ? get_field( ED_Torneos::PAR_CRONICA, $partido_id, false ) : '';
		$cronica    = is_string( $cronica ) ? $cronica : '';
		$post_id    = (int) get_post_meta( $partido_id, '_ed_cronica_post_id', true );
		return new WP_REST_Response(
			array(
				'partido_id'    => $partido_id,
				'cronica'       => '' !== trim( wp_strip_all_tags( $cronica ) ) ? $cronica : null,
				'post_url'      => $post_id ? get_permalink( $post_id ) : null,
				'tiene_cronica' => '' !== trim( wp_strip_all_tags( $cronica ) ),
			),
			200
		);
	}

	public function post_generar_cronica( WP_REST_Request $request ): WP_REST_Response {
		$pid = (int) $request->get_param( 'id' );
		$res = ED_IA_Cronicas::generar_cronica( $pid );
		return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
	}

	public function get_publicidad_posicion( WP_REST_Request $request ): WP_REST_Response {
		$pos = sanitize_key( (string) $request->get_param( 'posicion' ) );
		if ( ! ED_Publicidad::posicion_valida( $pos ) ) {
			return new WP_REST_Response( array( 'error' => 'posicion' ), 400 );
		}
		return new WP_REST_Response( ED_Publicidad::get_anuncios( $pos ), 200 );
	}

	public function post_publicidad_click( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( 'anuncio' !== get_post_type( $id ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		ED_Publicidad::registrar_evento( $id, 'click' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function get_directorio_list( WP_REST_Request $request ): WP_REST_Response {
		$cat = $request->get_param( 'categoria' );
		$cat = null !== $cat && '' !== (string) $cat ? (string) $cat : null;
		return new WP_REST_Response(
			ED_Directorio::get_empresas(
				$cat,
				min( 100, max( 1, (int) $request->get_param( 'limit' ) ) ),
				max( 0, (int) $request->get_param( 'offset' ) )
			),
			200
		);
	}

	public function get_directorio_empresa( WP_REST_Request $request ): WP_REST_Response {
		$emp = ED_Directorio::get_empresa_by_slug( (string) $request->get_param( 'slug' ) );
		if ( ! $emp ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		return new WP_REST_Response( $emp, 200 );
	}

	public function get_directorio_categorias(): WP_REST_Response {
		return new WP_REST_Response( ED_Directorio::get_categorias(), 200 );
	}

	public function get_entradas_usuario_rest(): WP_REST_Response {
		$entradas = ED_Entradas_QR::get_entradas_usuario( (int) get_current_user_id() );
		$out      = array();
		foreach ( $entradas as $e ) {
			$out[] = array(
				'id'           => (int) $e->id,
				'torneo'       => $e->torneo_nombre ?? '',
				'qr_url'       => ED_Entradas_QR::get_qr_url( (string) $e->qr_token ),
				'estado'       => (string) $e->estado,
				'precio'       => (float) $e->precio,
				'numeros_rifa' => ED_Rifa::get_numeros_entrada( (int) $e->id ),
				'creado_en'    => (string) $e->creado_en,
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function post_validar_entrada( WP_REST_Request $request ): WP_REST_Response {
		$resultado = ED_Entradas_QR::validar(
			sanitize_text_field( (string) $request->get_param( 'token' ) ),
			(int) get_current_user_id()
		);
		return new WP_REST_Response( $resultado, ! empty( $resultado['ok'] ) ? 200 : 400 );
	}

	public function get_entrada_qr_redirect( WP_REST_Request $request ): WP_REST_Response {
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$qr_url = ED_Entradas_QR::get_qr_url( $token );
		$res    = new WP_REST_Response( null, 302 );
		$res->header( 'Location', $qr_url );
		return $res;
	}

	public function get_stats_entradas_torneo( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			ED_Entradas_QR::get_stats_torneo( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function get_rifa_ganadores_rest( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			ED_Rifa::get_ganadores( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function post_rifa_sorteo( WP_REST_Request $request ): WP_REST_Response {
		$premios = $request->get_param( 'premios' );
		$premios = is_array( $premios ) ? $premios : array();
		$result  = ED_Rifa::realizar_sorteo( (int) $request->get_param( 'id' ), $premios );
		return new WP_REST_Response( $result, 200 );
	}

	public function post_rifa_sorteo_aleatorio( WP_REST_Request $request ): WP_REST_Response {
		$desc = $request->get_param( 'premios_desc' );
		$desc = is_array( $desc ) ? array_map( 'strval', $desc ) : array();
		$result = ED_Rifa::sorteo_aleatorio( (int) $request->get_param( 'id' ), $desc );
		return new WP_REST_Response( $result, 200 );
	}

	public function get_bar_productos_rest( WP_REST_Request $request ): WP_REST_Response {
		$rows = ED_Bar::get_productos( (int) $request->get_param( 'id' ) );
		$out  = array();
		foreach ( $rows as $p ) {
			$foto_id = isset( $p->foto_id ) ? (int) $p->foto_id : 0;
			$out[]   = array(
				'id'          => (int) $p->id,
				'nombre'      => (string) $p->nombre,
				'descripcion' => (string) ( $p->descripcion ?? '' ),
				'precio'      => (float) $p->precio,
				'categoria'   => (string) ( $p->categoria ?? '' ),
				'foto_url'    => $foto_id ? wp_get_attachment_image_url( $foto_id, 'medium' ) : null,
				'orden'       => (int) ( $p->orden ?? 0 ),
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function get_bar_franjas_rest( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			ED_Bar::get_franjas( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function post_bar_pedido( WP_REST_Request $request ): WP_REST_Response {
		$tid = (int) $request->get_param( 'id' );
		if ( ! function_exists( 'get_field' ) || ! (bool) get_field( ED_Torneos::TOR_BAR_ACTIVO, $tid ) ) {
			return new WP_REST_Response(
				array(
					'ok'    => false,
					'error' => __( 'El bar no està actiu per a aquest torneig.', 'escuela-deportiva-core' ),
				),
				400
			);
		}
		$lineas = $request->get_param( 'lineas' );
		$lineas = is_array( $lineas ) ? $lineas : array();
		$result = ED_Bar::crear_pedido(
			$tid,
			sanitize_text_field( (string) $request->get_param( 'nombre_cliente' ) ),
			sanitize_text_field( (string) $request->get_param( 'franja_horaria' ) ),
			sanitize_text_field( (string) $request->get_param( 'metodo_pago' ) ),
			$lineas,
			is_user_logged_in() ? (int) get_current_user_id() : null,
			sanitize_text_field( (string) ( $request->get_param( 'telefono' ) ?? '' ) ),
			sanitize_text_field( (string) ( $request->get_param( 'notas' ) ?? '' ) )
		);
		return new WP_REST_Response( $result, ! empty( $result['ok'] ) ? 200 : 400 );
	}

	public function get_bar_pedidos_rest( WP_REST_Request $request ): WP_REST_Response {
		$franja = $request->get_param( 'franja' );
		$estado = $request->get_param( 'estado' );
		$franja = null !== $franja && '' !== (string) $franja ? (string) $franja : null;
		$estado = null !== $estado && '' !== (string) $estado ? (string) $estado : null;
		return new WP_REST_Response(
			ED_Bar::get_pedidos( (int) $request->get_param( 'id' ), $franja, $estado ),
			200
		);
	}

	public function post_bar_pedido_estado( WP_REST_Request $request ): WP_REST_Response {
		$ok = ED_Bar::actualizar_estado(
			(int) $request->get_param( 'id' ),
			sanitize_text_field( (string) $request->get_param( 'estado' ) )
		);
		return new WP_REST_Response( array( 'ok' => $ok ), $ok ? 200 : 400 );
	}

	public function get_bar_mis_pedidos(): WP_REST_Response {
		return new WP_REST_Response(
			ED_Bar::get_pedidos_usuario( (int) get_current_user_id() ),
			200
		);
	}

	public function get_fed_proximos( WP_REST_Request $request ): WP_REST_Response {
		$rows = ED_Sync_Federacion::get_proximos_partidos( (int) $request->get_param( 'limit' ) );
		return new WP_REST_Response( array_map( array( $this, 'fed_partido_to_array' ), $rows ), 200 );
	}

	public function get_fed_resultados( WP_REST_Request $request ): WP_REST_Response {
		$rows = ED_Sync_Federacion::get_resultados_recientes( (int) $request->get_param( 'limit' ) );
		return new WP_REST_Response( array_map( array( $this, 'fed_partido_to_array' ), $rows ), 200 );
	}

	public function get_fed_clasificacion( WP_REST_Request $request ): WP_REST_Response {
		$rows = ED_Sync_Federacion::get_clasificacion_categoria( (int) $request->get_param( 'categoria_id' ) );
		return new WP_REST_Response( array_map( array( $this, 'fed_clasi_to_array' ), $rows ), 200 );
	}

	public function post_fed_sync(): WP_REST_Response {
		ED_Sync_Federacion::sincronizar( true );
		return new WP_REST_Response(
			array(
				'ok'          => true,
				'ultima_sync' => get_option( 'ed_ffcv_ultima_sync' ),
			),
			200
		);
	}

	/**
	 * @param object $row Fila ed_fed_partidos.
	 * @return array<string, mixed>
	 */
	private function fed_partido_to_array( object $row ): array {
		return array(
			'id'                 => (int) $row->id,
			'ffcv_id'            => (string) $row->ffcv_id,
			'competicion'        => (string) ( $row->competicion ?? '' ),
			'jornada'            => isset( $row->jornada ) ? (int) $row->jornada : null,
			'categoria_ffcv'     => (string) ( $row->categoria_ffcv ?? '' ),
			'categoria_id'       => isset( $row->categoria_id ) ? (int) $row->categoria_id : null,
			'equipo_local'       => (string) $row->equipo_local,
			'equipo_visitante'   => (string) $row->equipo_visitante,
			'es_nuestro_equipo'  => (int) $row->es_nuestro_equipo,
			'fecha_hora'         => $row->fecha_hora ?? null,
			'campo'              => $row->campo ?? null,
			'goles_local'        => isset( $row->goles_local ) ? (int) $row->goles_local : null,
			'goles_visitante'    => isset( $row->goles_visitante ) ? (int) $row->goles_visitante : null,
			'estado'             => (string) $row->estado,
			'temporada'          => (string) $row->temporada,
		);
	}

	/**
	 * @param object $row Fila ed_fed_clasificacion.
	 * @return array<string, mixed>
	 */
	private function fed_clasi_to_array( object $row ): array {
		return array(
			'posicion'       => (int) $row->posicion,
			'equipo'         => (string) $row->equipo,
			'es_nuestro'     => (int) $row->es_nuestro,
			'pj'             => (int) $row->pj,
			'pg'             => (int) $row->pg,
			'pe'             => (int) $row->pe,
			'pp'             => (int) $row->pp,
			'gf'             => (int) $row->gf,
			'gc'             => (int) $row->gc,
			'puntos'         => (int) $row->puntos,
			'categoria_ffcv' => (string) $row->categoria_ffcv,
			'competicion'    => (string) $row->competicion,
		);
	}

	public function get_calendario_semana( WP_REST_Request $request ): WP_REST_Response {
		$fecha = $request->get_param( 'fecha' );
		$fecha = $fecha ? sanitize_text_field( (string) $fecha ) : wp_date( 'Y-m-d' );
		try {
			$tz    = wp_timezone();
			$d     = new DateTimeImmutable( $fecha . ' 12:00:00', $tz );
			$lunes = $d->modify( 'monday this week' );
		} catch ( \Exception $e ) {
			$d     = new DateTimeImmutable( 'now', wp_timezone() );
			$lunes = $d->modify( 'monday this week' );
		}
		$domingo = $lunes->modify( '+6 days' );
		$ini     = $lunes->format( 'Y-m-d' );
		$fin     = $domingo->format( 'Y-m-d' );

		$cat = $request->get_param( 'categoria_id' );
		$jid = $request->get_param( 'jugador_id' );
		$cat = null !== $cat && '' !== (string) $cat ? (int) $cat : null;
		$jid = null !== $jid && '' !== (string) $jid ? (int) $jid : null;

		$eventos = ED_Calendario::get_eventos_semana( $ini, $fin, $cat, $jid );

		return new WP_REST_Response(
			array(
				'semana_inicio' => $ini,
				'semana_fin'    => $fin,
				'eventos'       => $eventos,
			),
			200
		);
	}

	public function get_calendario_mes( WP_REST_Request $request ): WP_REST_Response {
		$y = (int) $request->get_param( 'year' );
		$m = (int) $request->get_param( 'month' );
		if ( $y < 1970 || $y > 2100 ) {
			$y = (int) gmdate( 'Y' );
		}
		if ( $m < 1 || $m > 12 ) {
			$m = (int) gmdate( 'n' );
		}
		$cat = $request->get_param( 'categoria_id' );
		$cat = null !== $cat && '' !== (string) $cat ? (int) $cat : null;

		return new WP_REST_Response(
			ED_Calendario::get_eventos_mes( $y, $m, $cat ),
			200
		);
	}

	public function get_resultados_semana_rest( WP_REST_Request $request ): WP_REST_Response {
		$f = $request->get_param( 'fecha' );
		$f = $f ? sanitize_text_field( (string) $f ) : null;
		return new WP_REST_Response( ED_Calendario::get_resultados_semana( $f ), 200 );
	}

	public function get_mensajes_rest( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( ! $n ) {
			return new WP_REST_Response( array(), 200 );
		}
		$limit    = (int) $request->get_param( 'limit' );
		$mensajes = ED_Comunicaciones::get_mensajes_nucleo( (int) $n->ID, $limit );
		$out      = array();
		foreach ( $mensajes as $m ) {
			if ( ! $m->leido ) {
				ED_Comunicaciones::marcar_leido( (int) $m->id, (int) $n->ID );
				$m->leido    = 1;
				$m->leido_en = current_time( 'mysql' );
			}
			$out[] = array(
				'id'           => (int) $m->id,
				'asunto'       => (string) $m->asunto,
				'cuerpo'       => (string) $m->cuerpo,
				'remitente'    => (string) ( $m->remitente_nombre ?? '' ),
				'leido'        => (bool) $m->leido,
				'leido_en'     => $m->leido_en,
				'creado_en'    => (string) $m->creado_en,
				'tipo_destino' => (string) $m->tipo_destino,
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function get_mensajes_no_leidos_rest(): WP_REST_Response {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		$c    = $n ? ED_Comunicaciones::get_no_leidos( (int) $n->ID ) : 0;
		return new WP_REST_Response( array( 'count' => $c ), 200 );
	}

	public function post_marcar_leido_mensaje_rest( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();
		$n    = ED_Nucleo_Repository::find_for_user( (int) $user->ID );
		if ( $n ) {
			ED_Comunicaciones::marcar_leido( (int) $request->get_param( 'id' ), (int) $n->ID );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function post_enviar_mensaje_rest( WP_REST_Request $request ): WP_REST_Response {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = array();
		}
		$tipo = isset( $json['tipo_destino'] ) ? sanitize_key( (string) $json['tipo_destino'] ) : sanitize_key( (string) $request->get_param( 'tipo_destino' ) );
		$did  = array_key_exists( 'destino_id', $json ) && null !== $json['destino_id'] && '' !== (string) $json['destino_id']
			? (int) $json['destino_id']
			: ( $request->get_param( 'destino_id' ) ? (int) $request->get_param( 'destino_id' ) : null );
		$asunto = isset( $json['asunto'] ) ? (string) $json['asunto'] : (string) $request->get_param( 'asunto' );
		$cuerpo = isset( $json['cuerpo'] ) ? (string) $json['cuerpo'] : (string) $request->get_param( 'cuerpo' );

		if ( ! in_array( $tipo, ED_Comunicaciones::tipos_destino_validos(), true ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'mensaje_id' => 0, 'error' => 'tipo_destino' ),
				400
			);
		}

		$destino_param = $did && $did > 0 ? $did : null;
		if ( ED_Comunicaciones::TIPO_CLUB !== $tipo && ! $destino_param ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'mensaje_id' => 0, 'error' => 'destino_id' ),
				400
			);
		}

		$uid = (int) get_current_user_id();
		if ( ! ED_Comunicaciones::usuario_puede_enviar( $uid, $tipo, $destino_param ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'mensaje_id' => 0, 'error' => 'forbidden' ),
				403
			);
		}

		$id = ED_Comunicaciones::enviar(
			$uid,
			$tipo,
			$destino_param,
			$asunto,
			wp_kses_post( $cuerpo )
		);

		if ( ! $id ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'mensaje_id' => 0, 'error' => 'envio' ),
				400
			);
		}

		return new WP_REST_Response( array( 'ok' => true, 'mensaje_id' => $id ), 200 );
	}

	public function get_mensajes_enviados_rest( WP_REST_Request $request ): WP_REST_Response {
		$limit = (int) $request->get_param( 'limit' );
		$rows  = ED_Comunicaciones::get_mensajes_enviados( (int) get_current_user_id(), $limit );
		$out   = array();
		foreach ( $rows as $m ) {
			$out[] = array(
				'id'                => (int) $m->id,
				'asunto'            => (string) $m->asunto,
				'cuerpo'            => (string) $m->cuerpo,
				'tipo_destino'      => (string) $m->tipo_destino,
				'destino_id'        => isset( $m->destino_id ) ? (int) $m->destino_id : null,
				'creado_en'         => (string) $m->creado_en,
				'total_receptores'  => isset( $m->total_receptores ) ? (int) $m->total_receptores : 0,
				'total_leidos'      => isset( $m->total_leidos ) ? (int) $m->total_leidos : 0,
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function get_mensajes_destinos_rest( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$tipo = sanitize_key( (string) $request->get_param( 'tipo' ) );
		if ( ! in_array( $tipo, array( 'deporte', 'categoria', 'nucleo', 'jugador' ), true ) ) {
			return new WP_Error(
				'ed_invalid_tipo',
				__( 'Tipus de destí no vàlid.', 'escuela-deportiva-core' ),
				array( 'status' => 400 )
			);
		}
		return new WP_REST_Response( ED_Comunicaciones::get_destinos_para_selector( $tipo ), 200 );
	}

	public function get_admin_kpis_rest(): WP_REST_Response {
		return new WP_REST_Response( ED_Dashboard_Admin::get_kpis(), 200 );
	}

	public function get_admin_ingresos_mes_rest(): WP_REST_Response {
		return new WP_REST_Response( ED_Dashboard_Admin::get_ingresos_por_mes(), 200 );
	}

	public function get_admin_inscripciones_deporte_rest(): WP_REST_Response {
		return new WP_REST_Response( ED_Dashboard_Admin::get_inscripciones_por_deporte(), 200 );
	}

	public function get_admin_asistencia_categoria_rest(): WP_REST_Response {
		return new WP_REST_Response( ED_Dashboard_Admin::get_asistencia_por_categoria(), 200 );
	}

	public function get_admin_impagos_rest(): WP_REST_Response {
		$rows = ED_Dashboard_Admin::get_impagos();
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'jugador_nombre' => (string) $r->jugador_nombre,
				'deporte_nombre' => (string) $r->deporte_nombre,
				'importe'        => (float) $r->importe,
				'fecha_prevista' => $r->fecha_prevista,
				'estado'         => (string) $r->estado,
				'plazo'          => (int) $r->plazo,
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function get_admin_baja_asistencia_rest(): WP_REST_Response {
		$rows = ED_Dashboard_Admin::get_baja_asistencia();
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'jugador_id'     => (int) $r->jugador_id,
				'jugador_nombre' => (string) $r->jugador_nombre,
				'total'          => (int) $r->total,
				'asistencias'    => (int) $r->asistencias,
				'pct'            => (float) $r->pct,
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function get_admin_exportar_csv( WP_REST_Request $request ): void {
		$tipo = sanitize_key( (string) $request->get_param( 'tipo' ) );
		ED_Dashboard_Admin::exportar_csv( $tipo );
		exit;
	}
}
