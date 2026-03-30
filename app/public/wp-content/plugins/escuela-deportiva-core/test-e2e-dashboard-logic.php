<?php
if ( ! defined('ABSPATH') ) exit;
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "Iniciando prueba E2E de Núcleos y Dashboard...\n";
echo "============================================\n\n";

try {
    // 1. Crear roles de usuario
    $padre_id = wp_insert_user([
        'user_login' => 'padre_test_e2e_'.time(),
        'user_pass'  => 'password123',
        'user_email' => 'padre_'.time().'@test.com',
        'role'       => 'familia'
    ]);
    if ( is_wp_error($padre_id) ) throw new Exception("Error creando usuario padre: " . $padre_id->get_error_message());
    echo "✔ Usuario Padre creado (ID: $padre_id)\n";

    // 2. Crear Núcleo Familiar
    $nucleo_id = wp_insert_post([
        'post_type'   => 'nucleo_familiar',
        'post_title'  => 'Familia Test E2E',
        'post_status' => 'publish'
    ]);
    if ( is_wp_error($nucleo_id) ) throw new Exception("Error creador núcleo.");
    
    // Asignar Padre al Núcleo y limpiamos caché ACF si existiera
    update_field('ed_nucleo_adultos', [$padre_id], $nucleo_id);
    echo "✔ Núcleo Familiar creado (ID: $nucleo_id) y asignado al Padre.\n";

    // 3. Crear Jugador y asignarlo al Núcleo y Deporte
    $deporte_id = wp_insert_post([
        'post_type' => 'deporte',
        'post_title' => 'Basket Test',
        'post_status' => 'publish'
    ]);
    
    $jugador_id = wp_insert_post([
        'post_type'   => 'jugador',
        'post_title'  => 'Jugador Test Hijo',
        'post_status' => 'publish'
    ]);
    update_field('ed_jugador_nucleo', $nucleo_id, $jugador_id);
    update_field('ed_jugador_estado', 'activo', $jugador_id);
    update_field('ed_jugador_deporte', $deporte_id, $jugador_id);

    echo "✔ Jugador creado (ID: $jugador_id) y asignado al Núcleo y Deporte.\n";

    // 4. Testear métodos del Repositorio de Núcleo
    $nucleo_buscado = ED_Nucleo_Repository::find_for_user($padre_id);
    if ($nucleo_buscado && $nucleo_buscado->ID === $nucleo_id) {
        echo "✔ ED_Nucleo_Repository::find_for_user() localiza correctamente el núcleo.\n";
    } else {
        echo "❌ ERROR: No se encontró el núcleo para el usuario parent.\n";
    }

    $jugadores_del_nucleo = ED_Nucleo_Repository::get_jugador_ids_for_nucleo($nucleo_id);
    if (in_array($jugador_id, $jugadores_del_nucleo)) {
        echo "✔ ED_Nucleo_Repository::get_jugador_ids_for_nucleo() retorna al jugador correctamente.\n";
    } else {
        echo "❌ ERROR: Jugador no encontrado en el núcleo mediante query directa a postmeta.\n";
    }

    // 5. Testear el Dashboard (KPIS)
    echo "\nInvalidando caché del Dashboard...\n";
    if (class_exists('ED_Dashboard_Admin')) {
        ED_Dashboard_Admin::invalidar_cache();
        $kpis = ED_Dashboard_Admin::get_kpis();
        
        echo "KPI: Jugadores activos detectados globales = " . $kpis['inscripciones_activas'] . "\n";
        if ($kpis['inscripciones_activas'] >= 1) {
            echo "✔ Dashboard lee correctamente los jugadores activos.\n";
        } else {
            echo "❌ ERROR: Dashboard no detectó el jugador activo.\n";
        }

        $insc_deporte = ED_Dashboard_Admin::get_inscripciones_por_deporte();
        $deporte_encontrado = false;
        foreach($insc_deporte as $d) {
            if ($d['deporte_id'] === $deporte_id && $d['total'] >= 1) {
                $deporte_encontrado = true;
                break;
            }
        }
        if ($deporte_encontrado) {
            echo "✔ Dashboard agrupa las inscripciones por deporte correctamente.\n";
        } else {
            echo "❌ ERROR: Dashboard no refleja la inscripción por deporte.\n";
        }
    } else {
        echo "❌ ERROR: La clase ED_Dashboard_Admin no está instanciada.\n";
    }

    // Limpieza
    echo "\n============================================\n";
    echo "Limpiando datos de prueba...\n";
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($padre_id);
    wp_delete_post($nucleo_id, true);
    wp_delete_post($jugador_id, true);
    wp_delete_post($deporte_id, true);
    echo "✔ Limpieza masiva completada.\n";

} catch (Exception $e) {
    echo "Excepción: " . $e->getMessage() . "\n";
}
exit;
