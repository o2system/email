<?php
/**
 * This file is part of the O2System PHP Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author         Steeve Andrian Salim
 * @copyright      Copyright (c) Steeve Andrian Salim
 */
// ------------------------------------------------------------------------

// Load Email Helper Manually
require __DIR__ . DIRECTORY_SEPARATOR . 'Helpers/Common.php';

/**
 * O2System Email Autoload
 *
 * @param $className
 */
spl_autoload_register(
    function ( $className ) {
        if ( strpos( $className, 'O2System\Email\\' ) === false ) {
            return;
        }

        $className = ltrim( $className, '\\' );
        $filePath = '';

        if ( $lastNsPos = strripos( $className, '\\' ) ) {
            $namespace = substr( $className, 0, $lastNsPos );
            $className = substr( $className, $lastNsPos + 1 );
            $filePath = $namespace . '\\';
        }

        $filePath .= str_replace( '_', DIRECTORY_SEPARATOR, $className ) . '.php';

        // Fixed Path
        $filePath = str_replace( 'O2System\Email\\', __DIR__ . DIRECTORY_SEPARATOR, $filePath );
        $filePath = str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $filePath );

        if ( file_exists( $filePath ) ) {
            require $filePath;
        }

    },
    true,
    true
);
