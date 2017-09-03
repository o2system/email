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

namespace O2System\Email\Abstracts;

// ------------------------------------------------------------------------

use O2System\Email\Address;
use O2System\Email\Message;
use O2System\Email\Spool;

/**
 * Class AbstractProtocol
 *
 * @package O2System\Email\Abstracts
 */
abstract class AbstractProtocol
{
    /**
     * AbstractProtocol::$spool
     *
     * @var Spool
     */
    protected $spool;

    /**
     * AbstractProtocol::$message
     *
     * @var Message
     */
    protected $message;

    /**
     * AbstractProtocol::$newLine
     *
     * @var string
     */
    protected $newLine = PHP_EOL;

    /**
     * AbstractProtocol::$boundary
     *
     * @var string
     */
    protected $boundary;

    /**
     * AbstractProtocol::$multipart
     *
     * Whether to send multipart alternatives.
     * Yahoo! doesn't seem to like these.
     *
     * @var    bool
     */
    protected $multipart = true;

    // ------------------------------------------------------------------------

    /**
     * AbstractProtocol constructor.
     *
     * @param \O2System\Email\Spool $spool
     */
    public function __construct( Spool $spool )
    {
        $this->spool = $spool;
        $this->boundary = uniqid( '__o2emailBoundary_alt_' );
    }

    // ------------------------------------------------------------------------

    protected function prepareSubject()
    {
        return prepare_q_encoding( $this->message->getSubject() );
    }

