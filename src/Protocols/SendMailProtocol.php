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

/**
 * Class SendMailProtocol
 *
 * @package O2System\Email\Protocols
 */
class SendMailProtocol extends AbstractProtocol
{
    /**
     * SendingMailProtocol::sending
     *
     * Protocol message sending process.
     *
     * @param array $finalMessage
     *
     * @return bool
     */
    protected function sending( $finalMessage )
    {
        // is popen() enabled?
        if ( ! function_usable( 'popen' ) OR false === ( $fp = @popen( $this->spool->getConfig()->offsetGet( 'mailpath' ) . ' -oi -f ' . $finalMessage[ 'recipients' ] . ' -t',
                'w' ) )
        ) {
            // server probably has popen disabled, so nothing we can do to get a verbose error.
            return false;
        }

        fputs( $fp, $finalMessage[ 'header' ] );
        fputs( $fp, $finalMessage[ 'body' ] );

        $status = pclose( $fp );

        if ( $status !== 0 ) {
            $this->spool->addError( $status, language()->getLine( 'E_EMAIL_SENDMAIL' ) );

            return false;
        }

        return true;
    }
}