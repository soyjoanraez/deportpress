<?php
/**
 * Test E2E - Escuela Deportiva Core
 */

require_once dirname( __FILE__ ) . '/wp-load.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando pruebas E2E...\n";
echo "============================\n\n";

// 1. Crear Deporte y Categoría
$deporte_id = wp_insert_post([
    'post_type' => 'deporte',
    'post_title' => 'Fútbol Test',
    'post_status' => 'publish'
]);
update_field('ed_dep_importe_total', 200, $deporte_id);
echo "✔ Deporte creado (ID: $deporte_id)\n";

$categoria_id = wp_insert_post([
    'post_type' => 'categoria',
    'post_title' => 'Alevín Test',
    'post_status' => 'publish'
]);
update_field('ed_cat_deporte', $deporte_id, $categoria_id);
echo "✔ Categoría creada (ID: $categoria_id)\n";

// 2. Crear 4 Equipos
$equipos_ids = [];
for ($i=1; $i<=4; $i++) {
    $eq_id = wp_insert_post([
        'post_type' => 'equipo',
        'post_title' => "Equipo Test $i",
        'post_status' => 'publish'
    ]);
    update_field('ed_eq_categoria', $categoria_id, $eq_id);
    $equipos_ids[] = $eq_id;
    echo "✔ Equipo creado (ID: $eq_id)\n";
}

// 3. Crear Torneo
$torneo_id = wp_insert_post([
    'post_type' => 'torneo',
    'post_title' => 'Copa E2E Test',
    'post_status' => 'publish'
]);
update_field('ed_tor_tipo', 'eliminatoria', $torneo_id); // Eliminatoria
update_field('ed_tor_estado', 'inscripciones', $torneo_id);
update_field('ed_tor_equipos', $equipos_ids, $torneo_id);

echo "✔ Torneo creado (ID: $torneo_id)\n";

// 4. Generar cuadro del Torneo
ED_Torneos::generar_cuadro($torneo_id);
echo "✔ Cuadro del torneo generado\n";

// 5. Verificar los partidos creados en el torneo
$partidos = get_posts([
    'post_type' => 'partido',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'ed_par_torneo',
            'value' => $torneo_id
        ]
    ]
]);

echo "✔ Se han creado " . count($partidos) . " partidos para el cuadro eliminatorio en total.\n";

$primera_ronda = [];
foreach($partidos as $p) {
    $fase = get_field('ed_par_fase', $p->ID);
    $eq_loc = get_field('ed_par_equipo_local', $p->ID);
    if ('semifinal_1' === $fase || 'semifinal_2' === $fase) {
        if ($eq_loc) {
            $primera_ronda[] = $p->ID;
        }
    }
}

if (count($primera_ronda) > 0) {
    $partido_test = $primera_ronda[0];
    echo "✔ Seleccionado partido de Primera Ronda (Semis) para simular resultado (ID: $partido_test)\n";
    
    // Simular que el local marca 3 goles y visitante 1
    update_field('ed_par_goles_local', 3, $partido_test);
    update_field('ed_par_goles_visitante', 1, $partido_test);
    
    // Registrar evento de fin de partido para desencadenar el avance
    echo "✔ Registrando evento de 'fin_partido'...\n";
    ED_Partidos_Eventos::registrar($partido_test, 'fin_partido');
    
    echo "✔ Evento disparado. Verificando avance...\n";
    
    // El local debería haber avanzado al partido "final"
    $partido_final = get_posts([
        'post_type' => 'partido',
        'posts_per_page' => 1,
        'meta_query' => [
            ['key' => 'ed_par_torneo', 'value' => $torneo_id],
            ['key' => 'ed_par_fase', 'value' => 'final']
        ]
    ]);
    
    if (!empty($partido_final)) {
        $pf = $partido_final[0];
        $final_loc = get_field('ed_par_equipo_local', $pf->ID);
        $final_vis = get_field('ed_par_equipo_visitante', $pf->ID);
        $ganador_esperado = get_field('ed_par_equipo_local', $partido_test); // Porque marcó 3 (vs 1)
        
        echo "Partido Final (ID: {$pf->ID}) -> Local: " . ($final_loc ? get_the_title($final_loc) : '???') . " vs Visitante: " . ($final_vis ? get_the_title($final_vis) : '???') . "\n";
        
        if ((int)$final_loc === (int)$ganador_esperado || (int)$final_vis === (int)$ganador_esperado) {
            echo "🎉 ¡ÉXITO! El equipo ganador (".get_the_title($ganador_esperado).") ha avanzado a la gran final automáticamente.\n";
        } else {
            echo "❌ ERROR: El equipo ganador no ha avanzado a la final.\n";
        }
    } else {
        echo "❌ ERROR: No se encontró el partido de la final.\n";
    }
} else {
    echo "❌ ERROR: No se generaron partidos de primera ronda con equipos asignados.\n";
}

// 6. Limpieza
echo "\n============================\n";
echo "Limpiando datos de prueba...\n";
foreach($partidos as $p) { wp_delete_post($p->ID, true); }
wp_delete_post($torneo_id, true);
foreach($equipos_ids as $eq) { wp_delete_post($eq, true); }
wp_delete_post($categoria_id, true);
wp_delete_post($deporte_id, true);
echo "✔ Limpieza completada.\n";
