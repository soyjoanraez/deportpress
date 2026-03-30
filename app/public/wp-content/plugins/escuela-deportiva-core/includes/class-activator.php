<?php
/**
 * Activación: tablas, opciones, roles, migraciones de esquema.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase activador.
 */
class ED_Activator {

	/**
	 * Versió d'esquema de BD (incrementar quan canviïn taules).
	 * v9: Columna email_enviado en ed_msg_recepciones y cola asíncrona de envíos.
	 */
	public const DB_VERSION = '9';
	public const CATALOG_SEED_VERSION = '1';

	/**
	 * Ejecutar en activación del plugin.
	 */
	public static function activate(): void {
		require_once ED_PLUGIN_DIR . 'includes/class-roles.php';
		self::create_tables();
		self::migrate_data_if_needed();
		update_option( 'ed_db_version', self::DB_VERSION );
		ED_Roles::install();
		ED_Roles::upgrade_fase34_caps_for_existing_roles();
		ED_Roles::upgrade_fase5_caps_for_existing_roles();
		flush_rewrite_rules();
	}

	/**
	 * Comprova i aplica migracions en càrregues posteriors a l'activació.
	 */
	public static function maybe_upgrade(): void {
		$stored = (string) get_option( 'ed_db_version', '0' );
		if ( version_compare( $stored, self::DB_VERSION, '>=' ) ) {
			return;
		}
		// IMPORTANT: migrate_data_if_needed s'executa ABANS de create_tables
		// perquè la migració v8 elimina duplicats necessaris per afegir el UNIQUE KEY.
		self::migrate_data_if_needed();
		self::create_tables();
		update_option( 'ed_db_version', self::DB_VERSION );
		ED_Roles::upgrade_entrenamiento_caps_for_existing_roles();
		ED_Roles::upgrade_fase34_caps_for_existing_roles();
		ED_Roles::upgrade_fase5_caps_for_existing_roles();
		flush_rewrite_rules();
	}

	/**
	 * Crea el catàleg base de esports i categories una sola vegada.
	 */
	public static function maybe_seed_default_catalog(): void {
		$stored = (string) get_option( 'ed_catalog_seed_version', '0' );
		if ( version_compare( $stored, self::CATALOG_SEED_VERSION, '>=' ) ) {
			return;
		}

		self::seed_default_catalog();
		update_option( 'ed_catalog_seed_version', self::CATALOG_SEED_VERSION );
	}

	/**
	 * Crea esports i categories base de forma idempotent.
	 */
	private static function seed_default_catalog(): void {
		if ( ! post_type_exists( 'deporte' ) || ! post_type_exists( 'categoria' ) ) {
			return;
		}

		$deportes = array(
			'futbol'            => 'Futbol',
			'baloncesto'        => 'Baloncesto',
			'pelota-valenciana' => 'Pelota Valenciana',
			'gimnasia'          => 'Gimnasia',
		);

		$rangos = array(
			array(
				'slug'  => 'querubin',
				'title' => 'Querubín',
				'min'   => 4,
				'max'   => 5,
			),
			array(
				'slug'  => 'prebenjamin',
				'title' => 'Prebenjamín',
				'min'   => 6,
				'max'   => 7,
			),
			array(
				'slug'  => 'benjamin',
				'title' => 'Benjamín',
				'min'   => 8,
				'max'   => 9,
			),
			array(
				'slug'  => 'alevin',
				'title' => 'Alevín',
				'min'   => 10,
				'max'   => 11,
			),
			array(
				'slug'  => 'infantil',
				'title' => 'Infantil',
				'min'   => 12,
				'max'   => 13,
			),
			array(
				'slug'  => 'cadete',
				'title' => 'Cadete',
				'min'   => 14,
				'max'   => 15,
			),
			array(
				'slug'  => 'juvenil',
				'title' => 'Juvenil',
				'min'   => 16,
				'max'   => 18,
			),
		);

		foreach ( $deportes as $deporte_slug => $deporte_title ) {
			$deporte_id = self::upsert_catalog_post( 'deporte', $deporte_title, $deporte_slug );
			if ( $deporte_id <= 0 ) {
				continue;
			}

			foreach ( $rangos as $rango ) {
				$categoria_id = self::upsert_catalog_post(
					'categoria',
					$rango['title'],
					$rango['slug'] . '-' . $deporte_slug
				);

				if ( $categoria_id <= 0 ) {
					continue;
				}

				self::update_acf_like_meta( $categoria_id, 'ed_cat_deporte', $deporte_id, 'field_ed_cat_dep' );
				self::update_acf_like_meta( $categoria_id, 'ed_cat_edad_min', (int) $rango['min'], 'field_ed_cat_min' );
				self::update_acf_like_meta( $categoria_id, 'ed_cat_edad_max', (int) $rango['max'], 'field_ed_cat_max' );
			}
		}
	}

