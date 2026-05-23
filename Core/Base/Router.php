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
     * Maximum accepted length for the raw `page` parameter.
     */
    const MAX_PAGE_LENGTH = 1024;

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
     * Main routing method that determines whether to route API or content requests
     *
     * @return void
     */
    public function route()
    {
        $rawPage = isset($_REQUEST['page']) ? (string)$_REQUEST['page'] : '';
        $page = $this->sanitizePagePath($rawPage);

        if ($page === null) {
            http_response_code(400);
            $this->emitInvalidPage($rawPage);
            return;
        }

        if (strpos($page, 'api/') === 0) {
            $this->routeApi($page);
        } else {
            $this->routeContent($page);
        }
    }

    /**
     * Sanitize the raw `page` request parameter.
     *
     * Returns the sanitized path (always relative, never empty for traversal),
     * an empty string for "no page", or null if the input is unsafe.
     *
     * Rules:
     * - reject null bytes and backslashes (cross-OS path separators);
     * - reject Windows drive prefixes and URL schemes;
     * - reject `..` and `.` segments and empty segments (no double-slash);
     * - allow only `[A-Za-z0-9._/-]`;
     * - cap length to MAX_PAGE_LENGTH.
     *
     * @param string $rawPage Raw value from the request.
     * @return string|null Sanitized relative path, or null when unsafe.
     */
    protected function sanitizePagePath($rawPage)
    {
        if (!is_string($rawPage)) {
            return null;
        }
        if (strlen($rawPage) > self::MAX_PAGE_LENGTH) {
            return null;
        }

        $page = trim($rawPage, "/");
        if ($page === '') {
            return '';
        }

        // Null byte or backslash anywhere → reject upfront.
        if (strpos($page, "\0") !== false || strpos($page, '\\') !== false) {
            return null;
        }

        // Windows drive prefix (`C:`) or URL scheme (`http://`, `file://`, …).
        if (preg_match('#^[a-zA-Z]:#', $page)) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $page)) {
            return null;
        }

        // Whitelist allowed characters only.
        if (!preg_match('#^[A-Za-z0-9._/\-]+$#', $page)) {
            return null;
        }

        // Reject traversal segments and empty segments (double slashes).
        foreach (explode('/', $page) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $page;
    }

    /**
     * Emit a minimal error payload when the page parameter is rejected.
     * JSON for API-looking requests, plain text otherwise.
     *
     * @param string $rawPage Original raw value (not echoed back).
     * @return void
     */
    protected function emitInvalidPage($rawPage)
    {
        $looksLikeApi = strpos(ltrim((string)$rawPage, '/'), 'api/') === 0;
        if ($looksLikeApi) {
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => 'Invalid page parameter']);
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid page parameter.";
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
     * `$page` is assumed sanitized by sanitizePagePath(); a realpath() boundary
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
