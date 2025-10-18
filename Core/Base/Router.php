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
     * Array of route patterns mapped to service classes
     * @var array
     */
    protected $routes = [];

    /**
     * Initialize router with optional route definitions
     *
     * @param array $routes Route patterns mapped to service classes
     */
    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    /**
     * Main routing method that determines whether to route API or content requests
     *
     * @return void
     */
    public function route()
    {
        $page = isset($_REQUEST['page']) ? trim($_REQUEST['page'], "/") : '';

        if (strpos($page, 'api/') === 0) {
            $this->routeApi($page);
        } else {
            $this->routeContent($page);
        }
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
     * Route content requests to static files or PHP scripts
     *
     * @param string $page The content page path
     * @return void
     */
    protected function routeContent($page)
    {
        $config = core()->getConfigSection('parameters');
        $wwwRoot = isset($config['www_root']) ? $config['www_root'] : 'WWW';

        $file = $wwwRoot . '/' . ($page ?: 'index.html');

        if (is_file($file) && is_readable($file)) {
            $mime = mime_content_type($file);
            header('Content-Type: ' . $mime);
            readfile($file);
            return;
        }

        $indexPhp = $wwwRoot . '/index.php';
        if (is_file($indexPhp) && is_readable($indexPhp)) {
            include $indexPhp;
            return;
        }

        $indexHtml = $wwwRoot . '/index.html';
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
?>



