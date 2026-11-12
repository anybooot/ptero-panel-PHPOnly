<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Fast, regex-capable Router supporting parameters, middleware pipelines, and route groups.
 */
class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute(['GET', 'HEAD'], $path, $handler, $middleware);
    }

    public function post(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute(['POST'], $path, $handler, $middleware);
    }

    public function put(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute(['PUT'], $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute(['PATCH'], $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute(['DELETE'], $path, $handler, $middleware);
    }

    public function any(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], $path, $handler, $middleware);
    }

    private function addRoute(array $methods, string $path, callable|array|string $handler, array $middleware): self
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $groupMiddleware = array_merge($groupMiddleware, $m);
            }
        }

        $fullPath = rtrim($prefix, '/') . '/' . ltrim($path, '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        // Convert path like /servers/{uuid} to regex
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(\??)\}/', function ($matches) {
            $isOptional = !empty($matches[2]);
            return $isOptional ? '(?P<' . $matches[1] . '>[^/]+)?' : '(?P<' . $matches[1] . '>[^/]+)';
        }, $fullPath);

        // Also support :param style
        $pattern = preg_replace('/\:([a-zA-Z0-9_]+)/', '(?P<$1>[^/]+)', $pattern);

        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'methods' => $methods,
            'path' => $fullPath,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $methodNotAllowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if (!in_array($method, $route['methods'], true)) {
                $methodNotAllowed = true;
                continue;
            }

            // Extract named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                    $request->setAttribute($key, $value);
                }
            }

            // Execute middleware pipeline
            $pipeline = $route['middleware'];
            $index = 0;

            $runner = function (Request $req) use (&$runner, &$pipeline, &$index, $route, $params): Response {
                if ($index < count($pipeline)) {
                    $mw = $pipeline[$index++];
                    if (is_callable($mw)) {
                        return $mw($req, $runner);
                    }
                    if (is_string($mw) && class_exists($mw)) {
                        $instance = new $mw();
                        return $instance->handle($req, $runner);
                    }
                }

                // Execute final handler
                $handler = $route['handler'];
                if (is_callable($handler)) {
                    $res = $handler($req, ...array_values($params));
                } elseif (is_array($handler)) {
                    [$class, $action] = $handler;
                    $instance = is_string($class) ? new $class() : $class;
                    $res = $instance->$action($req, ...array_values($params));
                } elseif (is_string($handler) && class_exists($handler)) {
                    $instance = new $handler();
                    $res = $instance($req, ...array_values($params));
                } else {
                    throw new \RuntimeException('Invalid route handler specified.');
                }

                if ($res instanceof Response) {
                    return $res;
                }
                if (is_array($res) || is_object($res)) {
                    return Response::json($res);
                }
                return Response::html((string)$res);
            };

            return $runner($request);
        }

        if ($methodNotAllowed) {
            if ($request->isJson() || str_starts_with($path, '/api/')) {
                return Response::json(['error' => 'Method Not Allowed'], 405);
            }
            return Response::html(ErrorRenderer::render(405, 'The requested HTTP method is not allowed for this route.', 'Method Not Allowed'), 405);
        }

        if ($request->isJson() || str_starts_with($path, '/api/')) {
            return Response::json(['error' => 'Resource Not Found'], 404);
        }

        return Response::html(ErrorRenderer::render(404, 'The requested page or route could not be found.', 'Page Not Found'), 404);
    }
}
