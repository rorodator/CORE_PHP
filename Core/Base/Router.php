<?php
namespace Core\Base;

/**
 * Class Router
 *
 * Handles HTTP request routing for both API endpoints and static content.
 * Supports pattern-based routing for REST services and fallback to static files.
 */
class Router
{
    /**
     * Maximum accepted length for a resolved request path.
     */
    const MAX_PATH_LENGTH = 1024;

    /**
     * File extensions that must never be served directly via readfile().
     * Defense-in-depth: prevents leaking server-side source / config if such a file
     * happens to exist under the configured WWW root.
     *
     * @var string[]
     */
    protected static $forbiddenExtensions = [
        'php', 'phtml', 'phar', 'phps', 'pl', 'py', 'rb', 'sh', 'cgi',
        'env', 'ini', 'htaccess', 'htpasswd', 'sqlite', 'sql',
    ];

    /**
     * Array of route patterns mapped to service classes
     * @var array
     */
    protected $routes = [];

    /**
     * Array of CORE route patterns mapped to service classes (auto-registered)
     * @var array
     */
    protected $coreRoutes = [];

    /**
     * Initialize router with optional route definitions
     *
     * @param array $routes Route patterns mapped to service classes
     */
    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
        $this->registerCoreRoutes();
    }

    /**
     * Auto-register CORE routes based on activated services in configuration
     * This ensures that CORE services are automatically available to all applications
     */
    private function registerCoreRoutes()
    {
        try {
            // Get services configuration
            $services = core()->getConfigSection('services');
            
            // Register language service routes if lang service is activated
            if (isset($services['lang'])) {
                $this->coreRoutes = array_merge($this->coreRoutes, [
                    '#^api/lang/labels$#' => '\Core\Rest\Lang\LangGetService',
                    '#^api/lang/set$#' => '\Core\Rest\Lang\LangSetService',
                    '#^api/lang/current$#' => '\Core\Rest\Lang\LangCurrentService',
                    '#^api/lang/switch$#' => '\Core\Rest\Lang\LangSwitchService'
                ]);
            }
            
            // Future: Add other CORE services here
            // if (isset($services['auth'])) {
            //     $this->coreRoutes = array_merge($this->coreRoutes, [
            //         '#^api/auth/login$#' => '\Core\Rest\Auth\AuthLoginService',
            //         '#^api/auth/logout$#' => '\Core\Rest\Auth\AuthLogoutService'
            //     ]);
            // }
            
            // Merge CORE routes with application routes (CORE routes take precedence)
            $this->routes = array_merge($this->coreRoutes, $this->routes);
            
        } catch (\Exception $e) {
            // If core() is not available or configuration is missing, continue with app routes only
            // This ensures backward compatibility
        }
    }

    /**
     * Main routing method — resolves the request path from the URL (REST-style)
     * and dispatches to API services or static/SPA content.
     *
     * The path is taken from `REQUEST_URI`, never from `$_REQUEST` / POST body.
     * An explicit `$path` may be supplied for tests or internal calls.
     *
     * @param string|null $path Optional pre-resolved relative path (skips URI parsing).
     * @return void
     */
    public function route(?string $path = null)
    {
        $rawPath = $path ?? $this->resolveRequestPath();
        $resolved = $this->sanitizePath($rawPath);

        if ($resolved === null) {
            http_response_code(400);
            $this->emitInvalidPath($rawPath);
            return;
        }

        if (strpos($resolved, 'api/') === 0) {
            $this->routeApi($resolved);
        } else {
            $this->routeContent($resolved);
        }
    }

    /**
     * Resolve the application-relative path from the current HTTP request URI.
     *
     * Strips the configured or auto-detected base path (e.g. `/MyJourney`) and
     * normalises a direct front-controller hit (`/index.php`, `/index.php/...`).
     *
     * @return string Raw relative path before sanitization (may be empty).
     */
    protected function resolveRequestPath(): string
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($uriPath) || $uriPath === '') {
            return '';
        }

        $path = rawurldecode($uriPath);
        $basePath = $this->getBasePath();

        if ($basePath !== '' && strncmp($path, $basePath, strlen($basePath)) === 0) {
            $path = substr($path, strlen($basePath));
        }

        $path = trim($path, '/');

        if ($path === 'index.php') {
            return '';
        }
        if (strncmp($path, 'index.php/', 10) === 0) {
            $path = substr($path, 10);
        }

        return trim($path, '/');
    }

    /**
     * Application URL prefix (e.g. `/MyJourney` when deployed in a subdirectory).
     *
     * Priority: `[parameters] base_path` in config, else dirname of `SCRIPT_NAME`.
     *
     * @return string Base path without trailing slash, or empty at document root.
     */
    protected function getBasePath(): string
    {
        try {
            $config = core()->getConfigSection('parameters');
            if (isset($config['base_path']) && is_string($config['base_path'])) {
                $configured = trim($config['base_path']);
                if ($configured !== '' && $configured !== '/') {
                    return rtrim(str_replace('\\', '/', $configured), '/');
                }
                return '';
            }
        } catch (\Exception $e) {
            // fall through to SCRIPT_NAME detection
        }

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        if ($dir === '' || $dir === '/' || $dir === '.') {
            return '';
        }
        return $dir;
    }

    /**
     * Sanitize a relative request path.
     *
     * Returns the sanitized path (always relative, never empty for traversal),
     * an empty string for "no path", or null if the input is unsafe.
     *
     * Rules:
     * - reject null bytes and backslashes (cross-OS path separators);
     * - reject Windows drive prefixes and URL schemes;
     * - reject `..` and `.` segments and empty segments (no double-slash);
     * - allow only `[A-Za-z0-9._/-]`;
     * - cap length to MAX_PATH_LENGTH.
     *
     * @param string $rawPath Raw relative path from the URL.
     * @return string|null Sanitized relative path, or null when unsafe.
     */
    protected function sanitizePath($rawPath)
    {
        if (!is_string($rawPath)) {
            return null;
        }
        if (strlen($rawPath) > self::MAX_PATH_LENGTH) {
            return null;
        }

        $path = trim($rawPath, "/");
        if ($path === '') {
            return '';
        }

        if (strpos($path, "\0") !== false || strpos($path, '\\') !== false) {
            return null;
        }

        if (preg_match('#^[a-zA-Z]:#', $path)) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $path)) {
            return null;
        }

        if (!preg_match('#^[A-Za-z0-9._/\-]+$#', $path)) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    /**
     * Emit a minimal error payload when the request path is rejected.
     * JSON for API-looking requests, plain text otherwise.
     *
     * @param string $rawPath Original raw value (not echoed back).
     * @return void
     */
    protected function emitInvalidPath($rawPath)
    {
        $looksLikeApi = strpos(ltrim((string)$rawPath, '/'), 'api/') === 0;
        if ($looksLikeApi) {
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => 'Invalid request path']);
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid request path.";
    }

    /**
     * Route API requests to appropriate service classes
     *
     * @param string $page The API endpoint path
     * @return void
     */
    protected function routeApi($page)
    {
        foreach ($this->routes as $pattern => $serviceClass) {
            if (preg_match($pattern, $page, $matches)) {
                array_shift($matches);
                if (class_exists($serviceClass)) {
                    $service = new $serviceClass();
                    $result = call_user_func_array([$service, 'handle'], $matches);
                    if ($result !== null) {
                        header('Content-Type: application/json');
                        echo json_encode($result);
                    }
                    return;
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => true, 'message' => "API service not found: $serviceClass"]);
                    return;
                }
            }
        }
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => "No API route matched for: $page"]);
    }

    /**
     * Route content requests to static files or PHP scripts.
     *
     * `$page` is assumed sanitized by sanitizePath(); a realpath() boundary
     * check still confirms the resolved file lives inside the WWW root, and
     * forbidden extensions are blocked to avoid leaking server-side sources.
     *
     * @param string $page Sanitized relative path (may be empty).
     * @return void
     */
    protected function routeContent($page)
    {
        $config = core()->getConfigSection('parameters');
        $wwwRoot = isset($config['www_root']) ? $config['www_root'] : 'WWW';

        $rootReal = realpath($wwwRoot);
        if ($rootReal === false) {
            http_response_code(500);
            echo "WWW root not configured.";
            return;
        }

        if ($page !== '' && !$this->hasForbiddenExtension($page)) {
            $candidate = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $page);
            $candidateReal = realpath($candidate);

            if ($candidateReal !== false
                && $this->isPathInside($candidateReal, $rootReal)
                && is_file($candidateReal)
                && is_readable($candidateReal)
                && !$this->hasForbiddenExtension($candidateReal)) {
                $mime = mime_content_type($candidateReal);
                header('Content-Type: ' . $mime);
                readfile($candidateReal);
                return;
            }
        }

        $indexPhp = $rootReal . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($indexPhp) && is_readable($indexPhp)) {
            include $indexPhp;
            return;
        }

        $indexHtml = $rootReal . DIRECTORY_SEPARATOR . 'index.html';
        if (is_file($indexHtml) && is_readable($indexHtml)) {
            $mime = mime_content_type($indexHtml);
            header('Content-Type: ' . $mime);
            readfile($indexHtml);
            return;
        }

        http_response_code(404);
        echo "Page not found.";
    }

    /**
     * Check that an absolute path is contained within a base directory.
     *
     * @param string $path Absolute path to test.
     * @param string $base Absolute base directory.
     * @return bool
     */
    protected function isPathInside($path, $base)
    {
        $base = rtrim($base, "/\\");
        if ($path === $base) {
            return true;
        }
        $prefix = $base . DIRECTORY_SEPARATOR;
        return strncmp($path, $prefix, strlen($prefix)) === 0;
    }

    /**
     * Tell whether a file path uses an extension blocked from direct serving.
     *
     * @param string $path Path or filename.
     * @return bool
     */
    protected function hasForbiddenExtension($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }
        return in_array($ext, self::$forbiddenExtensions, true);
    }

    /**
     * Add a new route pattern to the router
     *
     * @param string $pattern Regular expression pattern for matching routes
     * @param string $serviceClass The service class to instantiate for this route
     * @return void
     */
    public function addRoute($pattern, $serviceClass)
    {
        $this->routes[$pattern] = $serviceClass;
    }
}
