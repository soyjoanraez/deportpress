<?php
/**
 * Bar / comandes anticipades (Fase 6).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Productes i comandes del bar per torneig.
 */
class ED_Bar {

	/**
	 * @return array<int, object>
	 */
	public static function get_productos( int $torneo_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_bar_productos
				WHERE torneo_id = %d AND disponible = 1
				ORDER BY categoria ASC, orden ASC, id ASC",
				$torneo_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function get_producto( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_bar_productos WHERE id = %d",
				$id
			)
		);
		return $row ?: null;
	}

	/**
	 * @param array{nombre:string,descripcion?:string,precio:float|string,categoria?:string,orden?:int,foto_id?:int} $datos
	 */
	public static function crear_producto( int $torneo_id, array $datos ): int|false {
		global $wpdb;
		$row = array(
			'torneo_id'   => $torneo_id,
			'nombre'      => sanitize_text_field( $datos['nombre'] ),
			'descripcion' => sanitize_text_field( (string) ( $datos['descripcion'] ?? '' ) ),
			'precio'      => round( (float) $datos['precio'], 2 ),
			'categoria'   => sanitize_text_field( (string) ( $datos['categoria'] ?? '' ) ),
			'disponible'  => 1,
			'orden'       => (int) ( $datos['orden'] ?? 0 ),
		);
		$fmt = array( '%d', '%s', '%s', '%f', '%s', '%d', '%d' );
		if ( ! empty( $datos['foto_id'] ) ) {
			$row['foto_id'] = (int) $datos['foto_id'];
			$fmt[]          = '%d';
		}
		$ok = $wpdb->insert( $wpdb->prefix . 'ed_bar_productos', $row, $fmt );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function toggle_disponible( int $producto_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}ed_bar_productos SET disponible = 1 - disponible WHERE id = %d",
				$producto_id
			)
		);
	}

	/**
	 * @return string[]
	 */
	public static function get_franjas( int $torneo_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}
		$franjas_raw = get_field( ED_Torneos::TOR_BAR_FRANJAS, $torneo_id );
		if ( ! $franjas_raw ) {
			return array();
		}
		if ( is_array( $franjas_raw ) ) {
			return array_values(
				array_filter(
					array_map(
						static function ( $f ) {
							return is_string( $f ) ? trim( $f ) : '';
						},
						$franjas_raw
					)
				)
			);
		}
		return array_filter( array_map( 'trim', explode( "\n", (string) $franjas_raw ) ) );
	}

	/**
	 * @param array<int, array{producto_id:int, cantidad?:int}> $lineas
	 * @return array{ok:bool, pedido_id?:int, total?:float, franja?:string, error?:string}
	 */
	public static function crear_pedido(
		int $torneo_id,
		string $nombre_cliente,
		string $franja_horaria,
		string $metodo_pago,
		array $lineas,
		?int $user_id = null,
		string $telefono = '',
		string $notas = ''
	): array {
		if ( empty( $lineas ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'La comanda està buida.', 'escuela-deportiva-core' ),
			);
		}

		$franjas_validas = self::get_franjas( $torneo_id );
		if ( ! in_array( $franja_horaria, $franjas_validas, true ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Franja horària no vàlida.', 'escuela-deportiva-core' ),
			);
		}

		global $wpdb;
		$total          = 0.0;
		$lineas_limpias = array();

		foreach ( $lineas as $linea ) {
			$producto = self::get_producto( (int) ( $linea['producto_id'] ?? 0 ) );
			if ( ! $producto || ! (int) $producto->disponible ) {
				continue;
			}

			$cantidad    = max( 1, (int) ( $linea['cantidad'] ?? 1 ) );
			$precio_unit = (float) $producto->precio;
			$total      += $precio_unit * $cantidad;
			$lineas_limpias[] = array(
				'producto_id' => (int) $producto->id,
				'cantidad'    => $cantidad,
				'precio_unit' => $precio_unit,
			);
		}

		if ( empty( $lineas_limpias ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Cap producte disponible a la comanda.', 'escuela-deportiva-core' ),
			);
		}

		$metodo = in_array( $metodo_pago, array( 'online', 'efectivo' ), true ) ? $metodo_pago : 'efectivo';

		$wpdb->insert(
			$wpdb->prefix . 'ed_bar_pedidos',
			array(
				'torneo_id'       => $torneo_id,
				'user_id'         => $user_id,
				'nombre_cliente'  => sanitize_text_field( $nombre_cliente ),
				'telefono'        => sanitize_text_field( $telefono ),
				'franja_horaria'  => $franja_horaria,
				'metodo_pago'     => $metodo,
				'estado'          => 'pendiente',
				'notas'           => sanitize_text_field( $notas ),
				'total'           => round( $total, 2 ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f' )
		);

		$pedido_id = (int) $wpdb->insert_id;

		foreach ( $lineas_limpias as $linea ) {
			$wpdb->insert(
				$wpdb->prefix . 'ed_bar_lineas',
				array(
					'pedido_id'   => $pedido_id,
					'producto_id' => $linea['producto_id'],
					'cantidad'    => $linea['cantidad'],
					'precio_unit' => $linea['precio_unit'],
				),
				array( '%d', '%d', '%d', '%f' )
			);
		}

		do_action( 'ed_bar_pedido_creado', $pedido_id, $torneo_id );

		return array(
			'ok'        => true,
			'pedido_id' => $pedido_id,
			'total'     => round( $total, 2 ),
			'franja'    => $franja_horaria,
		);
	}

	public static function actualizar_estado( int $pedido_id, string $nuevo_estado ): bool {
		$estados_validos = array( 'pendiente', 'preparando', 'listo', 'entregado', 'cancelado' );
		if ( ! in_array( $nuevo_estado, $estados_validos, true ) ) {
			return false;
		}

		global $wpdb;
		$ok = $wpdb->update(
			$wpdb->prefix . 'ed_bar_pedidos',
			array( 'estado' => $nuevo_estado ),
			array( 'id' => $pedido_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $ok && 'listo' === $nuevo_estado ) {
			do_action( 'ed_bar_pedido_listo', $pedido_id );
		}

		return (bool) $ok;
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_pedidos( int $torneo_id, ?string $franja = null, ?string $estado = null ): array {
		global $wpdb;
		$tabla_p = $wpdb->prefix . 'ed_bar_pedidos';
		$tabla_l = $wpdb->prefix . 'ed_bar_lineas';
		$tabla_pr = $wpdb->prefix . 'ed_bar_productos';

		$where  = array( 'p.torneo_id = %d' );
		$params = array( $torneo_id );
		if ( $franja ) {
			$where[]  = 'p.franja_horaria = %s';
			$params[] = $franja;
		}
		if ( $estado ) {
			$where[]  = 'p.estado = %s';
			$params[] = $estado;
		}

		$sql     = "SELECT p.* FROM {$tabla_p} p WHERE " . implode( ' AND ', $where ) . ' ORDER BY p.franja_horaria ASC, p.creado_en ASC';
		$pedidos = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( ! is_array( $pedidos ) ) {
			return array();
		}

		foreach ( $pedidos as &$pedido ) {
			$pedido->lineas = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT l.cantidad, l.precio_unit, pr.nombre, pr.categoria
					FROM {$tabla_l} l
					JOIN {$tabla_pr} pr ON pr.id = l.producto_id
					WHERE l.pedido_id = %d",
					$pedido->id
				)
			);
		}

		return $pedidos;
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_pedidos_usuario( int $user_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, po.post_title AS torneo_nombre
				FROM {$wpdb->prefix}ed_bar_pedidos p
				LEFT JOIN {$wpdb->posts} po ON po.ID = p.torneo_id
				WHERE p.user_id = %d
				ORDER BY p.creado_en DESC
				LIMIT 20",
				$user_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array{total_pedidos:int,pendientes:int,preparando:int,listos:int,ingresos:float}
	 */
	public static function get_resumen( int $torneo_id ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_bar_pedidos';

		return array(
			'total_pedidos' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado != 'cancelado'", $torneo_id ) ),
			'pendientes'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado = 'pendiente'", $torneo_id ) ),
			'preparando'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado = 'preparando'", $torneo_id ) ),
			'listos'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado = 'listo'", $torneo_id ) ),
			'ingresos'      => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total),0) FROM {$tabla} WHERE torneo_id = %d AND estado != 'cancelado' AND metodo_pago = 'online'", $torneo_id ) ),
		);
	}
}
