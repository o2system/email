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
use O2System\Email\Datastructures\Config;
use O2System\Email\Spool;

/**
 * Class SmtpProtocol
 *
 * @package O2System\Email\Protocols
 */
class SmtpProtocol extends AbstractProtocol
{
    /**
     * SmtpProtocol::$config
     *
     * SMTP Connection config
     *
     * @var Config
     */
    protected $config;

    /**
     * SmtpProtocol::$handle
     *
     * SMTP Connection resource.
     *
     * @var resource
     */
    protected $handle;

    /**
     * SmtpProtocol::$keepAlive
     *
     * Keep SMTP connection alive flag.
     *
     * @var bool
     */
    protected $keepAlive = false;

    /**
     * SmtpProtocol::$authenticate
     *
     * SMTP connection authenticate flag.
     *
     * @var bool
     */
    protected $authenticate = false;

    // ------------------------------------------------------------------------

    /**
     * SmtpProtocol::__construct
     *
     * @param \O2System\Email\Spool $spool
     */
    public function __construct( Spool $spool )
    {
        parent::__construct( $spool );

        $this->config = $this->spool->getConfig()->offsetGet( 'smtp' );

        if ( ! $this->config->offsetExists( 'timeout' ) ) {
            $this->config[ 'timeout' ] = 5;
        }

        if ( ! $this->config->offsetExists( 'deliveryStatus' ) ) {
            $this->config[ 'deliveryStatus' ] = false;
        }
    }

    // ------------------------------------------------------------------------

    /**
     * SmtpProtocol::connect
     *
     * Create an SMTP connection resource.
     *
     * @return bool
     */
    protected function connect()
    {
        if ( is_resource( $this->handle ) ) {
            return true;
        }

        $ssl = ( $this->config->encryption === 'ssl' ) ? 'ssl://' : '';

        if( ! $this->config->offsetExists('host') ) {
            $this->spool->addError( 0,
                language()->getLine( 'E_EMAIL_SMTP_REQUIRED_HOST' ) );

            return false;
        }

        // Connect to the host and port
        if ( false === ( $this->handle = @fsockopen( $ssl . $this->config->host, $this->config->port, $errno, $errstr,
                $this->config->timeout ) )
        ) {
            $this->spool->addError( 0,
                language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION' ) );

            return false;
        }

        stream_set_timeout( $this->handle, $this->config->timeout );

        // 220 it's mean the smtp service it's connected.
        if ( false === ( $response = $this->response( 220 ) ) ) {
            $this->spool->addError( 0,
                language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION_WITH', [ $response ] ) );

            return false;
        }

        if ( $this->config->encryption === 'tls' ) {
            if( $this->command( 'hello' ) ) {
                if( ! $this->command( 'starttls' ) ) {
                    $this->spool->addError( 0,
                        language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION_WITH_TLS' ) );
                }
            }

            $crypto = stream_socket_enable_crypto( $this->handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );

            if ( $crypto !== true ) {
                $this->spool->addError( 0,
                    language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION_WITH_TLS' ) );

                return false;
            }
        }

        if( $this->command( 'hello' ) ) {
            if( $this->authenticate() ) {
                return true;
            }
        }

        return false;
    }

    protected function disconnect()
    {
        if ( $this->connect() ) {
            fputs( $this->handle, "RSET" . "\r\n" );
            fputs( $this->handle, "QUIT" . "\r\n" );
        }
    }

    protected function sending( $finalMessage )
    {
        if ( $this->connect() ) {

            //email from
            fputs( $this->handle, "MAIL FROM: <" . $this->message->getFrom()->getEmail() . ">" . "\r\n" );
            if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

                $this->spool->addLog( $response );

                // 250 OK
                if ( mb_substr( $response, 0, 3 ) != 250 ) {
                    $this->spool->addError( 0,
                        language()->getLine( 'E_EMAIL_SMTP_FAILED_SENDING_WITH', [ $response ] ) );

                    return false;
                }
            }

            //email to
            $headersTo = [];
            $finalMessageTo = [];
            if ( false !== ( $to = $this->message->getTo() ) ) {
                foreach ( $to as $address ) {
                    if ( $address instanceof Address ) {
                        $headersTo[] = $address->__toString();
                        $finalMessageTo[] = $address->getEmail();

                        fputs( $this->handle, "RCPT TO: <" . $address->getEmail() . ">" . "\r\n" );

                        if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

                            $this->spool->addLog( $response );

                            // 250 OK
                            if ( mb_substr( $response, 0, 3 ) != 250 ) {
                                $this->spool->addError( 0,
                                    language()->getLine( 'E_EMAIL_SMTP_FAILED_SENDING_WITH', [ $response ] ) );

                                return false;
                                break;
                            }
                        }
                    }
                }
            }

            //the email
            fputs( $this->handle, "DATA" . "\r\n" );
            if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

                $this->spool->addLog( $response );

                // 354 Go ahead
                if ( mb_substr( $response, 0, 3 ) != 354 ) {
                    $this->spool->addError( 0,
                        language()->getLine( 'E_EMAIL_SMTP_FAILED_SENDING_WITH', [ $response ] ) );

                    return false;
                }
            }

            //construct headers
            $finalHeaders = '';
            foreach ( $this->prepareHeaders() as $name => $value ) {
                $finalHeaders .= $name . ': ' . $value . "\r\n";
            }

            $finalBody = preg_replace( '/^\./m', '..$1', $this->prepareBody() );

            fputs( $this->handle, $finalHeaders . $finalBody );

            if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

                $this->spool->addLog( $response );

                // 354 Go ahead
                if ( mb_substr( $response, 0, 3 ) != 354 ) {
                    $this->spool->addError( 0,
                        language()->getLine( 'E_EMAIL_SMTP_FAILED_SENDING_WITH', [ $response ] ) );

                    return false;
                }
            }

            // say goodbye
            fputs( $this->handle, "QUIT" . "\r\n" );
            $response = fgets( $this->handle, 4096 );
            $logArray[ 'quitresponse' ] = "$response";
            $logArray[ 'quitcode' ] = substr( $response, 0, 3 );
            fclose( $this->handle );

            //a return value of 221 in $retVal["quitcode"] is a success
            return ( $logArray );
        }

        return false;
    }

