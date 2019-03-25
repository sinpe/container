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

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * DI Container.
 * 
 * @package Sinpe\Container
 * @since   1.0.0
 */
class Container implements ContainerInterface, PsrContainerInterface, \ArrayAccess
{
    /**
     * Context
     *
     * @var array
     */
    public $contextual = [];

    /**
     * The stack of concretions currently being built.
     *
     * @var array
     */
    protected $buildStack = [];

    /**
     * The registered alias maps
     *
     * @var array
     */
    protected $aliasMaps = [];

    /**
     * The registered aliases keyed by the abstract name.
     *
     * @var array
     */
    protected $actualAliases = [];

    /**
     * The container's item.
     *
     * @var array
     */
    private $items = [];

    /**
     * The container's shared values.
     *
     * @var array
     */
    private $sharedValues = [];

    /**
     * Factories.
     *
     * @var array
     */
    private $factories = [];

    /**
     * Frozened items
     *
     * @var array
     */
    private $frozen = [];

    /**
     * Raw items.
     *
     * @var array
     */
    private $raw = [];

    /**
     * __construct.
     */
    public function __construct()
    {
		Facade::setContainer($this);

		class_alias(ContainerFacade::class, 'Container');

        if (!$this->has(PsrContainerInterface::class)) {
            $this->set(PsrContainerInterface::class, $this);
        }

        $this->registerDefaults();
    }

    /**
     * Sets an item.
     *
     * @param string $id    The unique identifier for the item
     * @param mixed  $value The value to define an item
     * @throws \RuntimeException Prevent override of a frozen item
     */
    public function offsetSet($id, $value)
    {
        if (isset($this->frozen[$id])) {
            throw new \RuntimeException(i18n('Cannot override frozen item "%s".', $id));
        }

        $this->items[$id] = $value;
    }

    /**
     * Sets an item.
     *
     * @param string $id    The unique identifier for the item
     * @param mixed  $value The value to define an item
     * @throws \RuntimeException Prevent override of a frozen item
     */
    public function set(string $id, $value)
    {
        $this->offsetSet($id, $value);
    }

    /**
     * Gets an item.
     *
     * @param string $id The unique identifier for the item
     *
     * @return mixed The calculated value of the item
     *
     * @throws \RuntimeException If the identifier is not defined
     */
    public function offsetGet($id)
    {
        return $this->make($id);
    }

    /**
     * Gets an item.
     *
     * @param string $id The unique identifier for the item
     *
     * @return mixed The calculated value of the item
     *
     * @throws \RuntimeException If the identifier is not defined
     */
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Checks if a item is set.
     *
     * @param string $id The unique identifier for the item
     *
     * @return bool
     */
    public function offsetExists($id)
    {
        return isset($this->items[$id]) || isset($this->aliasMaps[$id]);
    }

    /**
     * Checks if a item is set.
     *
     * @param string $id The unique identifier for the item
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->offsetExists($id);
    }

    /**
     * Unsets a item.
     *
     * @param string $id The unique identifier for item
     */
    public function offsetUnset($id)
    {
        if ($this->has($id)) {
            unset($this->items[$id],
                $this->frozen[$id],
                $this->raw[$id],
                $this->factories[$id],
                $this->aliasMaps[$id]);
        }
    }

    /**
     * Marks and sets a callable as being a factory service.
     *
     * @param string $id The unique identifier for the item
     * @param callable $callable The item to be used as a factory
     *
     * @return static
     */
    public function factory(string $id, callable $callable)
    {
        $this->factories[$id] = true;
        $this->items[$id] = $callable;
        return $this;
    }

    /**
     * Marks and sets an item as a raw value.
     *
     * @param string $id The unique identifier for the item
     * @param mixed The value of the item
     * 
     * @return static
     */
    public function raw(string $id, $value)
    {
        $this->raw[$id] = true;
        $this->items[$id] = $value;

        return $this;
    }

    /**
     * Returns all defined item names.
     *
     * @return array An array of item names
     */
    public function keys()
    {
        return array_keys($this->items) + array_keys($this->aliasMaps);
    }

    /**
     * Registers a service provider.
     *
     * @param ProviderInterface $provider A ProviderInterface instance
     * @param array                    $items   An array of items that customizes the provider
     *
     * @return static
     */
    public function register(ProviderInterface $provider, array $items = array())
    {
        $provider->register($this);

        foreach ($items as $key => $item) {
            $this[$key] = $item;
        }

        return $this;
    }

    /**
     * Register the default items.
     */
    protected function registerDefaults()
    {
    }

