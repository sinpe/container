<?php
/*
 * This file is part of the long/container package.
 *
 * (c) Sinpe <support@sinpe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sinpe\Container;

use Psr\Container\ContainerInterface as Base;

/**
 * DI Container interface
 * 
 * @package Sinpe\Container
 * @since   1.0.0
 */
interface ContainerInterface extends Base
{
    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array  $parameters
     *
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []);

    /**
     * Call the given Closure / class::method and inject its dependencies.
     *
     * @param callable $callback
     * @param array           $parameters
     * @param string|null     $defaultMethod
     *
     * @return mixed
     */
    public function call($callback, array $parameters = [], $defaultMethod = null);
}
