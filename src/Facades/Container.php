<?php
/*
 * This file is part of the long/container package.
 *
 * (c) Sinpe <support@sinpe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sinpe\Container\Facades;

use Sinpe\Container\ContainerInterface;
use Sinpe\Container\Facade;

/**
 * Container facade.
 * 
 * @package Sinpe\Framework
 * @since   1.0.0
 */
class Container extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ContainerInterface::class;
    }
}