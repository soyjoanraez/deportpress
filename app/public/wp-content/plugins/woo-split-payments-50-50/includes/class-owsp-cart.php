<?php
/**
 * Integración con producto, carrito y checkout.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lógica de selección 50/50 y ajuste de importes.
 */
class OWSP_Cart {

	private const FLAG_SELECTED      = '_owsp_split_selected';
	private const META_DUE_TYPE      = '_owsp_due_type';
	private const META_DUE_DATE      = '_owsp_due_date';
	private const META_DUE_DAYS      = '_owsp_due_days';
	private const META_ORIGINAL_UNIT = '_owsp_original_unit_price';

	/**
	 * Hooks de carrito.
	 */
	public static function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_product_choice' ) );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_variation_plan_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'capture_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'restore_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'render_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_split_prices' ), 20 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'render_balance_total_row' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( __CLASS__, 'render_balance_total_row' ) );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_before_checkout' ) );

		// Checkout selection hooks
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( __CLASS__, 'render_checkout_item_selector' ), 10, 3 );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'update_checkout_session' ) );
	}

	/**
	 * UI en producto simple/variable.
	 */
	public static function render_product_choice(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			self::render_variable_product_choice( $product );
			return;
		}

		$product_id = $product->get_id();
		$mode       = OWSP_Product_Settings::get_split_mode( $product_id );

		if ( 'disabled' === $mode || ! OWSP_Product_Settings::has_valid_due_configuration( $product_id ) ) {
			return;
		}

		self::render_choice_markup( $mode, OWSP_Product_Settings::get_plan_label( $product_id ) );
	}

	/**
	 * Añade datos 50/50 a cada variación para la UI.
	 *
	 * @param array<string, mixed> $data      Datos de variación.
	 * @param WC_Product           $product   Producto variable.
	 * @param WC_Product_Variation $variation Variación concreta.
	 * @return array<string, mixed>
	 */
	public static function add_variation_plan_data( array $data, WC_Product $product, WC_Product_Variation $variation ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$variation_id = $variation->get_id();
		$mode         = OWSP_Product_Settings::get_split_mode( $variation_id );
		$is_valid     = OWSP_Product_Settings::has_valid_due_configuration( $variation_id );

		$data['owsp_split_mode']   = $mode;
		$data['owsp_has_valid_due'] = $is_valid;
		$data['owsp_plan_label']   = $is_valid ? OWSP_Product_Settings::get_plan_label( $variation_id ) : '';

		return $data;
	}

	/**
	 * Valida selección y vencimiento.
	 *
	 * @param bool $passed Resultado previo.
	 * @param int  $product_id Producto.
	 * @param int  $quantity Cantidad.
	 * @param int  $variation_id Variación.
	 */
	public static function validate_add_to_cart( bool $passed, int $product_id, int $quantity, int $variation_id = 0 ): bool {
		$target_product_id = $variation_id ?: $product_id;
		$mode              = OWSP_Product_Settings::get_split_mode( $target_product_id );

		if ( 'disabled' === $mode ) {
			return $passed;
		}

		$plan = 'forced' === $mode ? 'split' : ( isset( $_POST['owsp_payment_plan'] ) ? sanitize_text_field( wp_unslash( $_POST['owsp_payment_plan'] ) ) : 'full' );

		if ( 'split' !== $plan ) {
			return $passed;
		}

		if ( ! OWSP_Product_Settings::has_valid_due_configuration( $target_product_id ) ) {
			wc_add_notice( __( 'Este producto no tiene configurado correctamente el segundo pago.', OWSP_TEXTDOMAIN ), 'error' );
			return false;
		}

		$config   = OWSP_Product_Settings::get_due_configuration( $target_product_id );
		$due_date = OWSP_Product_Settings::resolve_due_date( $config );

		if ( '' === $due_date || $due_date < OWSP_Plugin::today() ) {
			wc_add_notice( __( 'La fecha del segundo pago es inválida o ya ha pasado.', OWSP_TEXTDOMAIN ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Guarda la elección 50/50 en la línea de carrito.
	 *
	 * @param array<string, mixed> $cart_item_data Datos previos.
	 * @param int                  $product_id ID producto.
	 * @param int                  $variation_id ID variación.
	 * @return array<string, mixed>
	 */
	public static function capture_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$target_product_id = $variation_id ?: $product_id;
		$mode              = OWSP_Product_Settings::get_split_mode( $target_product_id );

		if ( 'disabled' === $mode ) {
			return $cart_item_data;
		}

		$config = OWSP_Product_Settings::get_due_configuration( $target_product_id );
		$plan   = 'forced' === $mode ? 'split' : ( isset( $_POST['owsp_payment_plan'] ) ? sanitize_text_field( wp_unslash( $_POST['owsp_payment_plan'] ) ) : 'full' );

		if ( 'split' === $plan ) {
			$cart_item_data[ self::FLAG_SELECTED ] = 'yes';
		}

		// Siempre guardamos la configuración para que el Checkout pueda usarla
		$cart_item_data[ self::META_DUE_TYPE ] = $config['type'];
		$cart_item_data[ self::META_DUE_DATE ] = $config['date'];
		$cart_item_data[ self::META_DUE_DAYS ] = $config['days'];
		$cart_item_data['owsp_unique_key']     = md5( $target_product_id . '|' . $plan . '|' . $config['type'] . '|' . $config['date'] . '|' . $config['days'] . '|' . microtime( true ) );

		return $cart_item_data;
	}

	/**
	 * Recupera datos de sesión del carrito.
	 *
	 * @param array<string, mixed> $cart_item Elemento restaurado.
	 * @param array<string, mixed> $values Sesión.
	 * @return array<string, mixed>
	 */
	public static function restore_cart_item_from_session( array $cart_item, array $values ): array {
		foreach ( array( self::FLAG_SELECTED, self::META_DUE_TYPE, self::META_DUE_DATE, self::META_DUE_DAYS, self::META_ORIGINAL_UNIT ) as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$cart_item[ $key ] = $values[ $key ];
			}
		}

		return $cart_item;
	}

	/**
	 * Ajusta el precio unitario al 50%.
	 */
	public static function apply_split_prices( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Capturamos el cambio en vivo antes de que WC calcule los totales tarde (soluciona el bug del desfase por 1 paso)
		$is_update_checkout = ( isset( $_GET['wc-ajax'] ) && 'update_order_review' === $_GET['wc-ajax'] ) || ( isset( $_POST['action'] ) && 'woocommerce_update_order_review' === $_POST['action'] );
		if ( wp_doing_ajax() && $is_update_checkout && isset( $_POST['post_data'] ) ) {
			self::update_checkout_session( wp_unslash( $_POST['post_data'] ) );
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			// Clonar los productos para que al modificar el precio de uno no modifique el de otro cart_item idéntico
			if ( isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
				$cart->cart_contents[ $cart_item_key ]['data'] = clone $cart_item['data'];
			}

			if ( ! self::is_item_split( $cart_item, $cart_item_key ) || ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				// Restaurar precio original si de repente cambia a 'full' en el checkout
				if ( isset( $cart->cart_contents[ $cart_item_key ][ self::META_ORIGINAL_UNIT ] ) && isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
					$cart->cart_contents[ $cart_item_key ]['data']->set_price( $cart->cart_contents[ $cart_item_key ][ self::META_ORIGINAL_UNIT ] );
				}
				continue;
			}

			if ( ! isset( $cart->cart_contents[ $cart_item_key ][ self::META_ORIGINAL_UNIT ] ) ) {
				$cart->cart_contents[ $cart_item_key ][ self::META_ORIGINAL_UNIT ] = (float) $cart_item['data']->get_price( 'edit' );
			}

			$original_price = (float) $cart->cart_contents[ $cart_item_key ][ self::META_ORIGINAL_UNIT ];
			$split_price    = OWSP_Plugin::round_amount( $original_price / 2 );

			$cart->cart_contents[ $cart_item_key ]['data']->set_price( $split_price );
		}
	}

	/**
	 * Muestra los datos 50/50 en carrito y checkout.
	 *
	 * @param array<int, array{name:string,value:string}> $item_data Meta visible.
	 * @param array<string, mixed>                        $cart_item Línea de carrito.
	 * @return array<int, array{name:string,value:string}>
	 */
	public static function render_cart_item_data( array $item_data, array $cart_item ): array {
		$target_product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id'];
		$mode              = OWSP_Product_Settings::get_split_mode( $target_product_id );
		$cart_item_key     = $cart_item['key'] ?? '';

		if ( 'disabled' === $mode ) {
			return $item_data;
		}

		if ( ! self::is_item_split( $cart_item, $cart_item_key ) ) {
			$item_data[] = array(
				'name'  => __( 'Plan de pago', OWSP_TEXTDOMAIN ),
				'value' => __( 'Pago único (100% hoy)', OWSP_TEXTDOMAIN ),
			);
			return $item_data;
		}

		// Si se calculó fraccionado, construimos la fecha:
		$config = array(
			'type' => (string) ( $cart_item[ self::META_DUE_TYPE ] ?? '' ),
			'date' => (string) ( $cart_item[ self::META_DUE_DATE ] ?? '' ),
			'days' => (int) ( $cart_item[ self::META_DUE_DAYS ] ?? 0 ),
		);

		$due_date = OWSP_Product_Settings::resolve_due_date( $config );

		// Fallback infalible: Si el cálculo del carrito está vacío, mira a la BD
		if ( empty( $due_date ) ) {
			$config   = OWSP_Product_Settings::get_due_configuration( $target_product_id );
			$due_date = OWSP_Product_Settings::resolve_due_date( $config );
		}

		$pretty_date = ! empty( $due_date ) ? wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() ) : __( 'Pendiente (Recuerda darle a "Actualizar" en tu producto tras poner la fecha)', OWSP_TEXTDOMAIN );

		$item_data[] = array(
			'name'  => __( 'Plan de pago', OWSP_TEXTDOMAIN ),
			'value' => sprintf(
				/* translators: %s: due date */
				__( 'Pago dividido (50%% hoy y 50%% el %s)', OWSP_TEXTDOMAIN ),
				$pretty_date
			),
		);

		return $item_data;
	}

	/**
	 * Fila resumen del saldo futuro.
	 */
	public static function render_balance_total_row(): void {
		if ( ! ( WC()->cart instanceof WC_Cart ) ) {
			return;
		}

		$pending_total = self::get_pending_balance_total( WC()->cart );
		if ( $pending_total <= 0 ) {
			return;
		}

		echo '<tr class="owsp-balance-row">';
		echo '<th>' . esc_html__( 'Segundo pago futuro', OWSP_TEXTDOMAIN ) . '</th>';
		echo '<td data-title="' . esc_attr__( 'Segundo pago futuro', OWSP_TEXTDOMAIN ) . '">' . wp_kses_post( wc_price( $pending_total ) ) . '</td>';
		echo '</tr>';
	}

	/**
	 * Bloquea cupones fijos para evitar saldos incoherentes.
	 */
	public static function validate_cart_before_checkout(): void {
		if ( ! ( WC()->cart instanceof WC_Cart ) || ! self::cart_has_split_items( WC()->cart ) ) {
			return;
		}

		foreach ( WC()->cart->get_coupons() as $coupon ) {
			if ( ! $coupon instanceof WC_Coupon ) {
				continue;
			}

			if ( in_array( $coupon->get_discount_type(), array( 'fixed_cart', 'fixed_product' ), true ) ) {
				wc_add_notice(
					__( 'Los pagos 50/50 no son compatibles todavía con cupones de importe fijo. Usa un cupón porcentual o retira el cupón.', OWSP_TEXTDOMAIN ),
					'error'
				);
				return;
			}
		}
	}

	/**
	 * Total que se cobrará en el segundo pedido.
	 */
	public static function get_pending_balance_total( WC_Cart $cart ): float {
		$total = 0.0;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! self::is_item_split( $cart_item ) ) {
				continue;
			}

			$total += (float) ( $cart_item['line_total'] ?? 0 );
			$total += (float) ( $cart_item['line_tax'] ?? 0 );
		}

		return OWSP_Plugin::round_amount( $total );
	}

	/**
	 * Indica si el carrito contiene líneas 50/50.
	 */
	public static function cart_has_split_items( WC_Cart $cart ): bool {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( self::is_item_split( $cart_item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render del selector para productos variables.
	 */
	private static function render_variable_product_choice( WC_Product $product ): void {
		if ( ! self::variable_product_has_split_option( $product ) ) {
			return;
		}

		echo '<div class="owsp-product-choice" data-owsp-variable-choice="yes" style="margin:16px 0;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">';
		echo '<div class="owsp-choice-placeholder">';
		echo '<strong>' . esc_html__( 'Forma de pago de este producto', OWSP_TEXTDOMAIN ) . '</strong>';
		echo '<p style="margin:8px 0 0;">' . esc_html__( 'Selecciona una variación para ver si admite pago 50/50 y su fecha del segundo pago.', OWSP_TEXTDOMAIN ) . '</p>';
		echo '</div>';

		echo '<div class="owsp-choice-disabled" style="display:none;">';
		echo '<strong>' . esc_html__( 'Forma de pago de este producto', OWSP_TEXTDOMAIN ) . '</strong>';
		echo '<p style="margin:8px 0 0;">' . esc_html__( 'La variación seleccionada se paga al 100% ahora.', OWSP_TEXTDOMAIN ) . '</p>';
		echo '</div>';

		echo '<div class="owsp-choice-forced" style="display:none;">';
		echo '<input type="hidden" name="owsp_payment_plan" value="split" disabled="disabled" class="owsp-plan-forced" />';
		echo '<strong>' . esc_html__( 'Este producto se paga en 2 plazos.', OWSP_TEXTDOMAIN ) . '</strong>';
		echo '<p style="margin:8px 0 0;" class="owsp-plan-forced-label"></p>';
		echo '</div>';

		echo '<div class="owsp-choice-optional" style="display:none;">';
		echo '<strong>' . esc_html__( 'Forma de pago de este producto', OWSP_TEXTDOMAIN ) . '</strong>';
		echo '<p style="margin:8px 0 12px;">' . esc_html__( 'Puedes pagar el 100% ahora o dividirlo en dos pagos del 50%.', OWSP_TEXTDOMAIN ) . '</p>';
		echo '<label style="display:block;margin-bottom:8px;">';
		echo '<input type="radio" name="owsp_payment_plan" value="full" checked="checked" disabled="disabled" class="owsp-plan-radio owsp-plan-full" /> ';
		echo esc_html__( 'Pagar 100% ahora', OWSP_TEXTDOMAIN );
		echo '</label>';
		echo '<label style="display:block;">';
		echo '<input type="radio" name="owsp_payment_plan" value="split" disabled="disabled" class="owsp-plan-radio owsp-plan-split" /> ';
		echo '<span class="owsp-plan-split-label"></span>';
		echo '</label>';
		echo '</div>';
		echo '</div>';

		self::enqueue_variation_choice_script();
	}

	/**
	 * Render base del selector 50/50.
	 */
	private static function render_choice_markup( string $mode, string $label ): void {
		echo '<div class="owsp-product-choice" style="margin:16px 0;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">';

		if ( 'forced' === $mode ) {
			echo '<input type="hidden" name="owsp_payment_plan" value="split" />';
			printf(
				'<strong>%1$s</strong><p style="margin:8px 0 0;">%2$s</p>',
				esc_html__( 'Este producto se paga en 2 plazos.', OWSP_TEXTDOMAIN ),
				esc_html( $label )
			);
			echo '</div>';
			return;
		}

		echo '<strong>' . esc_html__( 'Forma de pago de este producto', OWSP_TEXTDOMAIN ) . '</strong>';
		echo '<p style="margin:8px 0 12px;">' . esc_html__( 'Puedes pagar el 100% ahora o dividirlo en dos pagos del 50%.', OWSP_TEXTDOMAIN ) . '</p>';
		echo '<label style="display:block;margin-bottom:8px;">';
		echo '<input type="radio" name="owsp_payment_plan" value="full" checked="checked" /> ';
		echo esc_html__( 'Pagar 100% ahora', OWSP_TEXTDOMAIN );
		echo '</label>';
		echo '<label style="display:block;">';
		echo '<input type="radio" name="owsp_payment_plan" value="split" /> ';
		echo esc_html( $label );
		echo '</label>';
		echo '</div>';
	}

	/**
	 * Indica si un variable tiene alguna variación con 50/50 usable.
	 */
	private static function variable_product_has_split_option( WC_Product $product ): bool {
		if ( OWSP_Product_Settings::supports_split( $product->get_id() ) && OWSP_Product_Settings::has_valid_due_configuration( $product->get_id() ) ) {
			return true;
		}

		foreach ( $product->get_children() as $variation_id ) {
			if ( OWSP_Product_Settings::supports_split( (int) $variation_id ) && OWSP_Product_Settings::has_valid_due_configuration( (int) $variation_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * JS para alinear la UI con la variación seleccionada.
	 */
	private static function enqueue_variation_choice_script(): void {
		static $script_loaded = false;

		if ( $script_loaded || ! function_exists( 'wc_enqueue_js' ) ) {
			return;
		}

		$script_loaded = true;

		wc_enqueue_js(
			"
			(function($){
				function setOWSPState(\$box, variation) {
					var \$placeholder = \$box.find('.owsp-choice-placeholder');
					var \$disabled = \$box.find('.owsp-choice-disabled');
					var \$forced = \$box.find('.owsp-choice-forced');
					var \$optional = \$box.find('.owsp-choice-optional');
					var \$forcedInput = \$box.find('.owsp-plan-forced');
					var \$optionalInputs = \$box.find('.owsp-plan-radio');
					var \$fullRadio = \$box.find('.owsp-plan-full');
					var \$splitLabel = \$box.find('.owsp-plan-split-label');
					var \$forcedLabel = \$box.find('.owsp-plan-forced-label');

					\$placeholder.hide();
					\$disabled.hide();
					\$forced.hide();
					\$optional.hide();
					\$forcedInput.prop('disabled', true);
					\$optionalInputs.prop('disabled', true);

					if (!variation) {
						\$fullRadio.prop('checked', true);
						\$placeholder.show();
						return;
					}

					if (!variation.owsp_has_valid_due || variation.owsp_split_mode === 'disabled') {
						\$fullRadio.prop('checked', true);
						\$disabled.show();
						return;
					}

					if (variation.owsp_split_mode === 'forced') {
						\$forcedLabel.text(variation.owsp_plan_label || '');
						\$fullRadio.prop('checked', true);
						\$forcedInput.prop('disabled', false);
						\$forced.show();
						return;
					}

					\$splitLabel.text(variation.owsp_plan_label || '');
					\$optionalInputs.prop('disabled', false);
					\$optional.show();
				}

				\$(document.body).on('found_variation', '.variations_form', function(event, variation) {
					var \$box = \$(this).find('.owsp-product-choice[data-owsp-variable-choice=\"yes\"]');
					if (\$box.length) {
						setOWSPState(\$box, variation || null);
					}
				});

				\$(document.body).on('reset_data hide_variation', '.variations_form', function() {
					var \$box = \$(this).find('.owsp-product-choice[data-owsp-variable-choice=\"yes\"]');
					if (\$box.length) {
						setOWSPState(\$box, null);
					}
				});
			})(jQuery);
			"
		);
	}

	/**
	 * Determina si el item de carrito debe calcularse como 50/50.
	 */
	public static function is_item_split( array $cart_item, string $cart_item_key = '' ): bool {
		$target_product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id'];
		$mode              = OWSP_Product_Settings::get_split_mode( $target_product_id );

		if ( 'forced' === $mode ) {
			return true;
		}

		if ( 'optional' === $mode ) {
			if ( function_exists( 'WC' ) && WC() instanceof WooCommerce && isset( WC()->session ) && '' !== $cart_item_key ) {
				$plans = WC()->session->get( 'owsp_item_checkout_plans', array() );
				if ( is_array( $plans ) && isset( $plans[ $cart_item_key ] ) ) {
					$choice = $plans[ $cart_item_key ];
					if ( 'split' === $choice ) {
						return true;
					} elseif ( 'full' === $choice ) {
						return false;
					}
				}
			}
			return ! empty( $cart_item[ self::FLAG_SELECTED ] );
		}

		return false;
	}

	/**
	 * Renderiza un selector junto al producto en la tabla de Checkout.
	 */
	public static function render_checkout_item_selector( string $quantity_html, array $cart_item, string $cart_item_key ): string {
		$target_product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id'];
		$mode              = OWSP_Product_Settings::get_split_mode( $target_product_id );

		if ( 'optional' !== $mode || ! OWSP_Product_Settings::has_valid_due_configuration( $target_product_id ) ) {
			return $quantity_html;
		}

		if ( ! is_checkout() || is_wc_endpoint_url() ) {
			return $quantity_html;
		}

		$is_split = self::is_item_split( $cart_item, $cart_item_key );

		$select  = '<span class="owsp-checkout-inline-select" style="display:block; margin-top:4px;">';
		$select .= '<select name="owsp_item_checkout_plans[' . esc_attr( $cart_item_key ) . ']" class="owsp-checkout-item-plan-select" style="font-size:12px; padding:2px 4px; border-radius:4px; border:1px solid #ccc;">';
		$select .= '<option value="full" ' . selected( false, $is_split, false ) . '>' . esc_html__( 'Pagar TODO (100%)', OWSP_TEXTDOMAIN ) . '</option>';
		$select .= '<option value="split" ' . selected( true, $is_split, false ) . '>' . esc_html__( 'Dividir 50/50', OWSP_TEXTDOMAIN ) . '</option>';
		$select .= '</select></span>';

		static $js_loaded = false;
		if ( ! $js_loaded && function_exists( 'wc_enqueue_js' ) ) {
			wc_enqueue_js( "
				jQuery(document.body).on('change', '.owsp-checkout-item-plan-select', function() {
					jQuery('body').trigger('update_checkout');
				});
			" );
			$js_loaded = true;
		}

		return $quantity_html . $select;
	}

	/**
	 * Actualiza los planes en sesión indexados por clave de ítem.
	 */
	public static function update_checkout_session( $post_data ): void {
		parse_str( $post_data, $data );
		if ( isset( $data['owsp_item_checkout_plans'] ) && is_array( $data['owsp_item_checkout_plans'] ) && WC()->session ) {
			WC()->session->set( 'owsp_item_checkout_plans', $data['owsp_item_checkout_plans'] );
		}
	}
}
