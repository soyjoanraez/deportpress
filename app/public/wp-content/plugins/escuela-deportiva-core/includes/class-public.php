<?php
/**
 * Front-end: shortcodes panel familiar, MVP, rankings i assets.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes públics.
 */
class ED_Public {

	/**
	 * @var bool
	 */
	private static $calendario_widget_assets = false;

	/**
	 * @var bool
	 */
	private static $enviar_mensaje_assets = false;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_shortcode( 'ed_panel_familiar', array( $this, 'shortcode_panel_familiar' ) );
		add_shortcode( 'ed_panel_entrenador', array( $this, 'shortcode_panel_entrenador' ) );
		add_shortcode( 'ed_votar_mvp', array( $this, 'shortcode_votar_mvp' ) );
		add_shortcode( 'ed_rankings', array( $this, 'shortcode_rankings' ) );
		add_shortcode( 'ed_escaner_qr', array( $this, 'shortcode_escaner_qr' ) );
		add_shortcode( 'ed_panel_bar', array( $this, 'shortcode_panel_bar' ) );
		add_shortcode( 'ed_pedir_bar', array( $this, 'shortcode_pedir_bar' ) );
		add_shortcode( 'ed_calendario', array( $this, 'shortcode_calendario' ) );
		add_shortcode( 'ed_enviar_mensaje', array( $this, 'shortcode_enviar_mensaje' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_panel_assets' ), 20 );
	}

	/**
	 * Carrega CSS/JS només a la pàgina del shortcode o slugs coneguts.
	 */
	public function maybe_enqueue_panel_assets(): void {
		if ( $this->should_load_familiar_assets() ) {
			$this->enqueue_familiar_assets();
		}
		if ( $this->should_load_entrenador_assets() ) {
			$this->enqueue_entrenador_assets();
		}
		if ( $this->should_load_mvp_assets() ) {
			$this->enqueue_mvp_assets();
		}
		if ( $this->should_load_rankings_assets() ) {
			$this->enqueue_rankings_assets();
		}
		if ( $this->should_load_escaner_assets() ) {
			$this->enqueue_escaner_assets();
		}
		if ( $this->should_load_panel_bar_assets() ) {
			$this->enqueue_panel_bar_assets();
		}
		if ( $this->should_load_calendario_widget_assets() ) {
			$this->enqueue_calendario_widget_assets();
		}
		if ( $this->should_load_enviar_mensaje_assets() ) {
			$this->enqueue_enviar_mensaje_assets();
		}
	}

	/**
	 * Shortcode output panel familiar.
	 */
	/**
	 * Calendari unificat: [ed_calendario jugador_id="0" categoria_id="0"].
	 *
	 * @param array<string, string> $atts Atributs del shortcode.
	 */
	/**
	 * Formulari d’enviament de missatges a famílies (staff).
	 */
	public function shortcode_enviar_mensaje(): string {
		if ( ! is_user_logged_in() ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Has d’iniciar sessió.', 'escuela-deportiva-core' ) . '</p>';
		}
		if ( ! $this->user_can_staff_comunicaciones() ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Accés restringit.', 'escuela-deportiva-core' ) . '</p>';
		}
		$this->enqueue_enviar_mensaje_assets();
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/enviar-mensaje.php';
		return (string) ob_get_clean();
	}

	public function shortcode_calendario( array $atts ): string {
		$this->enqueue_calendario_widget_assets();
		$atts = shortcode_atts(
			array(
				'jugador_id'   => '0',
				'categoria_id' => '0',
			),
			$atts,
			'ed_calendario'
		);
		$jid = (int) $atts['jugador_id'];
		$cid = (int) $atts['categoria_id'];
		return sprintf(
			'<div id="ed-calendario" class="ed-cal-widget" data-jugador-id="%s" data-categoria-id="%s"></div>',
			$jid > 0 ? esc_attr( (string) $jid ) : '',
			$cid > 0 ? esc_attr( (string) $cid ) : ''
		);
	}

	public function shortcode_panel_familiar(): string {
		$this->enqueue_familiar_assets();
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/panel-familiar.php';
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode output panel entrenador.
	 */
	public function shortcode_panel_entrenador(): string {
		$this->enqueue_entrenador_assets();
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/panel-entrenador.php';
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode votació MVP: [ed_votar_mvp partido_id="123"] o ?partido= a la pàgina votar-mvp.
	 *
	 * @param array<string, string> $atts Atributs del shortcode.
	 */
	public function shortcode_votar_mvp( array $atts ): string {
		$this->enqueue_mvp_assets();
		$atts = shortcode_atts(
			array(
				'partido_id' => '0',
			),
			$atts,
			'ed_votar_mvp'
		);
		$pid = (int) $atts['partido_id'];
		if ( ! $pid && isset( $_GET['partido'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pid = (int) wp_unslash( $_GET['partido'] );
		}
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/votar-mvp-widget.php';
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode rankings: [ed_rankings torneo_id="0"] (0 = globals per tipus goles).
	 *
	 * @param array<string, string> $atts Atributs del shortcode.
	 */
	public function shortcode_rankings( array $atts ): string {
		$this->enqueue_rankings_assets();
		$atts = shortcode_atts(
			array(
				'torneo_id' => '0',
				'tipo'      => 'goles',
				'limit'     => '15',
			),
			$atts,
			'ed_rankings'
		);
		$torneo_id = (int) $atts['torneo_id'];
		$tipo      = sanitize_key( (string) $atts['tipo'] );
		$limit     = (int) $atts['limit'];
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/rankings-widget.php';
		return (string) ob_get_clean();
	}

	/**
	 * Assets panel familiar.
	 */
	private function enqueue_familiar_assets(): void {
		$this->enqueue_calendario_widget_assets();
		wp_enqueue_style(
			'ed-fase6',
			ED_PLUGIN_URL . 'assets/css/fase6.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_style(
			'ed-panel-familiar',
			ED_PLUGIN_URL . 'assets/css/panel-familiar.css',
			array( 'ed-fase6' ),
			ED_VERSION
		);
		wp_enqueue_style(
			'ed-comunicaciones',
			ED_PLUGIN_URL . 'assets/css/comunicaciones.css',
			array( 'ed-panel-familiar' ),
			ED_VERSION
		);

		wp_enqueue_script(
			'ed-panel-familiar',
			ED_PLUGIN_URL . 'assets/js/panel-familiar.js',
			array( 'ed-calendario-widget' ),
			ED_VERSION,
			true
		);

		$tags = array();
		if ( is_user_logged_in() && class_exists( 'ED_Nucleo_Repository' ) ) {
			$n = ED_Nucleo_Repository::find_for_user( (int) get_current_user_id() );
			if ( $n instanceof WP_Post ) {
				$tags['es_familia']     = '1';
				$tags[ 'nucleo_' . $n->ID ] = '1';
				foreach ( ED_Nucleo_Repository::get_jugador_ids_for_nucleo( $n->ID ) as $jid ) {
					if ( function_exists( 'get_field' ) ) {
						$eid = (int) get_field( 'ed_jugador_equipo', $jid, false );
						if ( $eid ) {
							$tags[ 'equipo_' . $eid ] = '1';
						}
						$cid = (int) get_field( 'ed_jugador_categoria', $jid, false );
						if ( $cid ) {
							$tags[ 'categoria_' . $cid ] = '1';
						}
						$did = (int) get_field( 'ed_jugador_deporte', $jid, false );
						if ( $did ) {
							$tags[ 'deporte_' . $did ] = '1';
						}
					}
				}
			}
		}

		wp_localize_script(
			'ed-panel-familiar',
			'edPanel',
			array(
				'restBase'        => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'loginUrl'        => wp_login_url( get_permalink() ),
				'oneSignalTags'   => $tags,
				'i18nError'       => __( 'No s’han pogut carregar les dades. Torna a provar.', 'escuela-deportiva-core' ),
				'i18nEmpty'       => __( 'No hi ha jugadors vinculats al teu nucli.', 'escuela-deportiva-core' ),
				'i18nDetail'      => __( 'Veure fitxa', 'escuela-deportiva-core' ),
				'i18nBirth'       => __( 'Data de naixement', 'escuela-deportiva-core' ),
				'i18nPosition'    => __( 'Posició', 'escuela-deportiva-core' ),
				'i18nDorsal'      => __( 'Dorsal', 'escuela-deportiva-core' ),
				'i18nTeam'        => __( 'Equip', 'escuela-deportiva-core' ),
				'i18nState'       => __( 'Estat', 'escuela-deportiva-core' ),
				'i18nPagosEmpty'  => __( 'No hi ha terminis de pagament per al teu nucli.', 'escuela-deportiva-core' ),
				'i18nFactura'     => __( 'Veure comanda / factura', 'escuela-deportiva-core' ),
				'i18nPlazo'       => __( 'Termini', 'escuela-deportiva-core' ),
				'i18nPagadoEl'    => __( 'Pagat el', 'escuela-deportiva-core' ),
				'i18nPrevistoEl'  => __( 'Previst per al', 'escuela-deportiva-core' ),
				'i18nStatEntreno' => __( 'Assistència entrenaments', 'escuela-deportiva-core' ),
				'i18nStatPartido' => __( 'Assistència partits', 'escuela-deportiva-core' ),
				'i18nEntradas'    => __( 'Entrades', 'escuela-deportiva-core' ),
				'i18nBarPedidos'  => __( 'Comandes bar', 'escuela-deportiva-core' ),
				'i18nEntradasEmpty' => __( 'No tens entrades registrades.', 'escuela-deportiva-core' ),
				'i18nBarEmpty'    => __( 'No tens comandes de bar.', 'escuela-deportiva-core' ),
				'i18nMsgEmpty'    => __( 'No tens missatges.', 'escuela-deportiva-core' ),
				'i18nMsgNew'      => __( 'Nou', 'escuela-deportiva-core' ),
				'i18nMsgView'     => __( 'Veure missatge', 'escuela-deportiva-core' ),
				'i18nMsgHide'     => __( 'Amagar', 'escuela-deportiva-core' ),
			)
		);
	}

	/**
	 * Assets panel entrenador.
	 */
	private function enqueue_entrenador_assets(): void {
		wp_enqueue_style(
			'ed-panel-entrenador',
			ED_PLUGIN_URL . 'assets/css/panel-entrenador.css',
			array(),
			ED_VERSION
		);

		wp_enqueue_script(
			'ed-panel-entrenador',
			ED_PLUGIN_URL . 'assets/js/panel-entrenador.js',
			array(),
			ED_VERSION,
			true
		);

		wp_localize_script(
			'ed-panel-entrenador',
			'edCoachPanel',
			array(
				'restBase'               => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'loginUrl'               => wp_login_url( get_permalink() ),
				'today'                  => wp_date( 'Y-m-d' ),
				'i18nError'              => __( 'No s’han pogut carregar les dades. Torna a provar.', 'escuela-deportiva-core' ),
				'i18nLoadingCalendar'    => __( 'Carregant calendari…', 'escuela-deportiva-core' ),
				'i18nLoadingSession'     => __( 'Carregant convocatòria…', 'escuela-deportiva-core' ),
				'i18nNoEvents'           => __( 'No hi ha esdeveniments per als filtres seleccionats.', 'escuela-deportiva-core' ),
				'i18nPickSession'        => __( 'Selecciona una sessió.', 'escuela-deportiva-core' ),
				'i18nNoPlayers'          => __( 'No s’han trobat jugadors per a aquesta sessió.', 'escuela-deportiva-core' ),
				'i18nSaving'             => __( 'Desant…', 'escuela-deportiva-core' ),
				'i18nSaved'              => __( 'Assistència desada.', 'escuela-deportiva-core' ),
				'i18nTraining'           => __( 'Entrenament', 'escuela-deportiva-core' ),
				'i18nMatch'              => __( 'Partit', 'escuela-deportiva-core' ),
				'i18nAttendanceClosed'   => __( 'Aquesta assistència ja està tancada.', 'escuela-deportiva-core' ),
				'i18nAttendanceLocked'   => __( 'Aquest esdeveniment no permet gestionar assistència des del panel.', 'escuela-deportiva-core' ),
				'i18nCategoryAll'        => __( 'Totes', 'escuela-deportiva-core' ),
				'i18nCategory'           => __( 'Categoria', 'escuela-deportiva-core' ),
				'i18nLocation'           => __( 'Lloc', 'escuela-deportiva-core' ),
				'i18nStatus'             => __( 'Estat', 'escuela-deportiva-core' ),
				'i18nScore'              => __( 'Marcador', 'escuela-deportiva-core' ),
				'i18nTeamContext'        => __( 'Equip / Context', 'escuela-deportiva-core' ),
				'i18nNoContext'          => __( 'Sense equip', 'escuela-deportiva-core' ),
				'i18nNoDate'             => __( 'Sense data', 'escuela-deportiva-core' ),
				'i18nNoLocation'         => __( 'Sense lloc', 'escuela-deportiva-core' ),
				'i18nSelectEvent'        => __( 'Selecciona un esdeveniment per gestionar l’assistència.', 'escuela-deportiva-core' ),
				'i18nWeekLabel'          => __( 'Setmana del %1$s al %2$s', 'escuela-deportiva-core' ),
				'i18nPlayerFallback'     => __( 'Jugador #%d', 'escuela-deportiva-core' ),
				'i18nDorsal'             => __( 'Dorsal %d', 'escuela-deportiva-core' ),
				'optAsistio'             => __( 'Ha assistit', 'escuela-deportiva-core' ),
				'optNoAsistio'           => __( 'No ha assistit', 'escuela-deportiva-core' ),
				'optConvocado'           => __( 'Convocat', 'escuela-deportiva-core' ),
				'optNoConvocado'         => __( 'No convocat', 'escuela-deportiva-core' ),
				'optJustificada'         => __( 'Absència justificada', 'escuela-deportiva-core' ),
				'optTarde'               => __( 'Arribada tard', 'escuela-deportiva-core' ),
			)
		);
	}

	private function enqueue_mvp_assets(): void {
		wp_enqueue_style(
			'ed-mvp-widget',
			ED_PLUGIN_URL . 'assets/css/mvp-widget.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_script(
			'ed-mvp-widget',
			ED_PLUGIN_URL . 'assets/js/mvp-widget.js',
			array(),
			ED_VERSION,
			true
		);
		wp_localize_script(
			'ed-mvp-widget',
			'edMvp',
			array(
				'restBase'      => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'i18nError'     => __( 'No s’ha pogut carregar la votació.', 'escuela-deportiva-core' ),
				'i18nNoPartido' => __( 'Indica un partit vàlid (atribut partido_id o ?partido=).', 'escuela-deportiva-core' ),
				'i18nClosed'    => __( 'La votació està tancada.', 'escuela-deportiva-core' ),
				'i18nVoted'     => __( 'Gràcies pel teu vot.', 'escuela-deportiva-core' ),
				'i18nVoteErr'   => __( 'No s’ha pogut registrar el vot.', 'escuela-deportiva-core' ),
				'i18nNoCand'    => __( 'Encara no hi ha candidats (jugadors dels equips del partit).', 'escuela-deportiva-core' ),
			)
		);
	}

	private function enqueue_rankings_assets(): void {
		wp_enqueue_style(
			'ed-rankings-widget',
			ED_PLUGIN_URL . 'assets/css/rankings-widget.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_script(
			'ed-rankings-widget',
			ED_PLUGIN_URL . 'assets/js/rankings-widget.js',
			array(),
			ED_VERSION,
			true
		);
		wp_localize_script(
			'ed-rankings-widget',
			'edRankings',
			array(
				'restBase'  => esc_url_raw( rest_url( 'ed/v1' ) ),
				'i18nError' => __( 'No s’han pogut carregar els rankings.', 'escuela-deportiva-core' ),
				'i18nEmpty' => __( 'Sense dades encara.', 'escuela-deportiva-core' ),
			)
		);
	}

	private function enqueue_calendario_widget_assets(): void {
		if ( self::$calendario_widget_assets ) {
			return;
		}
		self::$calendario_widget_assets = true;

		wp_enqueue_style(
			'ed-calendario-fase8',
			ED_PLUGIN_URL . 'assets/css/calendario-fase8.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_script(
			'ed-calendario-widget',
			ED_PLUGIN_URL . 'assets/js/calendario-widget.js',
			array(),
			ED_VERSION,
			true
		);
		wp_localize_script(
			'ed-calendario-widget',
			'edCalendario',
			array(
				'restBase'     => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'i18nError'    => __( 'No s’ha pogut carregar el calendari.', 'escuela-deportiva-core' ),
				'i18nLoading'  => __( 'Carregant…', 'escuela-deportiva-core' ),
				'i18nLegEnt'   => __( 'Entrenament', 'escuela-deportiva-core' ),
				'i18nLegTor'   => __( 'Torneig', 'escuela-deportiva-core' ),
				'i18nLegLiga'  => __( 'Lliga', 'escuela-deportiva-core' ),
			)
		);
	}

	private function should_load_calendario_widget_assets(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ed_calendario' );
	}

	private function enqueue_enviar_mensaje_assets(): void {
		if ( self::$enviar_mensaje_assets ) {
			return;
		}
		self::$enviar_mensaje_assets = true;

		wp_enqueue_style(
			'ed-fase6',
			ED_PLUGIN_URL . 'assets/css/fase6.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_style(
			'ed-comunicaciones-msg',
			ED_PLUGIN_URL . 'assets/css/comunicaciones.css',
			array( 'ed-fase6' ),
			ED_VERSION
		);
		wp_enqueue_script(
			'ed-enviar-mensaje',
			ED_PLUGIN_URL . 'assets/js/enviar-mensaje.js',
			array(),
			ED_VERSION,
			true
		);
		$coach_only = is_user_logged_in()
			&& current_user_can( 'edit_entrenamientos' )
			&& ! current_user_can( 'manage_escuela_deportiva' );

		wp_localize_script(
			'ed-enviar-mensaje',
			'edMsgForm',
			array(
				'restBase'      => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'coachOnly'     => $coach_only,
				'loading'       => __( 'Carregant…', 'escuela-deportiva-core' ),
				'loadError'     => __( 'No s’han pogut carregar les opcions.', 'escuela-deportiva-core' ),
				'labelDeporte'  => __( 'Selecciona esport…', 'escuela-deportiva-core' ),
				'labelCategoria'=> __( 'Selecciona categoria…', 'escuela-deportiva-core' ),
				'labelNucleo'   => __( 'Selecciona família…', 'escuela-deportiva-core' ),
				'labelJugador'  => __( 'Selecciona jugador…', 'escuela-deportiva-core' ),
				'alertIncomplete'=> __( 'Completa l’assumpte i el cos del missatge.', 'escuela-deportiva-core' ),
				'alertDestino'  => __( 'Selecciona el destinatari.', 'escuela-deportiva-core' ),
				'sending'       => __( 'Enviant…', 'escuela-deportiva-core' ),
				'btnSend'       => __( '📨 Enviar missatge', 'escuela-deportiva-core' ),
				'okMsg'         => __( 'Missatge enviat correctament.', 'escuela-deportiva-core' ),
				'errMsg'        => __( 'No s’ha pogut enviar el missatge.', 'escuela-deportiva-core' ),
				'histEmpty'     => __( 'Encara no has enviat missatges.', 'escuela-deportiva-core' ),
				'histError'     => __( 'No s’ha pogut carregar l’historial.', 'escuela-deportiva-core' ),
				'readLabel'     => __( 'llegits', 'escuela-deportiva-core' ),
			)
		);
	}

	private function should_load_enviar_mensaje_assets(): bool {
		if ( is_page( 'enviar-mensaje' ) ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ed_enviar_mensaje' );
	}

	/**
	 * Coordinador, entrenador, manage_escuela_deportiva o administrador.
	 */
	private function user_can_staff_comunicaciones(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return current_user_can( 'manage_options' )
			|| current_user_can( 'manage_escuela_deportiva' )
			|| current_user_can( 'edit_entrenamientos' );
	}

	private function should_load_familiar_assets(): bool {
		if ( is_page( 'panel-familiar' ) ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ed_panel_familiar' );
	}

	private function should_load_entrenador_assets(): bool {
		if ( is_page( 'panel-entrenador' ) ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ed_panel_entrenador' );
	}

	private function should_load_mvp_assets(): bool {
		if ( is_page( 'votar-mvp' ) ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ed_votar_mvp' );
	}

	private function should_load_rankings_assets(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ed_rankings' );
	}

	/**
	 * @param array<string, string> $atts Atributs del shortcode.
	 */
	public function shortcode_escaner_qr( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Has d’iniciar sessió.', 'escuela-deportiva-core' ) . '</p>';
		}
		$ok = current_user_can( 'manage_options' )
			|| current_user_can( 'manage_escuela_deportiva' )
			|| current_user_can( 'edit_partidos' );
		if ( ! $ok ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Accés restringit.', 'escuela-deportiva-core' ) . '</p>';
		}
		$atts      = shortcode_atts( array( 'torneo_id' => '0' ), $atts, 'ed_escaner_qr' );
		$torneo_id = (int) $atts['torneo_id'];
		if ( ! $torneo_id && isset( $_GET['torneo_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$torneo_id = (int) wp_unslash( $_GET['torneo_id'] );
		}
		$GLOBALS['ed_escaner_torneo_id'] = $torneo_id;
		$this->enqueue_escaner_assets();
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/escaner-qr.php';
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string> $atts Atributs del shortcode.
	 */
	public function shortcode_panel_bar( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Has d’iniciar sessió.', 'escuela-deportiva-core' ) . '</p>';
		}
		$ok = current_user_can( 'manage_options' )
			|| current_user_can( 'manage_escuela_deportiva' );
		if ( ! $ok ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Accés restringit.', 'escuela-deportiva-core' ) . '</p>';
		}
		$atts      = shortcode_atts( array( 'torneo_id' => '0' ), $atts, 'ed_panel_bar' );
		$torneo_id = (int) $atts['torneo_id'];
		if ( ! $torneo_id && isset( $_GET['torneo_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$torneo_id = (int) wp_unslash( $_GET['torneo_id'] );
		}
		$GLOBALS['ed_panel_bar_torneo_id'] = $torneo_id;
		$this->enqueue_panel_bar_assets();
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/panel-bar.php';
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string> $atts Atributs del shortcode.
	 */
	public function shortcode_pedir_bar( array $atts ): string {
		$atts = shortcode_atts( array( 'torneo_id' => '0' ), $atts, 'ed_pedir_bar' );
		$tid  = (int) $atts['torneo_id'];
		if ( $tid <= 0 ) {
			return '<p class="ed-shortcode-msg">' . esc_html__( 'Indica torneo_id al shortcode.', 'escuela-deportiva-core' ) . '</p>';
		}
		$GLOBALS['ed_bar_torneo_id_from_shortcode'] = $tid;
		$this->enqueue_bar_cliente_assets();
		$ed_bar_torneo_id = $tid;
		ob_start();
		include ED_PLUGIN_DIR . 'public/views/bar-cliente.php';
		return (string) ob_get_clean();
	}

	private function enqueue_escaner_assets(): void {
		wp_enqueue_style(
			'ed-fase6',
			ED_PLUGIN_URL . 'assets/css/fase6.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_script(
			'jsqr',
			'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js',
			array(),
			'1.4.0',
			true
		);
		wp_enqueue_script(
			'ed-escaner-qr',
			ED_PLUGIN_URL . 'assets/js/escaner-qr.js',
			array( 'jsqr' ),
			ED_VERSION,
			true
		);
		$tid = isset( $GLOBALS['ed_escaner_torneo_id'] ) ? (int) $GLOBALS['ed_escaner_torneo_id'] : 0;
		wp_localize_script(
			'ed-escaner-qr',
			'edEscaner',
			array(
				'restBase'      => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'torneoId'      => $tid,
				'i18nNoCam'     => __( 'Càmera no disponible. Usa el camp manual.', 'escuela-deportiva-core' ),
				'i18nRifa'      => __( 'Números rifa:', 'escuela-deportiva-core' ),
				'i18nAlready'   => __( 'Entrada ja utilitzada', 'escuela-deportiva-core' ),
				'i18nNotFound'  => __( 'Entrada no trobada', 'escuela-deportiva-core' ),
				'i18nCancelled' => __( 'Entrada cancel·lada', 'escuela-deportiva-core' ),
				'i18nUsedAt'    => __( 'Utilitzada el', 'escuela-deportiva-core' ),
			)
		);
	}

	private function enqueue_panel_bar_assets(): void {
		wp_enqueue_style(
			'ed-fase6',
			ED_PLUGIN_URL . 'assets/css/fase6.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_script(
			'ed-panel-bar',
			ED_PLUGIN_URL . 'assets/js/panel-bar.js',
			array(),
			ED_VERSION,
			true
		);
		$tid = isset( $GLOBALS['ed_panel_bar_torneo_id'] ) ? (int) $GLOBALS['ed_panel_bar_torneo_id'] : 0;
		wp_localize_script(
			'ed-panel-bar',
			'edBarPanel',
			array(
				'restBase'   => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'torneoId'   => $tid,
				'i18nTodas'  => __( 'Totes', 'escuela-deportiva-core' ),
				'i18nEmpty'  => __( 'Sense comandes.', 'escuela-deportiva-core' ),
				'i18nError'  => __( 'No s’han pogut carregar les comandes.', 'escuela-deportiva-core' ),
				'i18nNoTorneo' => __( 'Indica torneo_id a la pàgina (?torneo_id=) o al shortcode.', 'escuela-deportiva-core' ),
				'i18nAvanzar' => __( 'Avançar', 'escuela-deportiva-core' ),
				'labels'     => array(
					'pendiente'  => __( 'Pendent', 'escuela-deportiva-core' ),
					'preparando' => __( 'Preparant', 'escuela-deportiva-core' ),
					'listo'      => __( 'Llest', 'escuela-deportiva-core' ),
					'entregado'  => __( 'Lliurat', 'escuela-deportiva-core' ),
				),
				'nextEstado' => array(
					'pendiente'  => 'preparando',
					'preparando' => 'listo',
					'listo'      => 'entregado',
				),
			)
		);
	}

	private function enqueue_bar_cliente_assets(): void {
		wp_enqueue_style(
			'ed-fase6',
			ED_PLUGIN_URL . 'assets/css/fase6.css',
			array(),
			ED_VERSION
		);
		wp_enqueue_script(
			'ed-bar-cliente',
			ED_PLUGIN_URL . 'assets/js/bar-cliente.js',
			array(),
			ED_VERSION,
			true
		);
		$tid = 0;
		if ( isset( $GLOBALS['ed_bar_torneo_id_from_shortcode'] ) ) {
			$tid = (int) $GLOBALS['ed_bar_torneo_id_from_shortcode'];
		}
		wp_localize_script(
			'ed-bar-cliente',
			'edBarCliente',
			array(
				'restBase'        => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'torneoId'        => $tid,
				'i18nNoProductos' => __( 'No hi ha productes disponibles.', 'escuela-deportiva-core' ),
				'i18nCantidad'    => __( 'Quantitat', 'escuela-deportiva-core' ),
				'i18nNoTorneo'    => __( 'Falta l’ID del torneig.', 'escuela-deportiva-core' ),
				'i18nVacio'       => __( 'Afegeix almenys un producte.', 'escuela-deportiva-core' ),
				'i18nOk'          => __( 'Comanda registrada', 'escuela-deportiva-core' ),
				'i18nError'       => __( 'No s’ha pogut enviar la comanda.', 'escuela-deportiva-core' ),
			)
		);
	}

	private function should_load_escaner_assets(): bool {
		if ( is_page( 'escaner-qr' ) ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		return $post && ! empty( $post->post_content ) && has_shortcode( $post->post_content, 'ed_escaner_qr' );
	}

	private function should_load_panel_bar_assets(): bool {
		if ( is_page( 'panel-bar' ) ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		return $post && ! empty( $post->post_content ) && has_shortcode( $post->post_content, 'ed_panel_bar' );
	}

}
