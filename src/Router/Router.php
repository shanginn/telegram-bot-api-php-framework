<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Exception\RouteException;
use Phenogram\Framework\Handler\CallableHandler;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\Middleware\CallableMiddleware;
use Phenogram\Framework\Middleware\MiddlewareInterface;
use Phenogram\Framework\Trait\ContainerTrait;
use Psr\Container\ContainerExceptionInterface;

final class Router
{
    use ContainerTrait;

    private RouteCollection $collection;

    public function __construct()
    {
        $this->collection = new RouteCollection();
    }

    /**
     * @return \Generator<UpdateHandlerInterface>
     */
    public function supportedHandlers(Update $update): \Generator
    {
        foreach ($this->collection->all() as $route) {
            if ($route->supports($update)) {
                yield $route->getHandler();
            }
        }
    }

    public function add(): RouteConfigurator
    {
        return new RouteConfigurator($this);
    }

    public function register(RouteInterface $route): self
    {
        $this->collection->add($route);

        return $this;
    }

    public function configureRoute(RouteConfigurator $routeConfig): void
    {
        if ($routeConfig->target === null) {
            throw new RouteException(\sprintf('This route has no defined target. Call one of: `callable`, `handler` methods.'));
        }

        $handler = $this->getHandler($routeConfig->target);

        $middlewares = array_map(
            fn (string|MiddlewareInterface $middleware) => $this->getMiddleware($middleware),
            $routeConfig->middleware ?? []
        );

        $this->register(
            new BasicRoute(
                handler: $handler,
                middlewares: $middlewares,
                condition: $routeConfig->condition
            ),
        );
    }

    private function getMiddleware(string|callable|MiddlewareInterface $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (\is_callable($middleware)) {
            return new CallableMiddleware($middleware);
        }

        if (\is_string($middleware)) {
            if (!$this->hasContainer()) {
                throw new RouteException('Unable to configure route pipeline without associated container');
            }

            return $this->container->get($middleware);
        }

        $name = get_debug_type($middleware);
        throw new RouteException(\sprintf('Invalid middleware `%s`', $name));
    }

    private function getHandler(string|callable|UpdateHandlerInterface $target): UpdateHandlerInterface
    {
        if ($target instanceof UpdateHandlerInterface) {
            return $target;
        }

        if (\is_callable($target)) {
            return new CallableHandler($target);
        }

        try {
            if (!$this->hasContainer()) {
                throw new RouteException('Unable to configure route pipeline without associated container');
            }

            return $this->container->get($target);
        } catch (ContainerExceptionInterface $e) {
            throw new RouteException('Invalid target resolution', $e->getCode(), $e);
        }
    }
}
