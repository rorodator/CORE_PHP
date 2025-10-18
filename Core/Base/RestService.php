<?php
namespace Core\Base;

/**
 * Class RestService
 *
 * Abstract base class for REST API services.
 * Provides common functionality for request handling, parameter validation,
 * and response formatting. Services should extend this class and implement
 * the process() method to handle specific business logic.
 */
abstract class RestService
{
    /**
     * Validated parameters from the request
     * @var array
     */
    protected $params = [];

    /**
     * Parameter specifications for validation
     * Should be defined in child classes
     * @var array
     */
    protected $paramSpecs = [];

    /**
     * Main entry point for handling REST requests
     *
     * @param mixed ...$args Arguments passed from the router
     * @return mixed Service response or null if JSON was sent directly
     */
    public function handle(...$args)
    {
        try {
            $this->params = $this->validate();
            $result = $this->process(...$args);
            if (is_array($result) && array_key_exists('data', $result) && array_key_exists('status', $result)) {
                $this->sendJson($result['data'], $result['status']);
            } else {
                return $result;
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Validate request parameters according to paramSpecs
     *
     * @return array Validated parameters
     * @throws \Exception If validation fails
     */
    protected function validate()
    {
        if (is_array($this->paramSpecs)) {
            $params = [];
            foreach ($this->paramSpecs as $spec) {
                $value = $this->getParamFromSpec($spec);
                $params[$spec['name']] = core()->paramValidator->validate($value, $spec);
            }
            return $params;
        }
        throw new \Exception("No paramSpecs defined and validate() not overridden.");
    }

    abstract protected function process($id = null);

    protected function getJsonBody()
    {
        static $jsonCache = null;
        if ($jsonCache !== null) {
            return $jsonCache;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $jsonCache = is_array($data) ? $data : null;
        return $jsonCache;
    }

    protected function sendJson($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function errorResponse($message, $status = 400)
    {
        http_response_code($status);
        return [
            'error' => true,
            'message' => $message,
            'status' => $status
        ];
    }

    protected function getParamFromSpec($spec)
    {
        $source = $spec['source'] ?? 'request';
        $name = $spec['name'];
        switch ($source) {
            case 'get':
                return isset($_GET[$name]) ? $_GET[$name] : ($spec['default'] ?? null);
            case 'post':
                return isset($_POST[$name]) ? $_POST[$name] : ($spec['default'] ?? null);
            case 'json':
                $json = $this->getJsonBody();
                $path = $spec['json_path'] ?? $name;
                return $this->getValueFromJsonPath($json, $path) ?? ($spec['default'] ?? null);
            case 'request':
            default:
                return isset($_REQUEST[$name]) ? $_REQUEST[$name] : ($spec['default'] ?? null);
        }
    }

    protected function getValueFromJsonPath($json, $path)
    {
        if (!$json || !$path) return null;
        $parts = explode('.', $path);
        $value = $json;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        return $value;
    }
}
?>



