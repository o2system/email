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

namespace O2System\Email;

// ------------------------------------------------------------------------

use O2System\Spl\Info\SplFileInfo;

/**
 * Class Attachment
 *
 * @package O2System\Email
 */
class Attachment extends SplFileInfo
{
    public function getBody()
    {
        $string = '';

        if ( file_exists( $this->getRealPath() ) ) {
            $file = fopen( $this->getRealPath(), "r" );
            $attachment = fread( $file, filesize( $this->getRealPath() ) );
            $attachment = chunk_split( base64_encode( $attachment ) );
            fclose( $file );

            $string = 'Content-Type: \'' . $this->getType() . '\'; name="' . $this->getBasename() . '"' . PHP_EOL;
            $string .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
            $string .= 'Content-ID: <' . $this->getBasename() . '>' . PHP_EOL;
            // $string .= 'X-Attachment-Id: ebf7a33f5a2ffca7_0.1' . PHP_EOL;
            $string .= PHP_EOL . $attachment . PHP_EOL . PHP_EOL;
        }

        return $string;
    }
}