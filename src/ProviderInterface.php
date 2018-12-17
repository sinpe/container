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

use Psr\Container\ContainerInterface;

/**
 * Service set Provider interface.
 * 
 * @package Sinpe\Container
 * @since   1.0.0
 */
interface ProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * @param ContainerInterface $container A container instance
     */
    public function register(ContainerInterface $container);
}
