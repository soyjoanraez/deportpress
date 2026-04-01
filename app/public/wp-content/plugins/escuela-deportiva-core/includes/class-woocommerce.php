<?php
/**
 * Integració WooCommerce (compatibilitat HPOS).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declaracions de compatibilitat amb WooCommerce 8+.
 */
class ED_WooCommerce {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 10, 1 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_entrada_aforo' ), 10, 6 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'add_category_filter' ), 25 );
	}

	/**
	 * Activa anuncis o fitxes de directori segons SKU (Fase 5).
	 *
	 * @param int $order_id ID de comanda.
	 */
	public function on_payment_complete( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$tiene_entrada = false;
		foreach ( $order->get_items() as $item ) {
			$p = $item->get_product();
			if ( $p && str_starts_with( (string) $p->get_sku(), 'entrada-' ) ) {
				$tiene_entrada = true;
				break;
			}
		}
		if ( $tiene_entrada ) {
			ED_Entradas_QR::generar_desde_order( $order_id );
		}

		if ( ! function_exists( 'update_field' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$sku = (string) $product->get_sku();
			if ( '' === $sku ) {
				continue;
			}
			if ( str_starts_with( $sku, 'pub-' ) ) {
				$anuncio_id = (int) $order->get_meta( '_ed_anuncio_id' );
				if ( $anuncio_id ) {
					update_field( 'ed_anuncio_activo', '1', $anuncio_id );
					ED_Publicidad::invalidate_all_positions();
				}
			}
			if ( str_starts_with( $sku, 'dir-' ) ) {
				$empresa_id = (int) $order->get_meta( '_ed_empresa_id' );
				if ( $empresa_id && 'empresa_directorio' === get_post_type( $empresa_id ) ) {
					wp_update_post(
						array(
							'ID'          => $empresa_id,
							'post_status' => 'publish',
						)
					);
					if ( str_contains( $sku, 'banner' ) ) {
						update_field( 'ed_emp_dir_anuncio_activo', '1', $empresa_id );
					}
				}
			}
		}
	}

	/**
	 * Limita l’aforament segons ACF del torneig (SKU entrada-{id}).
	 *
	 * @param mixed $variation_id ID variació.
	 * @param mixed $variations Atributs variació.
	 * @param mixed $cart_item_data Dades extra.
	 */
	public function validate_entrada_aforo( $valid, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $valid || ! function_exists( 'wc_get_product' ) || ! function_exists( 'get_field' ) ) {
			return (bool) $valid;
		}
		$product = wc_get_product( (int) $product_id );
		if ( ! $product ) {
			return (bool) $valid;
		}
		$sku = (string) $product->get_sku();
		if ( ! str_starts_with( $sku, 'entrada-' ) ) {
			return (bool) $valid;
		}
		$torneo_id = (int) str_replace( 'entrada-', '', $sku );
		if ( $torneo_id <= 0 ) {
			return (bool) $valid;
		}
		$aforo_max = (int) get_field( ED_Torneos::TOR_AFORO_MAXIMO, $torneo_id );
		if ( $aforo_max <= 0 ) {
			return (bool) $valid;
		}
		$stats       = ED_Entradas_QR::get_stats_torneo( $torneo_id );
		$disponibles = $aforo_max - (int) $stats['total'];
		$qty         = (int) $quantity;
		if ( $qty > max( 0, $disponibles ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %d: remaining tickets */
					__( 'Només queden %d entrades disponibles.', 'escuela-deportiva-core' ),
					max( 0, $disponibles )
				),
				'error'
			);
			return false;
		}
		return (bool) $valid;
	}

	/**
	 * Declara suport per a taules d’altes prestacions (HPOS).
	 */
	public function declare_hpos(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ED_PLUGIN_FILE, true );
		}
	}

	/**
	 * Añade un filtro de categorías genérico en la vista de la tienda.
	 */
	public function add_category_filter(): void {
		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
			return;
		}

		echo '<form class="woocommerce-ordering ed-category-filter" method="get" style="margin-right:15px;">';
		wc_product_dropdown_categories(
			array(
				'show_option_none' => __( 'Todas las categorías', 'escuela-deportiva-core' ),
				'value_field'      => 'slug',
				'selected'         => isset( $_GET['product_cat'] ) ? wc_clean( wp_unslash( $_GET['product_cat'] ) ) : '',
				'name'             => 'product_cat',
				'class'            => 'dropdown_product_cat',
			)
		);
		wc_query_string_form_fields( null, array( 'product_cat', 'submit', 'paged' ) );
		echo '</form>';

		static $js_loaded = false;
		if ( ! $js_loaded && function_exists( 'wc_enqueue_js' ) ) {
			wc_enqueue_js( "
				jQuery('.ed-category-filter .dropdown_product_cat').change(function() {
					jQuery(this).closest('form').submit();
				});
			" );
			$js_loaded = true;
		}
	}
}
