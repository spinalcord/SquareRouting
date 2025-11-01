<?php

namespace SquareRouting\Core;

use SquareRouting\Core\Interfaces\RuleInterface;
use SquareRouting\Core\Validation\Rules\PathRouteRule;
use Throwable;

/**
 * Modified Route class to support path parameters with slashes
 */
class Route
{
    private array $routes = [];
    private Request $request;
    private DependencyContainer $container;
    private Language $language;

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
        $this->language = $container->get(Language::class);
        $this->container = $container;
    }

    public function getRoutesStrArray(): array
    {
        return array_column($this->routes, 'path');
    }
    /**
     * @param array<int,mixed> $paramPatterns
     */
    public function get(string $path, string $controller, string $action, array $paramPatterns = []): self
    {
        $route = [
            'method' => 'GET',
            'path' => $this->normalizePath($path),
            'controller' => $controller,
            'action' => $action,
            'params' => $this->compileParamPatterns($paramPatterns),
            'filters' => [],
        ];
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @param  array<int,mixed>  $paramPatterns
     */
    public function post(string $path, string $controller, string $action, array $paramPatterns = []): self
    {
        $route = [
            'method' => 'POST',
            'path' => $this->normalizePath($path),
            'controller' => $controller,
            'action' => $action,
            'params' => $this->compileParamPatterns($paramPatterns),
            'filters' => [],
        ];
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @param  array<int,mixed>  $paramPatterns
     */
    public function put(string $path, string $controller, string $action, array $paramPatterns = []): self
    {
        $route = [
            'method' => 'PUT',
            'path' => $this->normalizePath($path),
            'controller' => $controller,
            'action' => $action,
            'params' => $this->compileParamPatterns($paramPatterns),
            'filters' => [],
        ];
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @param  array<int,mixed>  $paramPatterns
     */
    public function delete(string $path, string $controller, string $action, array $paramPatterns = []): self
    {
        $route = [
            'method' => 'DELETE',
            'path' => $this->normalizePath($path),
            'controller' => $controller,
            'action' => $action,
            'params' => $this->compileParamPatterns($paramPatterns),
            'filters' => [],
        ];
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @param  array<int,mixed>  $paramPatterns
     */
    public function patch(string $path, string $controller, string $action, array $paramPatterns = []): self
    {
        $route = [
            'method' => 'PATCH',
            'path' => $this->normalizePath($path),
            'controller' => $controller,
            'action' => $action,
            'params' => $this->compileParamPatterns($paramPatterns),
            'filters' => [],
        ];
        $this->routes[] = $route;

        return $this;
    }

    public function reroute(string $from, string $to): void
    {
        $this->routes[] = [
            'method' => 'REROUTE',
            'path' => $this->normalizePath($from),
            'target' => $this->normalizePath($to),
        ];
    }

    /**
     * @param  array<int,mixed>  $filters
     */
    public function filter(array $filters): self
    {
        $lastKey = array_key_last($this->routes);
        if ($lastKey !== null && isset($this->routes[$lastKey]['filters'])) {
            $this->routes[$lastKey]['filters'] = $filters;
        }

        return $this;
    }

    public function dispatch(): bool
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestPath = $this->extractAndSetLanguageFromPath($requestUri);
        $requestPath = $this->normalizePath($requestPath);

        foreach ($this->routes as $route) {
            if ($route['method'] === 'REROUTE' && $route['path'] === $requestPath) {
                header('Location: ' . $route['target']);
                exit;
            }
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            // Check if route has PathRouteRule validator
            $hasPathValidator = false;
            foreach ($route['params'] as $paramName => $validators) {
                if (is_array($validators)) {
                    foreach ($validators as $validator) {
                        if ($validator instanceof PathRouteRule) {
                            $hasPathValidator = true;
                            break 2;
                        }
                    }
                }
            }

            if ($hasPathValidator) {
                $patternResult = $this->matchPathParameter($route, $requestPath);
                if ($patternResult) {
                    [$matches, $params] = $patternResult;

                    $this->executeRoute($route, $params);

                    return true;
                }
            } else {
                $matchResult = $this->matchRouteWithValidators($route, $requestPath);
                if ($matchResult) {
                    [$matches, $params] = $matchResult;

                    $this->executeRoute($route, $params);

                    return true;
                }
            }
        }

        // header("HTTP/1.0 404 Not Found");
        // echo "404 Not Found";
        return false;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param  array<int,mixed>  $route
     */
    public function addRouteDefinition(array $route): void
    {
        $this->routes[] = $route;
    }

    private function normalizePath(string $path): string
    {
        $segments = array_filter(explode('/', $path), fn ($segment) => trim($segment) !== '');

        return '/' . implode('/', $segments);
    }

    /**
     * Extracts and sets the language from the request URI.
     *
     * @param  string  $requestUri  The raw request URI.
     * @return string The request URI with the language segment removed.
     */
    private function extractAndSetLanguageFromPath(string $requestUri): string
    {
        $segments = array_filter(explode('/', $requestUri), fn ($segment) => trim($segment) !== '');

        if (empty($segments)) {
            return '/';
        }

        $availableLanguages = $this->language->getAvailableLanguages();
        $languageSegment = strtolower(array_shift($segments));

        if (count($segments) === 0 && in_array($languageSegment, array_map('strtolower', $availableLanguages))) {
            $this->language->setLanguage($languageSegment, true);
        } elseif (in_array($languageSegment, array_map('strtolower', $availableLanguages))) {
            $this->language->setLanguage($languageSegment);
        } else {
            array_unshift($segments, $languageSegment);
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @param  array<int,mixed>  $paramPatterns
     */
    private function compileParamPatterns(array $paramPatterns): array
    {
        $compiled = [];
        foreach ($paramPatterns as $param => $patternOrValidator) {
            if ($patternOrValidator instanceof RuleInterface) {
                // Handle validator objects
                $compiled[$param] = $patternOrValidator;
            } elseif (is_array($patternOrValidator) && isset($patternOrValidator[0]) && $patternOrValidator[0] instanceof RuleInterface) {
                // Handle array of validators
                $compiled[$param] = $patternOrValidator;
            } else {
                // Handle legacy pattern strings
                $compiled[$param] = $this->patterns[$patternOrValidator] ?? $patternOrValidator;
            }
        }

        return $compiled;
    }

    /**
     * Match a route with a path parameter
     *
     * @param  array  $route  Route definition
     * @param  string  $requestPath  Requested path
     * @return array|null Match result or null if no match
     */
    private function matchPathParameter(array $route, string $requestPath): ?array
    {
        // Find the parameter with PathRouteRule validator
        $pathParamName = null;
        foreach ($route['params'] as $paramName => $validators) {
            if (is_array($validators)) {
                foreach ($validators as $validator) {
                    if ($validator instanceof PathRouteRule) {
                        $pathParamName = $paramName;
                        break 2;
                    }
                }
            }
        }

        if ($pathParamName === null) {
            return null; // No PathRouteRule found
        }

        // Build the route pattern to extract prefix
        $routePattern = str_replace(':' . $pathParamName, ':path_placeholder', $route['path']);
        $parts = explode('/:path_placeholder', $routePattern);
        $prefix = $parts[0];

        // Check if the request path starts with the prefix
        if (strpos($requestPath, $prefix) !== 0) {
            return null;
        }

        // Extract the path parameter value
        $pathValue = substr($requestPath, strlen($prefix) + 1);

        // If there's more to the route after the path parameter, check that too
        if (isset($parts[1]) && $parts[1] !== '') {
            // Not handling complex patterns after path parameter for simplicity
            return null;
        }

        // Validate the extracted path value with the PathRouteRule
        $pathValidator = new PathRouteRule();
        if (!$pathValidator->validate($pathParamName, $pathValue, [])) {
            return null; // Validation failed
        }

        $matches = [$pathParamName => $pathValue];
        $params = [$pathValue]; // Numerical array for controller parameters

        return [$matches, $params];
    }

    /**
     * Match route using validators instead of regex patterns
     *
     * @param  array  $route  Route definition
     * @param  string  $requestPath  Requested path
     * @return array|null Match result or null if no match
     */
    private function matchRouteWithValidators(array $route, string $requestPath): ?array
    {
        $routeSegments = explode('/', ltrim($route['path'], '/'));
        $requestSegments = explode('/', ltrim($requestPath, '/'));

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];
        $matches = [];

        foreach ($routeSegments as $index => $segment) {
            if (str_starts_with($segment, ':')) {
                $paramName = substr($segment, 1);
                $value = urldecode($requestSegments[$index]);
                $validator = $route['params'][$paramName] ?? null;

                // Validate with validator if available
                if ($validator instanceof RuleInterface) {
                    if (!$validator->validate($paramName, $value, [])) {
                        return null; // Validation failed
                    }
                } elseif (is_array($validator) && isset($validator[0]) && $validator[0] instanceof RuleInterface) {
                    // Check all validators in the array
                    foreach ($validator as $v) {
                        if (!$v->validate($paramName, $value, [])) {
                            return null; // Validation failed
                        }
                    }
                }

                $params[] = $value;  // Numerisches Array für call_user_func_array
                $matches[$paramName] = $value;
            } elseif ($segment !== $requestSegments[$index]) {
                return null; // Static segment doesn't match
            }
        }

        return [$matches, $params];
    }

    /**
     * Execute a matched route
     *
     * @param  array  $route  Route definition
     * @param  array  $params  Route parameters
     */
    private function executeRoute(array $route, array $params): void
    {
        $this->container->set("routePath", fn() => $_SERVER['REQUEST_URI']);

        // //////////////////////////////////////////////
        // 'before' filters
        foreach ($route['filters'] as $filterClass) {
            // Überprüfen, ob die Klasse existiert, um Fehler zu vermeiden
            if (class_exists($filterClass)) {
                // Erstelle eine neue Instanz der Filter-Klasse
                $filterInstance = new $filterClass;

                // Rufe die 'before'-Methode auf der Instanz auf
                if (method_exists($filterInstance, 'before')) {
                    $filterInstance->before($this->container);
                }
            }
        }
        // //////////////////////////////////////////////
        try {
            // Controller instance
            $controller = new $route['controller'](
                $this->container // Argument
            );

            /* if (!$controller instanceof BaseController) { */
            /*     throw new \Exception('Controller class '.get_class($controller).' does not extend ' . BaseController::class); */
            /* } */

            // Call controller action and save the return value
            $response = call_user_func_array([$controller, $route['action']], $params);

            // Process the return value
            if ($response instanceof Response) {
                $response->send(); // Sende die Antwort an den Client
            } elseif (is_string($response)) {
                // Fallback: If only a string is returned, send as HTML.
                (new Response)->html($response)->send();
            } elseif (is_array($response)) {
                // Fallback: If an array is returned, send as JSON.
                (new Response)->json($response)->send();
            }
            // If nothing is returned (null), nothing happens.
            // This is useful if the controller, for example, only starts a download.

            // //////////////////////////////////////////////
            // 'after' filters (can be executed now!)
            foreach ($route['filters'] as $filterClass) {
                // Überprüfen, ob die Klasse existiert
                if (class_exists($filterClass)) {
                    // Erstelle eine neue Instanz der Filter-Klasse
                    $filterInstance = new $filterClass;

                    if (method_exists($filterInstance, 'after')) {
                        // Optional: One could pass the $response here to the filter,
                        // so that it can still modify the response.
                        // $filterInstance->after($this->request, $response);
                        $filterInstance->after($this->container);
                    }
                }
            }
            // //////////////////////////////////////////////
        } catch (Throwable $e) {
            // Error handling: In case of an exception, output a clean 500 error page
            error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            // Create a clean JSON error response
            $errorResponse = new Response;
            $errorResponse->error('Internal Server Error', 500)->send();
        }
    }
}