	/**
	 * Crea o recupera un post del catàleg per slug.
	 */
	private static function upsert_catalog_post( string $post_type, string $title, string $slug ): int {
		$existing = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $existing instanceof WP_Post ) {
			return (int) $existing->ID;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		return (int) $post_id;
	}

	/**
	 * Guarda meta compatible amb ACF encara que ACF no haja inicialitzat els camps.
	 *
	 * @param int|string $value Valor del meta.
	 */
	private static function update_acf_like_meta( int $post_id, string $meta_key, $value, string $field_key ): void {
		update_post_meta( $post_id, $meta_key, $value );
		update_post_meta( $post_id, '_' . $meta_key, $field_key );
	}

	/**
	 * Crea o actualitza tablas con dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql_asistencias = "CREATE TABLE {$wpdb->prefix}ed_asistencias (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			jugador_id bigint(20) unsigned NOT NULL,
			sesion_id bigint(20) unsigned NOT NULL,
			tipo_sesion varchar(20) NOT NULL DEFAULT 'entrenamiento',
			estado varchar(20) NOT NULL DEFAULT 'asistio',
			motivo varchar(255) NULL,
			fecha date NOT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY jugador_id (jugador_id),
			KEY sesion_tipo (sesion_id, tipo_sesion),
			KEY fecha (fecha)
		) $charset_collate;";

		$sql_eventos = "CREATE TABLE {$wpdb->prefix}ed_eventos_partido (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partido_id bigint(20) unsigned NOT NULL,
			tipo varchar(50) NOT NULL,
			jugador_id bigint(20) unsigned DEFAULT NULL,
			equipo_id bigint(20) unsigned DEFAULT NULL,
			minuto smallint(5) unsigned DEFAULT NULL,
			descripcion varchar(500) DEFAULT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY partido_id (partido_id),
			KEY tipo (tipo)
		) $charset_collate;";

		$sql_votos_mvp = "CREATE TABLE {$wpdb->prefix}ed_votos_mvp (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partido_id bigint(20) unsigned NOT NULL,
			jugador_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			fingerprint varchar(64) DEFAULT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_user_partido (user_id, partido_id),
			UNIQUE KEY uq_fingerprint_partido (fingerprint, partido_id),
			KEY idx_partido_jugador (partido_id, jugador_id)
		) $charset_collate;";

		$sql_rankings = "CREATE TABLE {$wpdb->prefix}ed_rankings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			torneo_id bigint(20) unsigned DEFAULT NULL,
			jugador_id bigint(20) unsigned NOT NULL,
			tipo varchar(20) NOT NULL,
			valor smallint(5) unsigned NOT NULL DEFAULT 0,
			actualizado datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_torneo_jugador_tipo (torneo_id, jugador_id, tipo),
			KEY idx_torneo_tipo_valor (torneo_id, tipo, valor)
		) $charset_collate;";

		$sql_push_subs = "CREATE TABLE {$wpdb->prefix}ed_push_suscripciones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			onesignal_id varchar(255) NOT NULL,
			tipo varchar(20) NOT NULL,
			referencia_id bigint(20) unsigned NOT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_onesignal_tipo_ref (onesignal_id, tipo, referencia_id),
			KEY idx_tipo_ref (tipo, referencia_id),
			KEY idx_onesignal (onesignal_id)
		) $charset_collate;";

		$sql_pagos = "CREATE TABLE {$wpdb->prefix}ed_pagos_plazos (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nucleo_id bigint(20) unsigned NOT NULL,
			jugador_id bigint(20) unsigned NOT NULL DEFAULT 0,
			deporte_id bigint(20) unsigned NOT NULL DEFAULT 0,
			plazo tinyint(3) unsigned NOT NULL DEFAULT 1,
			importe decimal(10,2) NOT NULL,
			estado varchar(20) NOT NULL DEFAULT 'pendiente',
			fecha_prevista date NULL,
			fecha_pagado datetime NULL,
			pasarela varchar(20) NOT NULL DEFAULT 'manual',
			token_pago varchar(255) NULL,
			wc_order_id bigint(20) unsigned NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_jugador_deporte_plazo (nucleo_id, jugador_id, deporte_id, plazo),
			KEY nucleo_id (nucleo_id),
			KEY jugador_id (jugador_id),
			KEY deporte_id (deporte_id),
			KEY estado_fecha (estado, fecha_prevista)
		) $charset_collate;";

		dbDelta( $sql_asistencias );
		dbDelta( $sql_eventos );
		dbDelta( $sql_pagos );
		dbDelta( $sql_votos_mvp );
		dbDelta( $sql_rankings );
		dbDelta( $sql_push_subs );

		$sql_pub_imp = "CREATE TABLE {$wpdb->prefix}ed_pub_impresiones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			anuncio_id bigint(20) unsigned NOT NULL,
			tipo varchar(20) NOT NULL DEFAULT 'impresion',
			fecha date NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_anuncio_fecha (anuncio_id, fecha),
			KEY idx_fecha (fecha)
		) $charset_collate;";

		$sql_cola_cron = "CREATE TABLE {$wpdb->prefix}ed_cronicas_cola (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partido_id bigint(20) unsigned NOT NULL,
			estado varchar(20) NOT NULL DEFAULT 'pendiente',
			intentos tinyint(3) unsigned NOT NULL DEFAULT 0,
			error_msg text NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			procesado_en datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_partido (partido_id),
			KEY idx_estado (estado)
		) $charset_collate;";

		dbDelta( $sql_pub_imp );
		dbDelta( $sql_cola_cron );

		$sql_entradas = "CREATE TABLE {$wpdb->prefix}ed_entradas (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			torneo_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			nucleo_id bigint(20) unsigned DEFAULT NULL,
			wc_order_id bigint(20) unsigned NOT NULL,
			wc_order_item_id bigint(20) unsigned NOT NULL,
			qr_token varchar(64) NOT NULL,
			estado varchar(20) NOT NULL DEFAULT 'valida',
			nombre_titular varchar(255) DEFAULT NULL,
			email_titular varchar(255) DEFAULT NULL,
			precio decimal(8,2) NOT NULL DEFAULT 0.00,
			usado_en datetime DEFAULT NULL,
			usado_por bigint(20) unsigned DEFAULT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_qr_token (qr_token),
			KEY idx_torneo (torneo_id),
			KEY idx_order (wc_order_id),
			KEY idx_estado (estado)
		) $charset_collate;";

		$sql_rifa = "CREATE TABLE {$wpdb->prefix}ed_rifa_numeros (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			torneo_id bigint(20) unsigned NOT NULL,
			entrada_id bigint(20) unsigned NOT NULL,
			numero smallint(5) unsigned NOT NULL,
			premiado tinyint(1) NOT NULL DEFAULT 0,
			premio varchar(100) DEFAULT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_torneo_numero (torneo_id, numero),
			KEY idx_entrada (entrada_id),
			KEY idx_premiado (premiado)
		) $charset_collate;";

		$sql_bar_productos = "CREATE TABLE {$wpdb->prefix}ed_bar_productos (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			torneo_id bigint(20) unsigned NOT NULL,
			nombre varchar(255) NOT NULL,
			descripcion varchar(500) DEFAULT NULL,
			precio decimal(8,2) NOT NULL,
			categoria varchar(100) DEFAULT NULL,
			foto_id bigint(20) unsigned DEFAULT NULL,
			disponible tinyint(1) NOT NULL DEFAULT 1,
			orden smallint(5) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_torneo_disponible (torneo_id, disponible)
		) $charset_collate;";

		$sql_bar_pedidos = "CREATE TABLE {$wpdb->prefix}ed_bar_pedidos (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			torneo_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			wc_order_id bigint(20) unsigned DEFAULT NULL,
			nombre_cliente varchar(255) NOT NULL,
			telefono varchar(30) DEFAULT NULL,
			franja_horaria varchar(20) NOT NULL,
			metodo_pago varchar(20) NOT NULL DEFAULT 'efectivo',
			estado varchar(20) NOT NULL DEFAULT 'pendiente',
			notas varchar(500) DEFAULT NULL,
			total decimal(8,2) NOT NULL DEFAULT 0.00,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			actualizado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_torneo_franja (torneo_id, franja_horaria),
			KEY idx_estado (estado)
		) $charset_collate;";

		$sql_bar_lineas = "CREATE TABLE {$wpdb->prefix}ed_bar_lineas (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			pedido_id bigint(20) unsigned NOT NULL,
			producto_id bigint(20) unsigned NOT NULL,
			cantidad tinyint(3) unsigned NOT NULL DEFAULT 1,
			precio_unit decimal(8,2) NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_pedido (pedido_id)
		) $charset_collate;";

		dbDelta( $sql_entradas );
		dbDelta( $sql_rifa );
		dbDelta( $sql_bar_productos );
		dbDelta( $sql_bar_pedidos );
		dbDelta( $sql_bar_lineas );

		$sql_fed_partidos = "CREATE TABLE {$wpdb->prefix}ed_fed_partidos (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ffcv_id varchar(100) NOT NULL,
			competicion varchar(255) DEFAULT NULL,
			jornada tinyint(3) unsigned DEFAULT NULL,
			categoria_ffcv varchar(100) DEFAULT NULL,
			categoria_id bigint(20) unsigned DEFAULT NULL,
			equipo_local varchar(255) NOT NULL,
			equipo_visitante varchar(255) NOT NULL,
			equipo_local_id bigint(20) unsigned DEFAULT NULL,
			es_nuestro_equipo tinyint(1) NOT NULL DEFAULT 0,
			fecha_hora datetime DEFAULT NULL,
			campo varchar(255) DEFAULT NULL,
			goles_local tinyint(3) unsigned DEFAULT NULL,
			goles_visitante tinyint(3) unsigned DEFAULT NULL,
			estado varchar(20) NOT NULL DEFAULT 'programado',
			temporada varchar(10) NOT NULL DEFAULT '2025-26',
			ultima_sync datetime DEFAULT NULL,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_ffcv_id (ffcv_id),
			KEY idx_categoria (categoria_id),
			KEY idx_fecha (fecha_hora),
			KEY idx_nuestro (es_nuestro_equipo),
			KEY idx_temporada (temporada)
		) $charset_collate;";

		$sql_fed_clasificacion = "CREATE TABLE {$wpdb->prefix}ed_fed_clasificacion (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uq_hash varchar(32) NOT NULL,
			competicion varchar(255) NOT NULL,
			categoria_ffcv varchar(100) NOT NULL,
			equipo varchar(255) NOT NULL,
			es_nuestro tinyint(1) NOT NULL DEFAULT 0,
			pj tinyint(3) unsigned NOT NULL DEFAULT 0,
			pg tinyint(3) unsigned NOT NULL DEFAULT 0,
			pe tinyint(3) unsigned NOT NULL DEFAULT 0,
			pp tinyint(3) unsigned NOT NULL DEFAULT 0,
			gf smallint(5) unsigned NOT NULL DEFAULT 0,
			gc smallint(5) unsigned NOT NULL DEFAULT 0,
			puntos smallint(5) unsigned NOT NULL DEFAULT 0,
			posicion tinyint(3) unsigned NOT NULL DEFAULT 0,
			temporada varchar(10) NOT NULL DEFAULT '2025-26',
			actualizado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_fed_clasi_hash (uq_hash),
			KEY idx_es_nuestro (es_nuestro),
			KEY idx_cat_temp (categoria_ffcv, temporada)
		) $charset_collate;";

		$sql_fed_mapeo = "CREATE TABLE {$wpdb->prefix}ed_fed_mapeo (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			categoria_ffcv varchar(100) NOT NULL,
			categoria_id bigint(20) unsigned NOT NULL,
			competicion varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY uq_ffcv_cat_comp (categoria_ffcv, competicion)
		) $charset_collate;";

		dbDelta( $sql_fed_partidos );
		dbDelta( $sql_fed_clasificacion );
		dbDelta( $sql_fed_mapeo );

		$sql_mensajes = "CREATE TABLE {$wpdb->prefix}ed_mensajes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			remitente_id bigint(20) unsigned NOT NULL,
			tipo_destino varchar(20) NOT NULL,
			destino_id bigint(20) unsigned DEFAULT NULL,
			asunto varchar(255) NOT NULL,
			cuerpo longtext NOT NULL,
			enviado_email tinyint(1) NOT NULL DEFAULT 0,
			enviado_push tinyint(1) NOT NULL DEFAULT 0,
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_remitente (remitente_id),
			KEY idx_tipo_destino (tipo_destino, destino_id),
			KEY idx_creado (creado_en)
		) $charset_collate;";

		$sql_msg_recep = "CREATE TABLE {$wpdb->prefix}ed_msg_recepciones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mensaje_id bigint(20) unsigned NOT NULL,
			nucleo_id bigint(20) unsigned NOT NULL,
			leido tinyint(1) NOT NULL DEFAULT 0,
			leido_en datetime DEFAULT NULL,
			email_enviado tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_msg_nucleo (mensaje_id, nucleo_id),
			KEY idx_nucleo_leido (nucleo_id, leido),
			KEY idx_mensaje (mensaje_id),
			KEY idx_email (email_enviado)
		) $charset_collate;";

		dbDelta( $sql_mensajes );
		dbDelta( $sql_msg_recep );
	}

	/**
	 * Dades legades després d'ampliar columnes.
	 * S'executa ABANS de dbDelta per garantir integritat.
	 */
	private static function migrate_data_if_needed(): void {
		global $wpdb;

		$table_a = $wpdb->prefix . 'ed_asistencias';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table_a} SET estado = 'asistio' WHERE estado = 'presente'" );
		// Normalitza data (datetime -> date).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table_a} SET fecha = DATE(fecha) WHERE fecha IS NOT NULL AND fecha != '0000-00-00'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table_a} SET creado_en = NOW() WHERE creado_en = '0000-00-00 00:00:00' OR creado_en IS NULL" );