    /**
     * AbstractProtocol::prepareHeaders
     *
     * Prepare message headers.
     *
     * @return array
     */
    protected function prepareHeaders()
    {
        $headers = $this->message->getHeaders();

        // Add Message User-Agent and X-Mailer Header
        $headers[ 'User-Agent' ] = $headers[ 'X-Mailer' ] = $this->spool->getConfig()->offsetGet( 'userAgent' );

        // Add Message X-Sender Header
        $headers[ 'X-Sender' ] = $this->message->getFrom()->getEmail();

        // Add Message Priority Header
        if ( false !== ( $priority = $this->message->getPriority() ) ) {
            $headers[ 'X-Priority' ] = $priority;
        }

        // Add Message-ID Header
        $headers[ 'Message-ID' ] = '<' . uniqid( '' ) . $this->message->getReturnPath() . '>';

        // Add Message Mime Version Header
        $headers[ 'Mime-Version' ] = $this->message->getMimeVersion();

        // Add Message To Header
        $recipients = [];
        if ( false !== ( $to = $this->message->getTo() ) ) {
            foreach ( $to as $address ) {
                $recipients[] = $address->getEmail();
            }

            $headers[ 'To' ] = implode( ', ', $recipients );
        }

        // Add Message From Header
        $headers[ 'From' ] = $this->message->getFrom()->__toString();

        // Add Message Cc Header
        if ( false !== ( $cc = $this->message->getCc() ) ) {
            foreach ( $cc as $address ) {
                $recipients[] = $address->getEmail();
            }

            $headers[ 'Cc' ] = implode( ', ', $recipients );
        }

        // Add Message Bcc Header
        if ( $this->spool->getConfig()->offsetGet( 'protocol' ) === 'smtp' ) {
            if ( false !== ( $bcc = $this->message->getBcc() ) ) {
                foreach ( $bcc as $address ) {
                    $recipients[] = $address->getEmail();
                }

                $headers[ 'Bcc' ] = implode( ', ', $recipients );
            }
        }

        // Add Message Reply-To Header
        $headers[ 'Reply-To' ] = $this->message->getReplyTo()->__toString();

        if ( $this->spool->getConfig()->offsetGet( 'protocol' ) !== 'mail' ) {
            $headers[ 'Subject' ] = $this->message->getSubject();
        }

        switch ( $this->message->getContentType() ) {
            default:
            case 'plain':
            case 'text':
                $headers[ 'Content-Type' ] = 'text/plain; charset=' . $this->message->getCharset();
                $headers[ 'Content-Transfer-Encoding' ] = $this->message->getEncoding();
                break;
            case 'html':
                if ( $this->multipart === false ) {
                    $headers[ 'Content-Type' ] = 'text/html; charset=' . $this->message->getCharset();
                    $headers[ 'Content-Transfer-Encoding' ] = 'quoted-printable';
                } else {
                    $headers[ 'Content-Type' ] = 'multipart/alternative; boundary="' . $this->boundary . '"';
                }

                break;
        }

        return $headers;
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractProtocol::prepareBody
     *
     * Prepare message body and unwrap body special elements.
     *
     * @return string
     */
    protected function prepareBody()
    {
        if ( $this->message->getContentType() === 'html' ) {
            if ( $this->multipart === false ) {
                $body = $this->wordwrap( $this->message->getBody() );
            } else {
                $altBody = $this->message->getAltBody();
                $altBody = empty( $altBody ) ? strip_tags( $this->message->getBody() ) : $altBody;

                $body = 'This is a multi-part message in MIME format.'
                    . $this->newLine
                    . 'Your email application may not support this format.'
                    . str_repeat( $this->newLine, 2 )
                    . '--'
                    . $this->boundary
                    . $this->newLine
                    . 'Content-Type: text/plain; charset='
                    . $this->message->getCharset()
                    . $this->newLine
                    . 'Content-Transfer-Encoding: '
                    . $this->message->getEncoding()
                    . str_repeat( $this->newLine, 2 )
                    . $altBody
                    . str_repeat( $this->newLine, 2 )
                    . '--'
                    . $this->boundary
                    . $this->newLine
                    . 'Content-Type: text/html; charset='
                    . $this->message->getCharset()
                    . $this->newLine
                    . 'Content-Transfer-Encoding: quoted-printable'
                    . str_repeat( $this->newLine, 2 )
                    . prepare_quoted_printable( $this->wordwrap( $this->message->getBody() ) )
                    . str_repeat( $this->newLine, 2 )
                    . '--' . $this->boundary
                    . '--';
            }

        } else {
            $body = $this->message->getBody();
        }

        return preg_replace_callback( '/\{unwrap\}(.*?)\{\/unwrap\}/si', [ $this, 'unwrapBodyCallback' ], $body );
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractProtocol::wordWrap
     *
     * Word Wrap
     *
     * @param    string $string
     *
     * @return    string
     */
    protected function wordwrap( $string )
    {
        // Set the character limit
        $limit = $this->spool->getConfig()->offsetGet( 'wordwrap' );
        if ( is_bool( $limit ) ) {
            $limit = 76;
        }

        // Standardize newlines
        if ( strpos( $string, "\r" ) !== false ) {
            $string = str_replace( [ "\r\n", "\r" ], "\n", $string );
        }

        // Reduce multiple spaces at end of line
        $string = preg_replace( '| +\n|', "\n", $string );

        // If the current word is surrounded by {unwrap} tags we'll
        // strip the entire chunk and replace it with a marker.
        $unwrap = [];
        if ( preg_match_all( '|\{unwrap\}(.+?)\{/unwrap\}|s', $string, $matches ) ) {
            for ( $i = 0, $c = count( $matches[ 0 ] ); $i < $c; $i++ ) {
                $unwrap[] = $matches[ 1 ][ $i ];
                $string = str_replace( $matches[ 0 ][ $i ], '{{unwrapped' . $i . '}}', $string );
            }
        }

        // Use PHP's native function to do the initial wordwrap.
        // We set the cut flag to FALSE so that any individual words that are
        // too long get left alone. In the next step we'll deal with them.
        $string = wordwrap( $string, $limit, "\n", false );

        // Split the string into individual lines of text and cycle through them
        $output = '';
        foreach ( explode( "\n", $string ) as $line ) {
            // Is the line within the allowed character count?
            // If so we'll join it to the output and continue
            if ( mb_strlen( $line ) <= $limit ) {
                $output .= $line . $this->newLine;
                continue;
            }

            $temp = '';
            do {
                // If the over-length word is a URL we won't wrap it
                if ( preg_match( '!\[url.+\]|://|www\.!', $line ) ) {
                    break;
                }

                // Trim the word down
                $temp .= mb_substr( $line, 0, $limit - 1 );
                $line = mb_substr( $line, $limit - 1 );
            } while ( mb_strlen( $line ) > $limit );

            // If $temp contains data it means we had to split up an over-length
            // word into smaller chunks so we'll add it back to our current line
            if ( $temp !== '' ) {
                $output .= $temp . $this->newLine;
            }

            $output .= $line . $this->newLine;
        }

        // Put our markers back
        if ( count( $unwrap ) > 0 ) {
            foreach ( $unwrap as $key => $value ) {
                $output = str_replace( '{{unwrapped' . $key . '}}', $value, $output );
            }
        }

        return $output;
    }

    // --------------------------------------------------------------------

    /**
     * AbstractProtocol::unwrapBodyCallback
     *
     * Strip line-breaks via callback
     *
     * @param array $matches
     *
     * @return string
     */
    protected function unwrapBodyCallback( $matches )
    {
        if ( strpos( $matches[ 1 ], "\r" ) !== false OR strpos( $matches[ 1 ], "\n" ) !== false ) {
            $matches[ 1 ] = str_replace( [ "\r\n", "\r", "\n" ], '', $matches[ 1 ] );
        }

        return $matches[ 1 ];
    }

    // --------------------------------------------------------------------

    /**
     * Clean Extended Email Address: Joe Smith <joe@smith.com>
     *
     * @param    string
     *
     * @return    string
     */
    public function cleanEmail( $email )
    {
        if ( ! is_array( $email ) ) {
            return preg_match( '/\<(.*)\>/', $email, $match ) ? $match[ 1 ] : $email;
        }

        $clean_email = [];

        foreach ( $email as $addy ) {
            $clean_email[] = preg_match( '/\<(.*)\>/', $addy, $match ) ? $match[ 1 ] : $addy;
        }

        return (string)$clean_email;
    }

    /**
     * AbstractProtocol::validateEmailForShell
     *
     * Applies stricter, shell-safe validation to email addresses.
     * Introduced to prevent RCE via sendmail's -f option.
     *
     * @see        https://gist.github.com/Zenexer/40d02da5e07f151adeaeeaa11af9ab36
     * @license    https://creativecommons.org/publicdomain/zero/1.0/	CC0 1.0, Public Domain
     *
     * Credits for the base concept go to Paul Buonopane <paul@namepros.com>
     *
     * @param    string $email
     *
     * @return    bool
     */
    protected function validateEmailForShell( &$email )
    {
        if ( function_exists( 'idn_to_ascii' ) && $atpos = strpos( $email, '@' ) ) {
            $email = mb_substr( $email, 0, ++$atpos ) . idn_to_ascii( mb_substr( $email, $atpos ) );
        }

        return ( filter_var( $email,
                FILTER_VALIDATE_EMAIL ) === $email && preg_match( '#\A[a-z0-9._+-]+@[a-z0-9.-]{1,253}\z#i', $email ) );
    }

    // --------------------------------------------------------------------

    /**
     * AbstractProtocol::send
     *
     * Send the message.
     *
     * @param Message $message
     *
     * @return bool
     */
    public function send( Message $message )
    {
        $this->message = $message;

        // GEt final from
        $finalMessage[ 'from' ] = $this->message->getFrom();

        // Get final subject
        $finalMessage[ 'subject' ] = $this->message->getSubject();

        // Get final headers
        $headers = $this->prepareHeaders();

        $finalMessage[ 'headers' ] = '';

        foreach ( $headers as $name => $value ) {
            $finalMessage[ 'headers' ] .= $name . ': ' . $value . $this->newLine;
        }

        // Get final body
        $finalMessage[ 'body' ] = $this->prepareBody();

        // Attach final body
        if ( false !== ( $attachments = $this->message->getAttachments() ) ) {
            $finalMessage[ 'body' ] .= str_repeat( $this->newLine, 2 );

            foreach( $attachments as $attachment ) {
                $finalMessage[ 'body' ] .= '--'.$this->boundary.$this->newLine
                    .$attachment->getBody().$this->newLine.'--'.$this->boundary.'--';
            }
        }

        // Get final return-path
        $finalMessage[ 'returnPath' ] = $this->message->getReturnPath();

        if ( false !== ( $subscribers = $this->message->getSubscribers() ) ) {

            foreach ( $subscribers as $address ) {
                if ( $address instanceof Address ) {
                    $finalMessage[ 'recipients' ] = $address->getEmail();
                    if ( ! $this->sending( $finalMessage ) ) {
                        return false;
                        break;
                    }

                    /**
                     * Sending 15 Mails in 60 seconds is equivalent to sending one mail every 4 seconds.
                     */
                    sleep( 4 );
                }
            }

            return true;

        } elseif ( false !== ( $to = $this->message->getTo() ) ) {
            $recipients = [];

            foreach ( $to as $address ) {
                if ( $address instanceof Address ) {
                    $recipients[] = $address->getEmail();
                }
            }

            $finalMessage[ 'recipients' ] = implode( ',', $recipients );

            return $this->sending( $finalMessage );
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractProtocol::sending
     *
     * Protocol message sending process.
     *
     * @param array $finalMessage
     *
     * @return bool
     */
    abstract protected function sending( $finalMessage );
}