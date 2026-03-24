<?php
/**
 * Desactivación del plugin.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase desactivador.
 */
class ED_Deactivator {

	/**
	 * Ejecutar al desactivar.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'ed_cron_cobro_plazos' );
		wp_clear_scheduled_hook( 'ed_cron_recordatorios' );
		wp_unschedule_hook( 'ed_abrir_votacion_mvp' );
		wp_unschedule_hook( 'ed_cerrar_votacion_mvp' );
		wp_unschedule_hook( 'ed_recordatorio_partido' );
		wp_clear_scheduled_hook( 'ed_cron_cronicas' );
		wp_unschedule_hook( 'ed_encolar_cronica_fallback' );
		wp_clear_scheduled_hook( 'ed_cron_sync_federacion' );
		flush_rewrite_rules();
	}
}
