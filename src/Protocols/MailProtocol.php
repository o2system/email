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

namespace O2System\Email\Protocols;

// ------------------------------------------------------------------------

use O2System\Email\Abstracts\AbstractProtocol;
use O2System\Email\Address;
use O2System\Email\Message;

/**
 * Class MailProtocol
 *
 * @package O2System\Email\Protocols
 */
class MailProtocol extends AbstractProtocol
{
    protected $newLine = "\r\n";

    /**
     * MailProtocol::sending
     *
     * Protocol message sending process.
     *
     * @param array $finalMessage
     *
     * @return bool
     */
    protected function sending( $finalMessage )
    {
        if ( ini_get( 'safe_mode' ) ) {
            return mail(
                $finalMessage[ 'recipients' ],
                $finalMessage[ 'subject' ],
                $finalMessage[ 'body' ],
                $finalMessage[ 'headers' ] );
        } else {
            // most documentation of sendmail using the "-f" flag lacks a space after it, however
            // we've encountered servers that seem to require it to be in place.
            return mail(
                $finalMessage[ 'recipients' ],
                $finalMessage[ 'subject' ],
                $finalMessage[ 'body' ],
                $finalMessage[ 'headers' ],
                '-f ' . $finalMessage[ 'returnPath' ]
            );
        }
    }
}