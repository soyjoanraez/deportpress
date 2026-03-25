<?php
/**
 * Configuración de producto.
 *
 * @package OWSP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metacampos del producto y helpers.
 */
class OWSP_Product_Settings {

	private const META_SPLIT_MODE = '_owsp_split_mode';
	private const META_DUE_TYPE   = '_owsp_due_type';
	private const META_DUE_DATE   = '_owsp_due_date';
	private const META_DUE_DAYS   = '_owsp_due_days';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_fields' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Renderiza campos de admin.
	 */
	public static function render_fields(): void {
		global $post;

		$product_id       = $post instanceof WP_Post ? (int) $post->ID : 0;
		$stored_due_date  = $product_id > 0 ? (string) get_post_meta( $product_id, self::META_DUE_DATE, true ) : '';
		$display_due_date = self::format_admin_date( $stored_due_date );

		echo '<div class="options_group">';

		woocommerce_wp_select(
			array(
				'id'          => self::META_SPLIT_MODE,
				'label'       => __( 'Pago 50/50', OWSP_TEXTDOMAIN ),
				'description' => __( 'Define si el producto admite pago en dos plazos del 50%.', OWSP_TEXTDOMAIN ),
				'desc_tip'    => true,
				'options'     => array(
					'disabled' => __( 'Desactivado', OWSP_TEXTDOMAIN ),
					'optional' => __( 'Opcional para el cliente', OWSP_TEXTDOMAIN ),
					'forced'   => __( 'Obligatorio', OWSP_TEXTDOMAIN ),
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => self::META_DUE_TYPE,
				'label'       => __( 'Vencimiento segundo 50%', OWSP_TEXTDOMAIN ),
				'description' => __( 'Puedes fijar una fecha concreta o un número de días tras la compra.', OWSP_TEXTDOMAIN ),
				'desc_tip'    => true,
				'options'     => array(
					'fixed_date' => __( 'Fecha fija', OWSP_TEXTDOMAIN ),
					'days_after' => __( 'Días después de la compra', OWSP_TEXTDOMAIN ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_DUE_DATE,
				'label'       => __( 'Fecha segundo 50%', OWSP_TEXTDOMAIN ),
				'description' => __( 'Selecciona la fecha en el calendario. Formato visible: dd/mm/yyyy.', OWSP_TEXTDOMAIN ),
				'desc_tip'    => true,
				'placeholder' => '01/09/2026',
				'value'       => $display_due_date,
				'class'       => 'short owsp-admin-date-field',
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_DUE_DAYS,
				'label'             => __( 'Días hasta el segundo 50%', OWSP_TEXTDOMAIN ),
				'description'       => __( 'Se usa cuando el vencimiento depende de la fecha de compra.', OWSP_TEXTDOMAIN ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Guarda campos del producto.
	 *
	 * @param int $product_id ID del producto.
	 */
	public static function save_fields( int $product_id ): void {
		$split_mode = isset( $_POST[ self::META_SPLIT_MODE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_SPLIT_MODE ] ) ) : 'disabled';
		$due_type   = isset( $_POST[ self::META_DUE_TYPE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_DUE_TYPE ] ) ) : 'fixed_date';
		$due_date   = isset( $_POST[ self::META_DUE_DATE ] ) ? self::normalize_due_date_input( sanitize_text_field( wp_unslash( $_POST[ self::META_DUE_DATE ] ) ) ) : '';
		$due_days   = isset( $_POST[ self::META_DUE_DAYS ] ) ? absint( wp_unslash( $_POST[ self::META_DUE_DAYS ] ) ) : 0;

		if ( ! in_array( $split_mode, array( 'disabled', 'optional', 'forced' ), true ) ) {
			$split_mode = 'disabled';
		}

		if ( ! in_array( $due_type, array( 'fixed_date', 'days_after' ), true ) ) {
			$due_type = 'fixed_date';
		}

		update_post_meta( $product_id, self::META_SPLIT_MODE, $split_mode );
		update_post_meta( $product_id, self::META_DUE_TYPE, $due_type );
		update_post_meta( $product_id, self::META_DUE_DATE, $due_date );
		update_post_meta( $product_id, self::META_DUE_DAYS, $due_days );
	}

	/**
	 * Carga datepicker en la edición de producto.
	 *
	 * @param string $hook_suffix Pantalla admin.
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-datepicker' );

		if ( ! wp_style_is( 'jquery-ui-style', 'registered' ) && function_exists( 'WC' ) && WC() instanceof WooCommerce ) {
			wp_register_style(
				'jquery-ui-style',
				WC()->plugin_url() . '/assets/css/jquery-ui/jquery-ui.min.css',
				array(),
				OWSP_VERSION
			);
		}

		wp_enqueue_style( 'jquery-ui-style' );

		wp_add_inline_script(
			'jquery-ui-datepicker',
			"(function($){
				$(function(){
					var \$field = $('#" . esc_js( self::META_DUE_DATE ) . "');
					if (!\$field.length || !$.fn.datepicker) {
						return;
					}

					\$field.datepicker({
						dateFormat: 'dd/mm/yy',
						firstDay: 1,
						changeMonth: true,
						changeYear: true,
						showButtonPanel: true
					});
				});
			})(jQuery);"
		);
	}

	/**
	 * Obtiene modo 50/50, con fallback al padre de variación.
	 */
	public static function get_split_mode( int $product_id ): string {
		$value = (string) self::get_meta_with_parent_fallback( $product_id, self::META_SPLIT_MODE );

		return in_array( $value, array( 'disabled', 'optional', 'forced' ), true ) ? $value : 'disabled';
	}

	/**
	 * Devuelve si el producto admite 50/50.
	 */
	public static function supports_split( int $product_id ): bool {
		return 'disabled' !== self::get_split_mode( $product_id );
	}

	/**
	 * Configuración de vencimiento del producto.
	 *
	 * @return array{type:string,date:string,days:int}
	 */
	public static function get_due_configuration( int $product_id ): array {
		$type = (string) self::get_meta_with_parent_fallback( $product_id, self::META_DUE_TYPE );
		$date = (string) self::get_meta_with_parent_fallback( $product_id, self::META_DUE_DATE );
		$days = (int) self::get_meta_with_parent_fallback( $product_id, self::META_DUE_DAYS );

		if ( ! in_array( $type, array( 'fixed_date', 'days_after' ), true ) ) {
			$type = 'fixed_date';
		}

		return array(
			'type' => $type,
			'date' => $date,
			'days' => max( 0, $days ),
		);
	}

	/**
	 * Valida si la configuración del segundo pago es utilizable.
	 */
	public static function has_valid_due_configuration( int $product_id ): bool {
		$config = self::get_due_configuration( $product_id );

		if ( 'days_after' === $config['type'] ) {
			return $config['days'] > 0;
		}

		return '' !== $config['date'];
	}

	/**
	 * Resuelve la fecha final de vencimiento.
	 */
	public static function resolve_due_date( array $config, ?int $reference_timestamp = null ): string {
		$reference_timestamp = $reference_timestamp ?: time();

		if ( 'days_after' === $config['type'] ) {
			$tz   = wp_timezone();
			$base = new DateTimeImmutable( '@' . $reference_timestamp );
			$base = $base->setTimezone( $tz )->setTime( 0, 0, 0 );
			return $base->modify( '+' . max( 1, (int) $config['days'] ) . ' days' )->format( 'Y-m-d' );
		}

		return (string) $config['date'];
	}

	/**
	 * Etiqueta comercial del plan.
	 */
	public static function get_plan_label( int $product_id, ?int $reference_timestamp = null ): string {
		$config   = self::get_due_configuration( $product_id );
		$due_date = self::resolve_due_date( $config, $reference_timestamp );

		if ( '' === $due_date ) {
			return __( '50% ahora y 50% después', OWSP_TEXTDOMAIN );
		}

		return sprintf(
			/* translators: %s: due date */
			__( '50%% ahora y 50%% el %s', OWSP_TEXTDOMAIN ),
			wp_date( get_option( 'date_format' ), strtotime( $due_date ), wp_timezone() )
		);
	}

	/**
	 * Fallback de meta para variaciones.
	 *
	 * @param int    $product_id ID producto o variación.
	 * @param string $key Clave de meta.
	 * @return mixed
	 */
	private static function get_meta_with_parent_fallback( int $product_id, string $key ) {
		$value = get_post_meta( $product_id, $key, true );
		if ( '' !== (string) $value && null !== $value ) {
			return $value;
		}

		$product = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variation' ) ) {
			return get_post_meta( $product->get_parent_id(), $key, true );
		}

		return $value;
	}

	/**
	 * Convierte una fecha guardada a formato admin dd/mm/yyyy.
	 */
	private static function format_admin_date( string $date ): string {
		if ( '' === $date ) {
			return '';
		}

		$normalized = self::normalize_due_date_input( $date );
		if ( '' === $normalized ) {
			return '';
		}

		$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d', $normalized, wp_timezone() );

		return $datetime instanceof DateTimeImmutable ? $datetime->format( 'd/m/Y' ) : '';
	}

	/**
	 * Normaliza la fecha de admin a Y-m-d.
	 */
	private static function normalize_due_date_input( string $date ): string {
		$date = trim( $date );

		if ( '' === $date ) {
			return '';
		}

		foreach ( array( '!d/m/Y', '!Y-m-d' ) as $format ) {
			$datetime = DateTimeImmutable::createFromFormat( $format, $date, wp_timezone() );
			$errors   = DateTimeImmutable::getLastErrors();
			$errors   = false === $errors
				? array(
					'warning_count' => 0,
					'error_count'   => 0,
				)
				: $errors;

			if ( $datetime instanceof DateTimeImmutable && 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] ) {
				return $datetime->format( 'Y-m-d' );
			}
		}

		return '';
	}
}
