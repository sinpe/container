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

use Psr\Container\ContainerExceptionInterface;

/**
 * Base interface representing a generic exception in a container.
 */
class Exception extends \RuntimeException implements ContainerExceptionInterface
{ }
