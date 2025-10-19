<?php
namespace Core\Base;

/**
 * Class RestService
 *
 * Abstract base class for REST API services in the CORE framework.
 * 
 * This class provides a standardized foundation for building REST API endpoints
 * with automatic parameter validation, request handling, and response formatting.
 * 
 * Key Features:
 * - Automatic parameter validation using paramSpecs
 * - Support for multiple parameter sources (GET, POST, JSON, REQUEST)
 * - JSON path navigation for nested parameters
 * - Standardized error handling and response formatting
 * - Integration with the CORE ParamValidator service
 * - Functional status codes (not HTTP status codes) for business logic
 * - Separation of concerns: backend provides status, frontend handles UI messages
 * 
 * Usage:
 * 1. Extend this class in your service
 * 2. Define $paramSpecs array with parameter specifications
 * 3. Implement the process() method with your business logic
 * 4. Access validated parameters via $this->params
 * 
 * Response Format:
 * The process() method should return an array with exactly two keys:
 * - 'data': Contains the actual response data (always present for successful GET operations)
 * - 'status': Functional status code (not HTTP status) for business logic handling
 * 
 * IMPORTANT: NEVER include user-facing messages in the response!
 * The frontend is responsible for displaying success/error messages based on the status.
 * The backend only provides the functional status, never UI messages.
 * 
 * Example responses:
 * Success: ['data' => $labels, 'status' => 'SUCCESS']
 * Business error: ['data' => null, 'status' => 'TEAM_EXISTS']
 * 
 * Real-world example (Team creation):
 * - Success: ['data' => $teamData, 'status' => 'SUCCESS']
 * - Name exists: ['data' => null, 'status' => 'TEAM_EXISTS']
 * - No permission: ['data' => null, 'status' => 'INSUFFICIENT_RIGHTS']
 * 
 * WRONG: ['data' => $teamData, 'status' => 'SUCCESS', 'message' => 'Team created!']
 * RIGHT: ['data' => $teamData, 'status' => 'SUCCESS']
 * 
 * Example paramSpecs:
 * [
 *     ['name' => 'lang', 'type' => 'string', 'source' => 'json', 'default' => 'fr'],
 *     ['name' => 'key', 'type' => 'string', 'source' => 'json', 'required' => false]
 * ]
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
     * This method is called by the router when a request matches a service route.
     * It handles the complete request lifecycle:
     * 1. Validates parameters according to paramSpecs
     * 2. Calls the process() method implemented by child classes
     * 3. Formats and sends the response
     *
     * @param mixed ...$args Arguments passed from the router
     * @return mixed Service response or null if JSON was sent directly
     */
    public function handle(...$args)
    {
        try {
            // Step 1: Validate all parameters according to paramSpecs
            // This populates $this->params with validated values
            $this->params = $this->validate();
            
            // Step 2: Call the business logic implemented by child classes
            $result = $this->process(...$args);
            
            // Step 3: Handle response formatting
            // If result has 'data' and 'status' keys, send JSON response directly
            // Note: 'status' here is a functional status code (e.g., 'SUCCESS', 'TEAM_EXISTS')
            // not an HTTP status code. The HTTP status is always 200 for successful requests.
            if (is_array($result) && array_key_exists('data', $result) && array_key_exists('status', $result)) {
                $this->sendJson($result['data'], $result['status']);
            } else {
                // Return result for further processing by the router
                return $result;
            }
        } catch (\Exception $e) {
            // Handle any errors during processing
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Validate request parameters according to paramSpecs
     * 
     * This method processes each parameter specification defined in $paramSpecs:
     * 1. Extracts the parameter value from the appropriate source (GET, POST, JSON, etc.)
     * 2. Validates the value using the ParamValidator service
     * 3. Returns an array of validated parameters accessible via $this->params
     *
     * @return array Validated parameters
     * @throws \Exception If validation fails
     */
    protected function validate()
    {
        if (is_array($this->paramSpecs)) {
            $params = [];
            // Process each parameter specification
            foreach ($this->paramSpecs as $spec) {
                // Extract parameter value from the specified source
                $value = $this->getParamFromSpec($spec);
                // Validate the value using the core ParamValidator service
                $params[$spec['name']] = core()->paramValidator->validate($value, $spec);
            }
            return $params;
        }
        throw new \Exception("No paramSpecs defined and validate() not overridden.");
    }

    /**
     * Abstract method that must be implemented by child classes
     * Contains the main business logic for the REST service
     * 
     * @param mixed $id Optional ID parameter (for resource-based endpoints)
     * @return mixed Service response data
     */
    abstract protected function process($id = null);

    /**
     * Get JSON body from the request
     * 
     * Uses static caching to avoid reading php://input multiple times
     * since it can only be read once per request
     * 
     * @return array|null Parsed JSON data or null if invalid
     */
    protected function getJsonBody()
    {
        static $jsonCache = null;
        if ($jsonCache !== null) {
            return $jsonCache;
        }
        // Read raw input from the request body
        $input = file_get_contents('php://input');
        // Parse JSON data
        $data = json_decode($input, true);
        // Cache the result (null if invalid JSON)
        $jsonCache = is_array($data) ? $data : null;
        return $jsonCache;
    }

    /**
     * Send JSON response and terminate execution
     * 
     * IMPORTANT: The $status parameter here is a FUNCTIONAL status code
     * (e.g., 'SUCCESS', 'TEAM_EXISTS', 'USER_NOT_FOUND'), NOT an HTTP status code.
     * The HTTP status is always 200 for successful requests, regardless of the
     * functional status. This allows the client to handle business logic errors
     * in a specific and functional way.
     * 
     * @param mixed $data Data to encode as JSON
     * @param string $status Functional status code (e.g., 'SUCCESS', 'TEAM_EXISTS')
     */
    protected function sendJson($data, $status = 'SUCCESS')
    {
        // Always return HTTP 200 for successful requests
        // The functional status is handled by the client
        http_response_code(200);
        header('Content-Type: application/json');
        
        // Send the standardized response format
        // Note: Only 'data' and 'status' keys are allowed. Never include UI messages!
        echo json_encode([
            'data' => $data,
            'status' => $status
        ]);
        exit;
    }

    /**
     * Create an error response array
     * 
     * Returns a standardized error response format that can be
     * processed by the router or sent directly to the client
     * 
     * @param string $message Error message
     * @param int $status HTTP status code (default: 400)
     * @return array Error response array
     */
    protected function errorResponse($message, $status = 400)
    {
        http_response_code($status);
        return [
            'error' => true,
            'message' => $message,
            'status' => $status
        ];
    }

    /**
     * Extract parameter value from the specified source
     * 
     * Supports multiple parameter sources:
     * - 'get': $_GET superglobal
     * - 'post': $_POST superglobal  
     * - 'json': JSON request body (supports dot notation paths)
     * - 'request': $_REQUEST superglobal (default)
     * 
     * @param array $spec Parameter specification
     * @return mixed Parameter value or default value
     */
    protected function getParamFromSpec($spec)
    {
        $source = $spec['source'] ?? 'request';
        $name = $spec['name'];
        
        switch ($source) {
            case 'get':
                // Extract from GET parameters
                return isset($_GET[$name]) ? $_GET[$name] : ($spec['default'] ?? null);
                
            case 'post':
                // Extract from POST parameters
                return isset($_POST[$name]) ? $_POST[$name] : ($spec['default'] ?? null);
                
            case 'json':
                // Extract from JSON request body
                $json = $this->getJsonBody();
                $path = $spec['json_path'] ?? $name; // Support dot notation paths
                return $this->getValueFromJsonPath($json, $path) ?? ($spec['default'] ?? null);
                
            case 'request':
            default:
                // Extract from REQUEST superglobal (GET + POST + COOKIE)
                return isset($_REQUEST[$name]) ? $_REQUEST[$name] : ($spec['default'] ?? null);
        }
    }

    /**
     * Extract value from JSON data using dot notation path
     * 
     * Supports nested object access using dot notation:
     * - 'user.name' accesses $json['user']['name']
     * - 'data.items.0' accesses $json['data']['items'][0]
     * 
     * @param array|null $json JSON data array
     * @param string $path Dot notation path (e.g., 'user.profile.name')
     * @return mixed Value at the specified path or null if not found
     */
    protected function getValueFromJsonPath($json, $path)
    {
        if (!$json || !$path) return null;
        
        // Split the path into individual keys
        $parts = explode('.', $path);
        $value = $json;
        
        // Navigate through the nested structure
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                // Path not found, return null
                return null;
            }
        }
        return $value;
    }
}
?>



