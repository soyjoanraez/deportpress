<?php
/**
 * Desinstalación del plugin.
 *
 * @package escuela-deportiva-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Neteja sempre els crons i transients (independentment de ED_UNINSTALL_DELETE_DATA).
$ed_cron_hooks = array(
	'ed_cron_cobro_plazos',
	'ed_cron_recordatorios',
	'ed_cron_cronicas',
	'ed_cron_sync_federacion',
	'ed_abrir_votacion_mvp',
	'ed_cerrar_votacion_mvp',
	'ed_recordatorio_partido',
	'ed_encolar_cronica_fallback',
);
foreach ( $ed_cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

$ed_transients = array(
	'ed_dashboard_kpis_v1',
	'ed_dashboard_ingresos_mes_v1',
	'ed_dashboard_inscripciones_deporte_v1',
	'ed_dashboard_asistencia_cat_v1',
);
foreach ( $ed_transients as $transient ) {
	delete_transient( $transient );
}

if ( defined( 'ED_UNINSTALL_DELETE_DATA' ) && ED_UNINSTALL_DELETE_DATA ) {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'ed_asistencias',
		$wpdb->prefix . 'ed_eventos_partido',
		$wpdb->prefix . 'ed_pagos_plazos',
		$wpdb->prefix . 'ed_votos_mvp',
		$wpdb->prefix . 'ed_rankings',
		$wpdb->prefix . 'ed_push_suscripciones',
		$wpdb->prefix . 'ed_pub_impresiones',
		$wpdb->prefix . 'ed_cronicas_cola',
		$wpdb->prefix . 'ed_entradas',
		$wpdb->prefix . 'ed_rifa_numeros',
		$wpdb->prefix . 'ed_bar_lineas',
		$wpdb->prefix . 'ed_bar_pedidos',
		$wpdb->prefix . 'ed_bar_productos',
		$wpdb->prefix . 'ed_fed_clasificacion',
		$wpdb->prefix . 'ed_fed_partidos',
		$wpdb->prefix . 'ed_fed_mapeo',
		$wpdb->prefix . 'ed_msg_recepciones',
		$wpdb->prefix . 'ed_mensajes',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from controlled list.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Elimina totes les opcions del plugin.
	$ed_options = array(
		'ed_db_version',
		'ed_nombre_club',
		'ed_temporada_actual',
		'ed_ffcv_config',
		'ed_openai_api_key',
		'ed_onesignal_app_id',
		'ed_onesignal_api_key',
	);
	foreach ( $ed_options as $option ) {
		delete_option( $option );
	}

	// Elimina les capabilities del plugin del rol administrator.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$plugin_caps = array(
			'manage_escuela_deportiva',
			'edit_jugadores', 'edit_others_jugadores', 'publish_jugadores',
			'read_jugador', 'read_private_jugadores', 'delete_jugadores',
			'edit_nucleo_familiars', 'read_nucleo_familiar',
			'edit_deportes', 'edit_categorias', 'edit_equipos',
			'edit_entrenamientos', 'edit_torneos', 'edit_partidos',
			'edit_anuncios', 'edit_empresa_directorios',
		);
		foreach ( $plugin_caps as $cap ) {
			$admin->remove_cap( $cap );
		}
	}

	// Elimina els rols del plugin.
	remove_role( 'ed_coordinador' );
	remove_role( 'ed_entrenador' );
	remove_role( 'ed_adulto' );
	remove_role( 'ed_operador' );
}