    /**
     * __get.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * __isset.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param string $alias
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getActual(string $alias)
    {
        if (!isset($this->aliasMaps[$alias])) {
            return $alias;
        }

        if ($this->aliasMaps[$alias] === $alias) {
            throw new \RuntimeException(i18n('%s is aliased to itself.', $alias));
        }
        // 允许多层
        return $this->getActual($this->aliasMaps[$alias]);
    }

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @param string $concrete
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function build($concrete, array $parameters = [])
    {
        if (!class_exists($concrete)) {
            throw new \RuntimeException(i18n('Class %s not exists.', $concrete));
        }

        $reflector = new \ReflectionClass($concrete);

        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface of Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException(i18n('Target %s is not instantiable.', $concrete));
        }

        $constructor = $reflector->getConstructor();

        // If there are no constructors, that means there are no dependencies then
        // we can just resolve the instances of the objects right away, without
        // resolving any other types or dependencies out of these containers.
        if (is_null($constructor)) {
            $object = new $concrete();
        } else {
            $this->buildStack[] = $concrete;

            $dependencies = $constructor->getParameters();
            // Once we have all the constructor's parameters we can create each of the
            // dependency instances and then use the reflection instances to make a
            // new instance of this class, injecting the created dependencies in.
            $instances = $this->resolveDependencies($dependencies, $parameters);

            array_pop($this->buildStack);

            $object = $reflector->newInstanceArgs($instances);
        }

        // 
        if ($object instanceof ContainerAwareInterface) {
            $object->setContainer($this);
        }

        // 有初始化方法时，调用初始化
        if (method_exists($object, '__init')) {
            $object->__init();
        }

        return $object;
    }

    /**
     * Resolve the given item from the container.
     *
     * @param string $id
     * @param array  $parameters
     *
     * @return mixed
     */
    public function make(string $id, array $parameters = [])
    {
        try {
            return $this->resolve($id, $parameters);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $id
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function resolve(string $id, $parameters = [])
    {
        $id = $this->getActual($id);

        // 支持直接使用类名，不需要预先注入
        if (!class_exists($id) && !$this->has($id)) {
            throw new \Exception(i18n('Item "%s" not exists.', $id));
        }

        // 是否根据特定的上下文创建实例
        $needsContextualBuild = !empty($parameters) || !is_null($this->getContextualConcrete($id));
        // If a value of the item is currently being managed as a shared value,
        // we'll just return an existing value 
        if (!$needsContextualBuild && isset($this->sharedValues[$id])) {
            return $this->sharedValues[$id];
        }

        // 原值、已经计算过的
        if (isset($this->raw[$id])
            || (!$needsContextualBuild && is_object($this->items[$id]) && !$this->items[$id] instanceof \Closure
            && !isset($this->factories[$id]))) {
            return $this->items[$id];
        }
        // return by factory
        if (isset($this->factories[$id])) {
            return $this->items[$id]($this);
        }
        // abstract maps to concrete
        $concrete = $this->getConcrete($id);
        // If the concrete type is actually a Closure, we will just execute it and
        // hand back the results of the functions, which allows functions to be
        // used as resolvers for more fine-tuned resolution of these objects.
        if ($concrete instanceof \Closure) {
            // $concrete的第一个参数是container，其他的参数是依次位置
            $args = array_merge([$this], $parameters);
            $object = call_user_func_array($concrete, static::getMethodDependencies($this, $concrete, $args));
        } else {
            // We're ready to instantiate an instance of the concrete type registered for
            // the binding. This will instantiate the types, as well as resolve any of
            // its "nested" dependencies recursively until all have gotten resolved.
            if ($concrete === $id) {
                $object = $this->build($concrete, $parameters);
            } else {
                $object = $this->make($concrete, $parameters);
            }
        }

        // If the requested type is registered as a singleton we'll want to cache off
        // the instances in "memory" so we can return it later without creating an
        // entirely new instance of an object on each subsequent request for it.
        if (!$needsContextualBuild && is_object($object)) {
            $this->sharedValues[$id] = $object;
            $this->frozen[$id] = true;
        }

        return $object;
    }

    /**
     * Call the given \Closure / class@method and inject its dependencies.
     *
     * @param callable|string $callback
     * @param array           $parameters
     * @param string|null     $defaultMethod
     *
     * @return mixed
     */
    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        if (!is_callable($callback) && !is_string($callback)) {
            throw new \InvalidArgumentException(i18n('"callback" needs callable.'));
        }

        if (!is_callable($callback) && is_string($callback)) {
            return static::callClass($this, $callback, $parameters, $defaultMethod);
        }

        return call_user_func_array(
            $callback,
            static::getMethodDependencies($this, $callback, $parameters)
        );
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param array $dependencies
     *
     * @return array
     */
    protected function resolveDependencies(array $dependencies, array $parameters = [])
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            // If this dependency has a override for this particular build we will use
            // that instead as the value. Otherwise, we will continue with this run
            // of resolutions and let reflection attempt to determine the result.
            // 从传入的参数获取值
            if (array_key_exists($dependency->name, $parameters)) {
                $results[] = $parameters[$dependency->name];
                continue;
            }
            // If the class is null, it means the dependency is a string or some other
            // primitive type which we can not resolve since it is not a class and
            // we will just bomb out with an error since we have no-where to go.
            $results[] = is_null($dependency->getClass())
                ? $this->resolvePrimitive($dependency)
                : $this->resolveClass($dependency);
        }

        return $results;
    }