    /**
     * SmtpProtocol::write
     *
     * Write SMTP data
     *
     * @param    string $data
     *
     * @return    bool
     */
    protected function write( $data, $code )
    {
        fputs( $this->handle, $data . "\r\n" );

        return $this->response( $code );
    }

    // --------------------------------------------------------------------

    protected function response( $code )
    {
        if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

            $this->spool->addLog( $response );

            return (bool) ( mb_substr( $response, 0, 3 ) != $code );
        }

        return false;
    }

    // --------------------------------------------------------------------

    /**
     * SmtpProtocol::command
     *
     * Send SMTP command
     *
     * @param    string
     * @param    string
     *
     * @return    bool
     */
    protected function command( $command, $data = '' )
    {
        switch ( $command ) {
            case 'hello':
                return $this->write('HELO ' . $this->getHostname(), 250 );
                break;

            case 'starttls':
                $this->write( 'STARTTLS', 250 );
                break;

            case 'from':
                $this->write( 'MAIL FROM:<' . $data . '>', 250 );
                break;

            case 'to':
                if ( $this->config->offsetGet( 'deliveryStatus' ) ) {
                    return $this->write( 'RCPT TO:<' . $data . '> NOTIFY=SUCCESS,DELAY,FAILURE ORCPT=rfc822;' . $data, 250 );
                } else {
                    return $this->write( 'RCPT TO:<' . $data . '>', 250 );
                }
                break;

            case 'data':
                return $this->write( 'DATA', 354 );
                break;

            case 'reset':
                return $this->write( 'RSET', 250 );
                break;

            case 'quit':
                $this->write( 'QUIT', 221 );
                fclose( $this->handle );

                return true;
                break;
        }

        return false;
    }

    // --------------------------------------------------------------------

    /**
     * SmtpProtocol::authenticate
     *
     * SMTP Authenticate
     *
     * @return  bool
     */
    protected function authenticate()
    {
        //request for auth login
        fputs( $this->handle, "AUTH LOGIN" . "\r\n" );
        if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

            print_out($response);

            $this->spool->addLog( $response );

            // 334 it's mean the auth request is accepted using base64 encoded username
            if ( mb_substr( $response, 0, 3 ) != 334 ) {
                $this->spool->addError( 0,
                    language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION_WITH', [ $response ] ) );

                return false;
            }
        }

        //send the username
        fputs( $this->handle, base64_encode( $this->config->offsetGet( 'username' ) ) . "\r\n" );

        if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

            $this->spool->addLog( $response );

            // 334 it's mean the auth request is accepted using base64 encoded password
            if ( mb_substr( $response, 0, 3 ) != 334 ) {
                $this->spool->addError( 0,
                    language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION_WITH', [ $response ] ) );

                return false;
            }
        }

        //send the password
        fputs( $this->handle, base64_encode( $this->config->offsetGet( 'password' ) ) . "\r\n" );
        if ( false !== ( $response = fgets( $this->handle, 4096 ) ) ) {

            $this->spool->addLog( $response );

            // 535 it's mean the auth login is failed.
            // 334 it's mean the auth request is accepted using base64 encoded password
            // 235 it's mean the auth request is successful
            if ( mb_substr( $response, 0, 3 ) != 235 ) {

                $this->spool->addError( 0,
                    language()->getLine( 'E_EMAIL_SMTP_FAILED_CONNECTION_WITH', [ $response ] ) );

                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    protected function getReportNumbers( $report )
    {
        $reportLines = explode( PHP_EOL, $report );
        $reportLines = array_filter( $reportLines );

        $reportNumbers = [];
        foreach ( $reportLines as $reportLine ) {
            $reportNumbers[] = (int)mb_substr( $reportLine, 0, 3 );
        }

        $reportNumbers = array_unique( $reportNumbers );

        return $reportNumbers;
    }

    /**
     * Get SMTP data
     *
     * @return    string
     */
    protected function getReport()
    {
        $data = '';

        while ( $str = fgets( $this->handle, 512 ) ) {
            $data .= $str;

            if ( $str[ 3 ] === ' ' ) {
                break;
            }
        }

        $this->spool->addLog( $data );

        return $data;
    }

    // --------------------------------------------------------------------

    /**
     * Get Hostname
     *
     * There are only two legal types of hostname - either a fully
     * qualified domain name (eg: "mail.example.com") or an IP literal
     * (eg: "[1.2.3.4]").
     *
     * @link    https://tools.ietf.org/html/rfc5321#section-2.3.5
     * @link    http://cbl.abuseat.org/namingproblems.html
     * @return    string
     */
    protected function getHostname()
    {
        if ( isset( $_SERVER[ 'SERVER_NAME' ] ) ) {
            return $_SERVER[ 'SERVER_NAME' ];
        }

        return isset( $_SERVER[ 'SERVER_ADDR' ] ) ? '[' . $_SERVER[ 'SERVER_ADDR' ] . ']' : '[127.0.0.1]';
    }

    // --------------------------------------------------------------------
}