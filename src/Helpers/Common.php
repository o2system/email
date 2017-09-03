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

if( ! function_exists( 'prepare_q_encoding' ) ) {
    /**
     * Prep Q Encoding
     *
     * Performs "Q Encoding" on a string for use in email headers.
     * It's related but not identical to quoted-printable, so it has its
     * own method.
     *
     * @param	string $string
     * @param string $charset default UTF8
     * @return	string
     */
    function prepare_q_encoding($string, $charset = 'utf8')
    {
        $string = str_replace(array("\r", "\n"), '', $string);
        if ($charset === 'UTF-8')
        {
            // Note: We used to have mb_encode_mimeheader() as the first choice
            //       here, but it turned out to be buggy and unreliable. DO NOT
            //       re-add it! -- Narf
            if (ICONV_ENABLED === TRUE)
            {
                $output = @iconv_mime_encode('', $string,
                    array(
                        'scheme' => 'Q',
                        'line-length' => 76,
                        'input-charset' => $charset,
                        'output-charset' => $charset,
                        'line-break-chars' => $this->crlf
                    )
                );
                // There are reports that iconv_mime_encode() might fail and return FALSE
                if ($output !== FALSE)
                {
                    // iconv_mime_encode() will always put a header field name.
                    // We've passed it an empty one, but it still prepends our
                    // encoded string with ': ', so we need to strip it.
                    return byte_safe_substr($output, 2);
                }
                $chars = iconv_strlen($string, 'UTF-8');
            }
            elseif (MB_ENABLED === TRUE)
            {
                $chars = mb_strlen($string, 'UTF-8');
            }
        }
        // We might already have this set for UTF-8
        isset($chars) OR $chars = byte_safe_strlen($string);
        $output = '=?'.$charset.'?Q?';
        for ($i = 0, $length = byte_safe_strlen($output); $i < $chars; $i++)
        {
            $character = ($charset === 'UTF-8' && ICONV_ENABLED === TRUE)
                ? '='.implode('=', str_split(strtoupper(bin2hex(iconv_substr($string, $i, 1, $charset))), 2))
                : '='.strtoupper(bin2hex($string[$i]));
            // RFC 2045 sets a limit of 76 characters per line.
            // We'll append ?= to the end of each line though.
            if ($length + ($l = byte_safe_strlen($character)) > 74)
            {
                $output .= '?='.PHP_EOL // EOL
                    .' =?'.$charset.'?Q?'.$character; // New line
                $length = 6 + byte_safe_strlen($charset) + $l; // Reset the length for the new line
            }
            else
            {
                $output .= $character;
                $length += $l;
            }
        }
        // End the header
        return $output.'?=';
    }
}
// --------------------------------------------------------------------

if( ! function_exists( 'prepare_quoted_printable' ) ) {
    /**
     * Prep Quoted Printable
     *
     * Prepares string for Quoted-Printable Content-Transfer-Encoding
     * Refer to RFC 2045 http://www.ietf.org/rfc/rfc2045.txt
     *
     * @param	string
     * @return	string
     */
    function prepare_quoted_printable($str, $crlf = PHP_EOL)
    {
        // ASCII code numbers for "safe" characters that can always be
        // used literally, without encoding, as described in RFC 2049.
        // http://www.ietf.org/rfc/rfc2049.txt
        static $ascii_safe_chars = array(
            // ' (  )   +   ,   -   .   /   :   =   ?
            39, 40, 41, 43, 44, 45, 46, 47, 58, 61, 63,
            // numbers
            48, 49, 50, 51, 52, 53, 54, 55, 56, 57,
            // upper-case letters
            65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
            // lower-case letters
            97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122
        );
        // We are intentionally wrapping so mail servers will encode characters
        // properly and MUAs will behave, so {unwrap} must go!
        $str = str_replace(array('{unwrap}', '{/unwrap}'), '', $str);
        // RFC 2045 specifies CRLF as "\r\n".
        // However, many developers choose to override that and violate
        // the RFC rules due to (apparently) a bug in MS Exchange,
        // which only works with "\n".
        if ($crlf === "\r\n")
        {
            return quoted_printable_encode($str);
        }
        // Reduce multiple spaces & remove nulls
        $str = preg_replace(array('| +|', '/\x00+/'), array(' ', ''), $str);
        // Standardize newlines
        if (strpos($str, "\r") !== FALSE)
        {
            $str = str_replace(array("\r\n", "\r"), "\n", $str);
        }
        $escape = '=';
        $output = '';
        foreach (explode("\n", $str) as $line)
        {
            $length = byte_safe_strlen($line);
            $temp = '';
            // Loop through each character in the line to add soft-wrap
            // characters at the end of a line " =\r\n" and add the newly
            // processed line(s) to the output (see comment on $crlf class property)
            for ($i = 0; $i < $length; $i++)
            {
                // Grab the next character
                $character = $line[$i];
                $ascii = ord($character);
                // Convert spaces and tabs but only if it's the end of the line
                if ($ascii === 32 OR $ascii === 9)
                {
                    if ($i === ($length - 1))
                    {
                        $character = $escape.sprintf('%02s', dechex($ascii));
                    }
                }
                // DO NOT move this below the $ascii_safe_chars line!
                //
                // = (equals) signs are allowed by RFC2049, but must be encoded
                // as they are the encoding delimiter!
                elseif ($ascii === 61)
                {
                    $character = $escape.strtoupper(sprintf('%02s', dechex($ascii)));  // =3D
                }
                elseif ( ! in_array($ascii, $ascii_safe_chars, TRUE))
                {
                    $character = $escape.strtoupper(sprintf('%02s', dechex($ascii)));
                }
                // If we're at the character limit, add the line to the output,
                // reset our temp variable, and keep on chuggin'
                if ((byte_safe_strlen($temp) + byte_safe_strlen($character)) >= 76)
                {
                    $output .= $temp.$escape.$this->crlf;
                    $temp = '';
                }
                // Add the character to our temporary line
                $temp .= $character;
            }
            // Add our completed line to the output
            $output .= $temp.$this->crlf;
        }
        // get rid of extra CRLF tacked onto the end
        return byte_safe_substr($output, 0, byte_safe_strlen($this->crlf) * -1);
    }
}
// --------------------------------------------------------------------

if( ! function_exists( 'byte_safe_strlen' ) ) {
    /**
     * byte_safe_strlen()
     *
     * @param	string	$string
     * @return	int
     */
    function byte_safe_strlen( $string )
    {
        return ( ( extension_loaded( 'mbstring' ) && ini_get( 'mbstring.func_overload' ) ) )
            ? mb_strlen( $string, '8bit' )
            : strlen( $string );
    }
}
// --------------------------------------------------------------------

if( ! function_exists( 'byte_safe_strlen' ) ) {
    /**
     * byte_safe_substr()
     *
     * @param    string $string
     * @param    int $start
     * @param    int $length
     * @return    string
     */
    function byte_safe_substr( $string, $start, $length = null )
    {
        if ( ( extension_loaded( 'mbstring' ) && ini_get( 'mbstring.func_overload' ) ) ) {
            return mb_substr( $string, $start, $length, '8bit' );
        }
        return isset( $length )
            ? substr( $string, $start, $length )
            : substr( $string, $start);
    }
}