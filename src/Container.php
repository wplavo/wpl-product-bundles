<?php

declare(strict_types=1);

namespace WPL\ProductBundles;

use RuntimeException;

/**
 * Minimal dependency-injection container.
 *
 * Supports transient bindings (bind) and lazily-initialized singletons (singleton).
 * Intentionally lightweight — no reflection, no autowiring, no external library.
 */
final class Container {

    /** @var array<string, callable(Container): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Registers a transient factory. A new instance is created on every make() call.
     *
     * @param string                        $abstract Identifier (typically FQCN).
     * @param callable(Container): mixed    $factory  Factory callable.
     */
    public function bind(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Registers a singleton factory. The instance is created once and cached.
     *
     * @param string                        $abstract Identifier (typically FQCN).
     * @param callable(Container): mixed    $factory  Factory callable.
     */
    public function singleton(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = function (Container $container) use ($abstract, $factory): mixed {
            if (!array_key_exists($abstract, $this->instances)) {
                $this->instances[$abstract] = $factory($container);
            }
            return $this->instances[$abstract];
        };
    }

    /**
     * Resolves an abstract to a concrete instance.
     *
     * @param string $abstract Identifier.
     * @return mixed
     * @throws RuntimeException When no binding exists and the class cannot be instantiated.
     */
    public function make(string $abstract): mixed {
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new RuntimeException(
            sprintf('No binding found for abstract "%s".', $abstract)
        );
    }

    /**
     * Returns true when the abstract has a binding or is an instantiable class.
     */
    public function has(string $abstract): bool {
        return isset($this->bindings[$abstract]) || class_exists($abstract);
    }
}
