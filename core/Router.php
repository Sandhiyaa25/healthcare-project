<?php

namespace Core;

use App\Exceptions\ValidationException;

class Router
{
    private array $routes  = [];
    private array $groups  = [];

    // ─── Register routes ────────────────────────────────────────────

    public function get(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $handler, $middleware);
    }

    public function post(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $uri, $handler, $middleware);
    }

    public function put(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $uri, $handler, $middleware);
    }

    public function patch(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $uri, $handler, $middleware);
    }

    public function delete(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $uri, $handler, $middleware);
    }

    private function addRoute(string $method, string $uri, array|callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'uri'        => $this->normalizeUri($uri),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    private function normalizeUri(string $uri): string
    {
        return '/' . trim($uri, '/');
    }

    // ─── Dispatch ───────────────────────────────────────────────────

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri    = $request->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchUri($route['uri'], $uri);

            if ($params === false) {
                continue;
            }

            $request->setParams($params);

            // Run middleware pipeline
            $pipeline = new MiddlewarePipeline($request, $route['middleware']);
            $pipeline->run();

            // Support closure handlers (e.g. health check)
            if (is_callable($route['handler'])) {
                ($route['handler'])($request);
                return;
            }

            // Call controller
            [$controllerClass, $action] = $route['handler'];

            if (!class_exists($controllerClass)) {
                Response::serverError("Controller {$controllerClass} not found");
                return;
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $action)) {
                Response::serverError("Method {$action} not found in {$controllerClass}");
                return;
            }

            $controller->$action($request);
            return;
        }

        // No route matched
        Response::notFound('Route not found');
    }

    private function matchUri(string $routeUri, string $requestUri): array|false
    {
        // Convert route pattern to regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestUri, $matches)) {
            // Return only named captures
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }
}