		$table_p = $wpdb->prefix . 'ed_pagos_plazos';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table_p} SET creado_en = NOW() WHERE creado_en = '0000-00-00 00:00:00' OR creado_en IS NULL" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table_p} SET pasarela = 'manual' WHERE pasarela = '' OR pasarela IS NULL" );

		// v8: Elimina plazos duplicats (nucleo+jugador+deporte+plazo) abans d'afegir UNIQUE KEY.
		// Conserva l'últim registre per cada combinació (el més recent o el pagat preferentment).
		self::deduplicate_pagos_plazos( $table_p );
	}

	/**
	 * Elimina duplicats de ed_pagos_plazos conservant el registre més rellevant.
	 * Prioritat: pagado > pendiente > fallido/cancelado; en cas d'empat, el de menor id.
	 */
	private static function deduplicate_pagos_plazos( string $table ): void {
		global $wpdb;

		// Comprova si ja existeix el UNIQUE KEY (si sí, no hi ha duplicats pendents).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$key_exists = $wpdb->get_var(
			"SELECT COUNT(*) FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = '{$table}'
			AND INDEX_NAME = 'uq_jugador_deporte_plazo'"
		);
		if ( (int) $key_exists > 0 ) {
			return; // UNIQUE KEY ja existeix, no hi ha duplicats.
		}

		// Troba grups duplicats.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dupes = $wpdb->get_results(
			"SELECT nucleo_id, jugador_id, deporte_id, plazo, COUNT(*) AS cnt
			FROM {$table}
			GROUP BY nucleo_id, jugador_id, deporte_id, plazo
			HAVING cnt > 1"
		);

		if ( empty( $dupes ) ) {
			return;
		}

		foreach ( $dupes as $d ) {
			// Determina l'ID a conservar: prefereix 'pagado', després el menor id.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$keep_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table}
					WHERE nucleo_id = %d AND jugador_id = %d AND deporte_id = %d AND plazo = %d
					ORDER BY (estado = 'pagado') DESC, id ASC
					LIMIT 1",
					(int) $d->nucleo_id,
					(int) $d->jugador_id,
					(int) $d->deporte_id,
					(int) $d->plazo
				)
			);
			if ( ! $keep_id ) {
				continue;
			}
			// Esborra els duplicats que no s'han de conservar.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table}
					WHERE nucleo_id = %d AND jugador_id = %d AND deporte_id = %d AND plazo = %d
					AND id != %d",
					(int) $d->nucleo_id,
					(int) $d->jugador_id,
					(int) $d->deporte_id,
					(int) $d->plazo,
					(int) $keep_id
				)
			);
		}
	}
}
