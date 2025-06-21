<?php

namespace SquareRouting\Core;


/**
 * Modified Route class to support path parameters with slashes
 */
class Route
{
    private array $routes = [];
    private array $patterns = [
        'num'   => '[0-9]+',
        'alnum' => '[A-Za-z0-9]+',
        'alpha' => '[A-Za-z]+',
        'any'   => '[^/]+',
        'slug'  => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'date'  => '[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])',
        'year' => '[0-9]{4}',
        'month' => '(?:0[1-9]|1[0-2])',
        'day' => '(?:0[1-9]|[12][0-9]|3[01])',
        'bool' => '(?:true|false|1|0|yes|no)',
        'hexcolor' => '#?(?:[0-9a-fA-F]{3}){1,2}',
        'all' => '.*',
        'path' => '.*' // New pattern for path parameters that can include slashes
    ];
    private Request $request;
    private DependencyContainer $container;

    function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
        $this->container = $container;
    }

    public function getRoutesStrArray(): array
    {
        return array_column($this->routes, 'path');
    }

    public function addPattern(string $name, string $pattern): void {
        $this->patterns[$name] = $pattern;
    }

    public function get(string $path, string $controller, string $action, array $paramPatterns = []): self {
        $route = [
            'method'     => 'GET',
            'path'       => $this->normalizePath($path),
            'controller' => $controller,
            'action'     => $action,
            'params'     => $this->compileParamPatterns($paramPatterns),
            'filters'    => []
        ];
        $this->routes[] = $route;
        return $this;
    }
    /**
     * @param array<int,mixed> $paramPatterns
     */
    public function post(string $path, string $controller, string $action, array $paramPatterns = []): self {
        $route = [
            'method'     => 'POST',
            'path'       => $this->normalizePath($path),
            'controller' => $controller,
            'action'     => $action,
            'params'     => $this->compileParamPatterns($paramPatterns),
            'filters'    => []
        ];
        $this->routes[] = $route;
        return $this;
    }

    public function reroute(string $from, string $to): void {
        $this->routes[] = [
            'method' => 'REROUTE',
            'path'   => $this->normalizePath($from),
            'target' => $this->normalizePath($to)
        ];
    }
    /**
     * @param array<int,mixed> $filters
     */
    public function filter(array $filters): self {
        $lastKey = array_key_last($this->routes);
        if ($lastKey !== null && isset($this->routes[$lastKey]['filters'])) {
            $this->routes[$lastKey]['filters'] = $filters;
        }
        return $this;
    }

    private function normalizePath(string $path): string {
        $segments = array_filter(explode('/', $path), fn($segment) => trim($segment) !== '');
        return '/' . implode('/', $segments);
    }
    /**
     * @param array<int,mixed> $paramPatterns
     */
    private function compileParamPatterns(array $paramPatterns): array {
        $compiled = [];
        foreach ($paramPatterns as $param => $patternKey) {
            $compiled[$param] = $this->patterns[$patternKey] ?? $patternKey;
        }
        return $compiled;
    }

    public function dispatch(): void {
        $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestPath   = $this->normalizePath($requestUri);

        foreach ($this->routes as $route) {
            if ($route['method'] === 'REROUTE' && $route['path'] === $requestPath) {
                header("Location: " . $route['target']);
                exit;
            }
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            // Check for path parameters
            if (strpos($route['path'], ':path') !== false) {
                $patternResult = $this->matchPathParameter($route, $requestPath);
                if ($patternResult) {
                    list($matches, $params) = $patternResult;
                    $this->executeRoute($route, $params);
                    return;
                }
            } else {
                $pattern = $this->buildRoutePattern($route['path'], $route['params']);
                if (preg_match($pattern, $requestPath, $matches)) {
                    $params = [];
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = ($route['params'][$key] ?? '[^/]+') === $this->patterns['any'] ? urldecode($value) : $value;
                        }
                    }

                    $this->executeRoute($route, $params);
                    return;
                }
            }
        }
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }

    /**
     * Match a route with a path parameter
     *
     * @param array $route Route definition
     * @param string $requestPath Requested path
     * @return array|null Match result or null if no match
     */
    private function matchPathParameter(array $route, string $requestPath): ?array
    {
        // Extract the part before :path
        $parts = explode('/:path', $route['path']);
        $prefix = $parts[0];

        // Check if the request path starts with the prefix
        if (strpos($requestPath, $prefix) !== 0) {
            return null;
        }

        // Extract the path parameter value
        $pathValue = substr($requestPath, strlen($prefix) + 1);

        // If there's more to the route after :path, check that too
        if (isset($parts[1]) && $parts[1] !== '') {
            // Not handling complex patterns after :path for simplicity
            return null;
        }

        $matches = ['path' => $pathValue];
        $params = ['path' => $pathValue];

        return [$matches, $params];
    }

    /**
     * Execute a matched route
     *
     * @param array $route Route definition
     * @param array $params Route parameters
     */
