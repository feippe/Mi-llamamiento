<?php

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $path = Request::path();
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod !== $method || !preg_match($regex, $path, $matches)) {
                continue;
            }
            array_shift($matches);
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action(...$matches);
            return;
        }
        Response::error('NOT_FOUND', 'Ruta no encontrada.', 404);
    }
}
