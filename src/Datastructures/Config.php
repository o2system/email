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

namespace O2System\Email\Datastructures;

// ------------------------------------------------------------------------

/**
 * Class Config
 *
 * @package O2System\Email\Datastructures
 */
class Config extends \O2System\Kernel\Datastructures\Config
{
    /**
     * Config::__construct
     *
     * @param array $config
     */
    public function __construct( array $config = [] )
    {
        $defaultConfig = [
            'protocol' => 'mail',
            'userAgent' => 'O2System\Email',
            'wordwrap' => false
        ];

        $config = array_merge( $defaultConfig, $config );

        parent::__construct( $config );
    }
}