private function executeRoute(array $route, array $params): void
    {
        // 'before' filters
        foreach ($route['filters'] as $filter) {
            if (method_exists($filter, 'before')) {
                $filter->before($this->request);
            }
        }

        try {
            // Controller instance 
            $controller = new $route['controller'](
                $this->container // Argument
            );

            /* if (!$controller instanceof BaseController) { */
            /*     throw new \Exception('Controller class '.get_class($controller).' does not extend ' . BaseController::class); */
            /* } */

            // Controller-Aktion aufrufen und den Rückgabewert speichern
            $response = call_user_func_array([$controller, $route['action']], $params);

            // 'after' filters (können jetzt ausgeführt werden!)
            foreach ($route['filters'] as $filter) {
                if (method_exists($filter, 'after')) {
                    // Optional: Man könnte die $response hier an den Filter übergeben,
                    // damit dieser die Antwort noch modifizieren kann.
                    // $filter->after($this->request, $response);
                    $filter->after($this->request);
                }
            }
            
            // Verarbeite den Rückgabewert
            if ($response instanceof Response) {
                $response->send(); // Sende die Antwort an den Client
            } elseif (is_string($response)) {
                // Fallback: Wenn nur ein String zurückgegeben wird, als HTML senden.
                (new Response())->html($response)->send();
            } elseif (is_array($response)) {
                // Fallback: Wenn ein Array zurückgegeben wird, als JSON senden.
                (new Response())->json($response)->send();
            }
            // Wenn nichts zurückgegeben wird (null), passiert einfach nichts.
            // Das ist nützlich, wenn der Controller z.B. nur einen Download startet.
            
        } catch (\Throwable $e) {
            // Fehlerbehandlung: Bei einer Exception eine saubere 500er-Fehlerseite ausgeben
            error_log($e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Erstelle eine saubere JSON-Fehlerantwort
            $errorResponse = new Response();
            $errorResponse->error('Internal Server Error', 500)->send();
        }
    }
    /**
     * @param array<int,mixed> $paramPatterns
     */
    private function buildRoutePattern(string $path, array $paramPatterns): string {
        $segments     = explode('/', ltrim($path, '/'));
        $regexSegments = [];
        foreach ($segments as $segment) {
            if (str_starts_with($segment, ':')) {
                $paramName = substr($segment, 1);
                $pattern   = $paramPatterns[$paramName] ?? '[^/]+';
                $regexSegments[] = "(?P<$paramName>$pattern)";
            } else {
                $regexSegments[] = preg_quote($segment, '/');
            }
        }

        return '#^/' . implode('/', $regexSegments) . '$#';
    }

    public function getRoutes(): array {
        return $this->routes;
    }
    /**
     * @param array<int,mixed> $route
     */
    public function addRouteDefinition(array $route): void {
        $this->routes[] = $route;
    }
}
