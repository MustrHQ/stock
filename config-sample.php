<?php
/**
 * MustrHQ Stock — settings.
 *
 * You should not need to touch this file: the installer writes it for you.
 * If you would rather set it up by hand, copy this file to config.php and
 * fill in the four database lines.
 */

/** Database name */
define('DB_NAME', 'database_name_here');
/** Database user */
define('DB_USER', 'username_here');
/** Database password */
define('DB_PASS', 'password_here');
/** Database host — 'localhost' on nearly all cPanel hosting */
define('DB_HOST', 'localhost');

/** What the app calls itself in the browser tab and on the sign-in screen */
define('APP_NAME', 'MustrHQ Stock');
/** Times and dates are shown in this zone */
define('APP_TZ', 'Europe/London');
/** Currency symbol used in every money figure */
define('APP_CCY', '£');

/**
 * Where people can get the source code of the version you run. The AGPL-3.0 licence asks anyone
 * running a modified copy for others to offer this. Point it at your fork if you change the code.
 */
define('APP_SOURCE_URL', 'https://github.com/MustrHQ/stock');
