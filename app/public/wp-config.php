<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'root' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '4LDHjMWPeNSjbF4n8koeFmjJJrYQUpQR7rwVix/U1XoNrzlpWEy1+13MRC2UBCn9Evptt7TEkleIwhvep3w34g==');
define('SECURE_AUTH_KEY',  'Ja6fz0SJYTnbue5TOOZIf8NTX4KItMDJ/0sOmF3gHaRGxddpWw+mXjG2al0VJ40llynfkzjqp7B0xTSIQE53YQ==');
define('LOGGED_IN_KEY',    '7O8naoW9zxuZnswODGgxk9VBW1qlBfwLEb4kNOSMB4FBVLJEMb8MjpyTX0SQr/gG4uVEdj92CwhSL4bwkmsEvQ==');
define('NONCE_KEY',        'YdTJkKpw6UP0bzurw3pMqqDMh12jfnL3Qhnx225ashL947WlBdDQ+OMS0hqG/Gx4V7IVboF0YKvSJ60ZfLMElQ==');
define('AUTH_SALT',        '1slIFwDOn9jtxCisdZmYdOJN4WA6Ic1hzP/sol0ons7YSELfaUNBuDOJ1qNwKrdjjKQwdVhPlf2eMNVp8QevTQ==');
define('SECURE_AUTH_SALT', '+okpz5wK0ciNVHEUZx7gDQMgClWe9gej81ohA/Yi1HYy9GNp3+RhYcYLOZOz3NazBEHlybt0ioJNG8Xg85UrHQ==');
define('LOGGED_IN_SALT',   'vVqGdggrMYTwyguuczskRWLUoVw0pkZOHLEr4M5aG2yUte99A5MX0PQzrP4d3GyOUPSq+elrSC2+Ypa04sR4HQ==');
define('NONCE_SALT',       'uFm3sY7/T0nmkqfoaAsurPLY/zJ8bayQqUGx7AnKozXIvoWYUFYvpIxxS5+FTFHTQkOJ+1pcIM6vKfKt81JY+g==');

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';




/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
