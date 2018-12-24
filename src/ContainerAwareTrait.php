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

trait ContainerAwareTrait
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Get container
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set container
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

}