    /**
     * Get the contextual concrete binding for the given abstract.
     *
     * @param string $id
     *
     * @return string|null
     */
    protected function getContextualConcrete($id)
    {
        // 自个被指定上下文实现
        if (!is_null($binding = $this->findInContextualBindings($id))) {
            return $binding;
        }

        // Next we need to see if a contextual binding might be bound under an alias of the
        // given abstract type. So, we will need to check if any aliases exist with this
        // type and then spin through them and check for contextual bindings on these.
        if (empty($this->actualAliases[$id])) {
            return;
        }
        // 通过别名指定上下文
        foreach ($this->actualAliases[$id] as $alias) {
            if (!is_null($binding = $this->findInContextualBindings($alias))) {
                return $binding;
            }
        }
    }

    /**
     * Find the concrete binding for the given abstract in the contextual binding array.
     *
     * @param string $id
     *
     * @return string|null
     */
    protected function findInContextualBindings($id)
    {
        if (isset($this->contextual[end($this->buildStack)][$id])) {
            return $this->contextual[end($this->buildStack)][$id];
        }
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param string $id
     *
     * @return mixed $concrete
     */
    protected function getConcrete($id)
    {
        // 根据上下文，特定指定的实例
        if (!is_null($concrete = $this->getContextualConcrete($id))) {
            return $concrete;
        }

        // If we don't have a registered resolver or concrete for the type, we'll just
        // assume each type is a concrete name and will attempt to resolve it as is
        // since the container should be able to resolve concretes automatically.
        if (isset($this->items[$id])) {
            return $this->items[$id];
        }

        return $id;
    }

    /**
     * Resolve a non-class hinted primitive dependency.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function resolvePrimitive(\ReflectionParameter $parameter)
    {
        if (!is_null($concrete = $this->getContextualConcrete('$' . $parameter->name))) {
            return $concrete instanceof \Closure ? $concrete($this) : $concrete;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $message = "Unresolvable dependency resolving [%s] in class %s";

        throw new \RuntimeException(i18n($message, $parameter->name, $parameter->getDeclaringClass()->getName()));
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function resolveClass(\ReflectionParameter $parameter)
    {
        try {
            return $this->resolve($parameter->getClass()->name);
        } catch (\Exception $e) {
            // If we can not resolve the class instance, we will check to see if the value
            // is optional, and if it is we will return the optional parameter value as
            // the value of the dependency, similarly to how we do this with scalars. 
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }
            
            throw $e;
        }
    }

    /**
     * Add a contextual binding to the container.
     *
     * @param string          $concrete
     * @param string          $needs
     * @param \Closure|string $implementation
     */
    public function when($concrete, $needs, $implementation)
    {
        $this->contextual[$concrete][$this->getActual($needs)] = $implementation;
    }

    /**
     * Alias a type to a different name.
     *
     * @param string $concrete
     * @param string $alias
     */
    public function alias($concrete, $alias)
    {
        $this->aliasMaps[$alias] = $concrete;
        $this->actualAliases[$concrete][] = $alias;
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param PsrContainerInterface $container
     * @param string                            $callback
     * @param array                             $parameters
     * @param string|null                       $defaultMethod
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected static function callClass(PsrContainerInterface $container, $callback, array $parameters = [], $defaultMethod = null)
    {
        $segments = explode('::', $callback);

        // We will assume an @ sign is used to delimit the class name from the method
        // name. We will split on this @ sign and then build a callable array that
        // we can pass right back into the "call" method for dependency binding.
        $method = count($segments) == 2 ? $segments[1] : $defaultMethod;

        $callable = [$container->make($segments[0]), $method];

        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(i18n('Method %s invalid.', $callback));
        }

        return call_user_func_array(
            $callable,
            static::getMethodDependencies($container, $callable, $parameters)
        );
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param PsrContainerInterface $container
     * @param callable|string                   $callback
     * @param array                             $parameters
     *
     * @return array
     */
    public static function getMethodDependencies(
        PsrContainerInterface $container, 
        $callback, 
        array $parameters = []
    ) {
        $dependencies = [];

        foreach (static::getCallReflector($callback)->getParameters() as $key => $parameter) {
            static::addDependencyForCallParameter($container, $key, $parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param callable|string $callback
     *
     * @return \ReflectionFunctionAbstract
     */
    protected static function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        }

        return is_array($callback)
            ? new \ReflectionMethod($callback[0], $callback[1])
            : new \ReflectionFunction($callback);
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param PsrContainerInterface $container
     * @param int                           $i            参数位置
     * @param \ReflectionParameter          $parameter
     * @param array                         $parameters
     * @param array                         $dependencies
     *
     * @return mixed
     */
    protected static function addDependencyForCallParameter(
        PsrContainerInterface $container,
        $position,
        $parameter,
        array &$parameters,
        &$dependencies
    ) {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];
            unset($parameters[$parameter->name]);
        } elseif ($parameter->getClass()) {
            $dependencies[] = $container->make($parameter->getClass()->name);
        } elseif (isset($parameters[$position])) {
            $dependencies[] = $parameters[$position];
            unset($parameters[$position]);
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }
}
