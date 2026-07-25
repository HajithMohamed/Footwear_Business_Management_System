<?php

namespace App\Core;

/**
 * Minimal but capable router: named path params ({id}), per-route middleware,
 * automatic CSRF verification on state-changing requests.
 */
class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:array,handler:mixed,middleware:array}> */
    private array $routes = [];

    /** Middleware key => class name */
    private array $middlewareMap = [
        'auth'  => \App\Middleware\AuthMiddleware::class,
        'guest' => \App\Middleware\GuestMiddleware::class,
        'admin' => \App\Middleware\AdminMiddleware::class,
    ];

    public function get(string $path, $handler, array $middleware = []): void    { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, $handler, array $middleware = []): void   { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, $handler, array $middleware = []): void    { $this->add('PUT', $path, $handler, $middleware); }
    public function delete(string $path, $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    private function add(string $method, string $path, $handler, array $middleware): void
    {
        $path   = '/' . trim($path, '/');
        $params = [];
        $regex  = preg_replace_callback('#\{(\w+)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);
        $regex = '#^' . ($regex === '/' ? '/?' : $regex) . '$#';

        $this->routes[] = compact('method', 'path', 'regex', 'params', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            // Bind path params
            array_shift($matches);
            $params = array_combine($route['params'], $matches) ?: [];

            // CSRF for state-changing verbs
            if ($request->isWrite() && !Session::verifyCsrf($request->input('_token'))) {
                http_response_code(419);
                Session::flash('error', 'Your session expired. Please try again.');
                redirect(Auth::check() ? '' : 'login');
            }

            // Middleware chain
            foreach ($route['middleware'] as $key) {
                $class = $this->middlewareMap[$key] ?? null;
                if ($class) {
                    (new $class())->handle($request);
                }
            }

            $this->runHandler($route['handler'], $request, $params);
            return;
        }

        http_response_code(404);
        View::render('errors/404', [], 'auth');
    }

    private function runHandler($handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            $handler($request, $params);
            return;
        }

        // "ControllerName@method"
        [$controller, $action] = explode('@', $handler);
        $class = "App\\Controllers\\$controller";
        $instance = new $class();
        $instance->$action($request, $params);
    }
}
