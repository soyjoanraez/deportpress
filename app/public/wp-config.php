<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'LykaL>&<uJRR4;O24L/aMyz|YL]0D;g%_1QNOir03@ld*WU>S)2UaZ-n .BV&odp' );
define( 'SECURE_AUTH_KEY',   'U/A=XvyY-K7%q5 :T||7dbHv!4B8&{G-KV*w*@cfL]fZ`l;ggf^;cD8&}sNY<i`=' );
define( 'LOGGED_IN_KEY',     'XGm@+RsUZJz35J;/n%pqs>8l{Imr//blYgqm21/[dsUy@Q.WJFR6Qnz+r]/ye-dv' );
define( 'NONCE_KEY',         'I?Dd;[tmI&-U$#rfRB3,<aKP$IYOVEt5[a)cA-os<4v%@:QYGO/DWq=*@@uvk+Q-' );
define( 'AUTH_SALT',         'T14`VG}8*0(Lbp c5bT%MT,3`j*1A6pX:n F[vvxruAd$ }B+%6;,V]_QvtB/%q#' );
define( 'SECURE_AUTH_SALT',  'Rg.7z0Nxy;<bH;:^m@(=a+,2O{;?FH_q$Bn<Rod*v@kXDl0M?=T]?gq2 Q,D>#Ur' );
define( 'LOGGED_IN_SALT',    ' TiE>|OM=sSs6Lhay=l4=T yGK$!yhPxT>wjO`J>+8cM*1#[m9g8`M!>3;o0`Qvq' );
define( 'NONCE_SALT',        '!Sps5kLc7B=E}7/ZQ72ZB>rN/n} JwPg~[*by4:W#;Mygtngq1t8O0`>.Y5I(0od' );
define( 'WP_CACHE_KEY_SALT', 'W{=KzwP=Elt08zkUJvHm{)]#;~*8_Y&11!4*def!&/ofv-$$Yv#kA()(YCC:B)@.' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */

if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
	define( 'WP_MEMORY_LIMIT', '256M' );
}
if ( ! defined( 'WP_MAX_MEMORY_LIMIT' ) ) {
	define( 'WP_MAX_MEMORY_LIMIT', '512M' );
}
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
	define( 'WP_POST_REVISIONS', 5 );
}
if ( ! defined( 'EMPTY_TRASH_DAYS' ) ) {
	define( 'EMPTY_TRASH_DAYS', 14 );
}
// En producción: define( 'DISALLOW_FILE_EDIT', true );
/*
 * APIs opcionales (Escuela Deportiva / DeportPress). Descomenta y rellena cuando toque.
 * Pagos Fase 2 (Stripe / Redsys) — ver también wp-content/plugins/escuela-deportiva-core/DEPORTPRESS-SETUP.md
 * define( 'ED_STRIPE_SECRET_KEY', 'sk_live_...' );
 * define( 'ED_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
 * define( 'ED_REDSYS_SECRET_KEY', '...' );
 * define( 'ED_REDSYS_MERCHANT_CODE', '...' );
 * define( 'ED_REDSYS_TERMINAL', '001' );
 * define( 'ED_OPENAI_API_KEY', 'sk-...' );
 * Fase 4 — OneSignal (push + SDK al front): sense aquests valors no s’envien notificacions ni s’encua el SDK.
 * define( 'ED_ONESIGNAL_APP_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' ); // App ID del dashboard OneSignal
 * define( 'ED_ONESIGNAL_API_KEY', 'os_v2_app_...' ); // REST API Key (notificacions des del servidor WordPress)
 */

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
