<?php
namespace Core\Base;

use Core\Exception\CoreSecurityException;

/**
 * Class RestService
 *
 * Abstract base class for REST API services in the CORE framework.
 *
 * Provides a standardized foundation for building REST API endpoints
 * with declarative security, HTTP method enforcement, automatic parameter
 * validation, and standardized response formatting.
 *
 * Required declarations in every concrete service:
 * - `$securityLevel` (SecurityLevel) — the access level (deny-by-default).
 * - `$httpMethod`    (HttpMethod)    — the expected request method.
 * - `$security`      (array)         — declarative metadata for audit/visibility.
 *
 * Optional declaration (defaults applied when omitted):
 * - `$policy`        (array)         — cross-cutting policy (csrf, rateLimit, audit).
 *
 * Lifecycle (`handle()`):
 *  1. Declarations are present and coherent (else 500).
 *  2. The request HTTP method matches the declared one (else 405).
 *  3. Rate-limit policy hook (429 only when a subclass enforces a bucket budget).
 *  4. CSRF gate for state-changing methods when a session token is provisioned (else 403).
 *  5. Parameters are validated against `$paramSpecs` (else 400).
 *  6. The declared security level is enforced with `$this->params` available (else 401/403).
 *  7. `process()` runs the business logic with no direct arguments.
 *  8. Audit hook fires (best-effort, never throws).
 *  9. Response is formatted (data + functional status, HTTP 200 on success).
 *
 * Response format (success):
 *   ['data' => mixed, 'status' => 'SUCCESS' | 'XXX']
 *
 * The HTTP status is always 200 for normal flows; functional outcomes
 * are conveyed through the `status` string (e.g. `VALIDATION_ERROR`).
 */
abstract class RestService
{
    /**
     * Required declarative metadata keys for `$security`.
     * @var string[]
     */
    private const REQUIRED_SECURITY_KEYS = [
        'auth', 'public', 'resource', 'resourceIdParam', 'operation', 'visibilityAware',
    ];

    /**
     * Default values applied to every service's `$policy`.
     * Concrete services may override individual keys; unknown keys are refused.
     */
    private const DEFAULT_POLICY = [
        'csrf'      => true,
        'rateLimit' => 'standard',
        'audit'     => true,
    ];

    /**
     * Whitelist of keys a service is allowed to override in `$policy`.
     * @var string[]
     */
    private const ALLOWED_POLICY_KEYS = ['csrf', 'rateLimit', 'audit'];

    /**
     * Functional status returned in the JSON body when a SecurityLevel
     * declaration is missing or incoherent (server-side bug).
     */
    private const STATUS_DECLARATION_ERROR = 'SECURITY_DECLARATION_ERROR';

    /**
     * Required: every concrete service MUST replace this default
     * with an explicit SecurityLevel value (deny-by-default).
     */
    protected SecurityLevel $securityLevel = SecurityLevel::Undefined;

    /**
     * Required: expected HTTP method for this endpoint.
     * The actual request method must match exactly, otherwise 405.
     */
    protected ?HttpMethod $httpMethod = null;

    /**
     * Required: declarative metadata describing the endpoint.
     *
     * Required keys (values may be null where not applicable):
     * - `auth`            (bool)        — true if the endpoint requires authentication.
     * - `public`          (bool)        — true if the endpoint serves publicly visible data.
     * - `resource`        (string|null) — domain resource (e.g. 'journey', 'update').
     * - `resourceIdParam` (string|null) — request param holding the resource id (path/json key).
     * - `operation`       (string)      — read | create | update | delete | publish | share | admin | ai_action | ...
     * - `visibilityAware` (bool)        — endpoint enforces public/private visibility.
     *
     * @var array<string, mixed>
     */
    protected array $security = [];

    /**
     * Optional cross-cutting policy. Keys are merged with `DEFAULT_POLICY`;
     * only keys listed in `ALLOWED_POLICY_KEYS` are accepted.
     *
     * Supported keys:
     * - `csrf`      (bool)        — when true, compare `X-CSRF-Token` (or `_csrf`) against
     *                              `csrf_token` in session on mutating methods. Skipped when
     *                              no session token is provisioned yet (application lifecycle).
     * - `rateLimit` (string|false)— named bucket declaration for audit/overrides; CORE does not
     *                              enforce counters by default. `false` disables the hook.
     * - `audit`     (bool)        — emit a structured audit log entry per call. Default: true.
     *
     * @var array<string, mixed>
     */
    protected array $policy = [];

    /**
     * Validated parameters from the request.
     * @var array
     */
    protected $params = [];

    /**
     * Parameter specifications for validation; defined in child classes.
     * @var array
     */
    protected $paramSpecs = [];

    /**
     * Route captures passed by the router.
     * They are only exposed through `paramSpecs` with `source => path`;
     * concrete services must read validated values from `$this->params`.
     * @var array
     */
    protected $routeArgs = [];

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------

    /**
     * Main entry point invoked by the Router.
     *
     * @param mixed ...$args Optional route captures used only by `source => path`.
     * @return mixed|null
     */
    public function handle(...$args)
    {
        try {
            if (!ob_get_level()) { ob_start(); }
            $this->routeArgs = $args;

            // 1. Mandatory declarations.
            $this->assertSecurityDeclared();
            $this->assertHttpMethodDeclared();
            $this->assertSecurityMetadata();
            $this->assertSecurityCoherent();
            $this->assertPolicyKnown();

            // 2. Method check (cheap, run before any side effect).
            $this->assertHttpMethodMatches();

            // 3. Policy gates: rate-limit then CSRF (cheapest first).
            $this->enforceRateLimit();
            $this->enforceCsrf();

            // 4. Parameter validation. Security callbacks can safely use `$this->params`.
            $this->params = $this->validate();

            // 5. Authorization.
            $this->enforceSecurity();

            // 6. Business logic. Concrete services read only `$this->params`.
            $result = $this->process();

            // 7. Audit (best-effort, must not throw).
            $this->auditCall($result);

            // 8. Response formatting (compatible with previous contract).
            if (is_array($result) && array_key_exists('data', $result) && array_key_exists('status', $result)) {
                $this->sendJson($result['data'], $result['status']);
            } else {
                return $result;
            }
        } catch (CoreSecurityException $e) {
            return $this->sendSecurityError($e);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Business logic implemented by concrete services.
     *
     * @return mixed
     */
    abstract protected function process();

    // ---------------------------------------------------------------------
    // Security — declarations
    // ---------------------------------------------------------------------

    /**
     * Refuse to serve a service that did not declare a security level.
     *
     * @throws CoreSecurityException
     */
    protected function assertSecurityDeclared(): void
    {
        if ($this->securityLevel === SecurityLevel::Undefined) {
            throw new CoreSecurityException(
                'Security level must be explicitly declared on ' . static::class . '.',
                500,
                self::STATUS_DECLARATION_ERROR
            );
        }
    }

    /**
     * Refuse to serve a service that did not declare its expected HTTP method.
     *
     * @throws CoreSecurityException
     */
    protected function assertHttpMethodDeclared(): void
    {
        if ($this->httpMethod === null) {
            throw new CoreSecurityException(
                'Expected HTTP method must be declared on ' . static::class . '.',
                500,
                self::STATUS_DECLARATION_ERROR
            );
        }
    }

    /**
     * All required `$security` metadata keys must be present.
     *
     * @throws CoreSecurityException
     */
    protected function assertSecurityMetadata(): void
    {
        foreach (self::REQUIRED_SECURITY_KEYS as $key) {
            if (!array_key_exists($key, $this->security)) {
                throw new CoreSecurityException(
                    "Missing security metadata key '{$key}' on " . static::class . '.',
                    500,
                    self::STATUS_DECLARATION_ERROR
                );
            }
        }
    }

    /**
     * Cross-check `$securityLevel` with `$security` flags so a typo in either
     * place fails fast (e.g. `auth=false` with `securityLevel=Authenticated`).
     *
     * @throws CoreSecurityException
     */
    protected function assertSecurityCoherent(): void
    {
        $auth = (bool)$this->security['auth'];
        if ($this->securityLevel === SecurityLevel::Public && $auth) {
            throw new CoreSecurityException(
                'Inconsistent security on ' . static::class . ': Public level cannot require auth.',
                500,
                self::STATUS_DECLARATION_ERROR
            );
        }
        if ($this->securityLevel !== SecurityLevel::Public && !$auth) {
            throw new CoreSecurityException(
                'Inconsistent security on ' . static::class . ': non-Public level must set auth=true.',
                500,
                self::STATUS_DECLARATION_ERROR
            );
        }
    }

    /**
     * Reject any unknown key declared in `$policy`. Catches typos at boot time.
     *
     * @throws CoreSecurityException
     */
    protected function assertPolicyKnown(): void
    {
        foreach (array_keys($this->policy) as $key) {
            if (!in_array($key, self::ALLOWED_POLICY_KEYS, true)) {
                throw new CoreSecurityException(
                    "Unknown policy key '{$key}' on " . static::class
                        . '. Allowed: ' . implode(', ', self::ALLOWED_POLICY_KEYS) . '.',
                    500,
                    self::STATUS_DECLARATION_ERROR
                );
            }
        }
    }

    /**
     * Effective policy: declared values merged on top of `DEFAULT_POLICY`.
     *
     * @return array<string, mixed>
     */
    protected function getEffectivePolicy(): array
    {
        return array_merge(self::DEFAULT_POLICY, $this->policy);
    }

    // ---------------------------------------------------------------------
    // Security — runtime
    // ---------------------------------------------------------------------

    /**
     * Verify the incoming HTTP method matches the declared one.
     *
     * @throws CoreSecurityException
     */
    protected function assertHttpMethodMatches(): void
    {
        $actual = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
        if ($actual === '' || $actual !== $this->httpMethod->value) {
            throw new CoreSecurityException(
                "HTTP method '{$actual}' not allowed for " . static::class . " (expected {$this->httpMethod->value}).",
                405,
                'METHOD_NOT_ALLOWED'
            );
        }
    }

    /**
     * Dispatch to the appropriate authorization check based on `$securityLevel`.
     *
     * @throws CoreSecurityException
     */
    protected function enforceSecurity(): void
    {
        switch ($this->securityLevel) {
            case SecurityLevel::Public:
                return;

            case SecurityLevel::Authenticated:
                $this->requireAuthenticated();
                return;

            case SecurityLevel::Admin:
                $this->requireAuthenticated();
                $this->requireRole('admin');
                return;

            case SecurityLevel::Owner:
                $this->requireAuthenticated();
                if (!$this->checkOwnership()) {
                    throw new CoreSecurityException(
                        'Forbidden: caller is not the owner of the resource.',
                        403,
                        'NOT_OWNER'
                    );
                }
                return;

            case SecurityLevel::Shared:
                $this->requireAuthenticated();
                if (!$this->checkSharedAccess()) {
                    throw new CoreSecurityException(
                        'Forbidden: caller has no shared access to the resource.',
                        403,
                        'NO_SHARE_ACCESS'
                    );
                }
                return;

            case SecurityLevel::Ai:
                $this->requireAuthenticated();
                if (!$this->checkAiAccess()) {
                    throw new CoreSecurityException(
                        'Forbidden: AI access denied for this caller.',
                        403,
                        'NO_AI_ACCESS'
                    );
                }
                return;

            default:
                throw new CoreSecurityException(
                    'Unknown security level: ' . $this->securityLevel->value,
                    500,
                    self::STATUS_DECLARATION_ERROR
                );
        }
    }

    /**
     * Require the caller to have an authenticated session.
     * Override to plug in a different auth mechanism.
     *
     * @throws CoreSecurityException
     */
    protected function requireAuthenticated(): void
    {
        if (!core()->session->has('user')) {
            throw new CoreSecurityException(
                'Authentication required.',
                401,
                'UNAUTHENTICATED'
            );
        }
    }

    /**
     * Require the connected user to have the given role.
     *
     * @throws CoreSecurityException
     */
    protected function requireRole(string $role): void
    {
        $user = core()->session->get('user');
        $roles = is_array($user) && isset($user['roles']) && is_array($user['roles'])
            ? $user['roles']
            : [];
        if (!in_array($role, $roles, true)) {
            throw new CoreSecurityException(
                "Forbidden: missing role '{$role}'.",
                403,
                'MISSING_ROLE'
            );
        }
    }

    /**
     * Override in services declaring SecurityLevel::Owner.
     * Default deny — services MUST return true only when ownership is proven.
     */
    protected function checkOwnership(): bool
    {
        return false;
    }

    /**
     * Override in services declaring SecurityLevel::Shared.
     * Default deny.
     */
    protected function checkSharedAccess(): bool
    {
        return false;
    }

    /**
     * Override in services declaring SecurityLevel::Ai.
     * Default deny.
     */
    protected function checkAiAccess(): bool
    {
        return false;
    }

    // ---------------------------------------------------------------------
    // Policy — rate limit / CSRF / audit
    // ---------------------------------------------------------------------

    /**
     * Rate-limit policy hook. Default implementation only validates the bucket
     * declaration; CORE_PHP does not ship a counter store or generic enforcement.
     * Consuming applications override this hook when they need concrete throttling
     * (e.g. Redis-backed counters).
     *
     * Throw a CoreSecurityException(429, 'RATE_LIMITED') when the caller
     * exceeds the bucket budget.
     *
     * @throws CoreSecurityException
     */
    protected function enforceRateLimit(): void
    {
        $bucket = $this->getEffectivePolicy()['rateLimit'];
        if ($bucket === false || $bucket === null) {
            return;
        }
        if (!is_string($bucket) || $bucket === '') {
            throw new CoreSecurityException(
                'Invalid rateLimit policy on ' . static::class . '.',
                500,
                self::STATUS_DECLARATION_ERROR
            );
        }
        // No global counter store yet — concrete enforcement is wired in
        // a project-specific override. Keep this hook to make the policy
        // explicit and audit-visible.
    }

    /**
     * CSRF gate. Active only when `policy.csrf === true`, the request uses a
     * state-changing HTTP method, and a `csrf_token` value exists in session.
     *
     * Default behaviour: read `X-CSRF-Token` (then `_csrf`), compare to the session
     * token with `hash_equals`, respond `CSRF_FAILED` on mismatch. When no session
     * token is provisioned yet, the check is skipped — token lifecycle belongs to
     * the consuming application; absence of a session token is not effective CSRF
     * protection on its own.
     *
     * @throws CoreSecurityException
     */
    protected function enforceCsrf(): void
    {
        if ($this->getEffectivePolicy()['csrf'] !== true) {
            return;
        }
        if ($this->httpMethod === HttpMethod::Get) {
            return;
        }
        $expected = (string)(core()->session->get('csrf_token') ?? '');
        if ($expected === '') {
            return;
        }
        $provided = $this->readCsrfToken();
        if (!hash_equals($expected, $provided)) {
            throw new CoreSecurityException(
                'CSRF token missing or invalid.',
                403,
                'CSRF_FAILED'
            );
        }
    }

    /**
     * Read the CSRF token from `X-CSRF-Token` header first, then `_csrf`
     * request field as a fallback.
     */
    protected function readCsrfToken(): string
    {
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string)$name, 'X-CSRF-Token') === 0) {
                    return (string)$value;
                }
            }
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return (string)($_REQUEST['_csrf'] ?? '');
    }

    /**
     * Append a structured audit entry for this call. Best-effort: never
     * throws — audit failures must not break the response.
     *
     * Override to ship to an external sink (DB table, log shipper, etc.).
     *
     * @param mixed $result Result returned by `process()` or a synthetic
     *                      payload such as `['status' => 'CSRF_FAILED']`.
     */
    protected function auditCall($result): void
    {
        if (($this->getEffectivePolicy()['audit'] ?? true) !== true) {
            return;
        }
        if ($this->httpMethod === null || $this->securityLevel === SecurityLevel::Undefined) {
            return;
        }
        try {
            $status = is_array($result) && isset($result['status'])
                ? (string)$result['status']
                : 'UNKNOWN';
            $userId = '';
            if (core()->session->has('user')) {
                $stored = core()->session->get('user');
                if (is_array($stored) && isset($stored['id'])) {
                    $userId = (string)$stored['id'];
                }
            }
            error_log(sprintf(
                '[audit] service=%s method=%s level=%s status=%s user=%s resource=%s op=%s',
                static::class,
                $this->httpMethod->value,
                $this->securityLevel->value,
                $status,
                $userId,
                (string)($this->security['resource'] ?? ''),
                (string)($this->security['operation'] ?? '')
            ));
        } catch (\Throwable $ignore) {
            // audit must never break the request
        }
    }

    // ---------------------------------------------------------------------
    // Validation (existing behaviour)
    // ---------------------------------------------------------------------

    /**
     * Validate request parameters against `$paramSpecs`.
     *
     * @return array Validated parameters.
     * @throws \Exception When validation fails.
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
        throw new \Exception('No paramSpecs defined and validate() not overridden.');
    }

    /**
     * Extract a single parameter value from the configured source.
     *
     * @param array $spec
     * @return mixed
     */
    protected function getParamFromSpec($spec)
    {
        $source = $spec['source'] ?? 'request';
        $name = $spec['name'];

        switch ($source) {
            case 'path':
                $idx = isset($spec['index']) ? (int)$spec['index'] : 0;
                return $this->routeArgs[$idx] ?? ($spec['default'] ?? null);

            case 'get':
                return $_GET[$name] ?? ($spec['default'] ?? null);

            case 'post':
                return $_POST[$name] ?? ($spec['default'] ?? null);

            case 'json':
                $json = $this->getJsonBody();
                $path = $spec['json_path'] ?? $name;
                return $this->getValueFromJsonPath($json, $path) ?? ($spec['default'] ?? null);

            case 'request':
            default:
                return $_REQUEST[$name] ?? ($spec['default'] ?? null);
        }
    }

    /**
     * Read JSON body once per request.
     *
     * @return array|null
     */
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

    /**
     * Resolve a dot-notation path inside a JSON-decoded array.
     *
     * @param array|null $json
     * @param string     $path
     * @return mixed|null
     */
    protected function getValueFromJsonPath($json, $path)
    {
        if (!$json || !$path) {
            return null;
        }
        $value = $json;
        foreach (explode('.', $path) as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        return $value;
    }

    // ---------------------------------------------------------------------
    // Response helpers
    // ---------------------------------------------------------------------

    /**
     * Send a successful JSON payload (HTTP 200, functional status in body).
     *
     * @param mixed  $data
     * @param string $status Functional status (e.g. SUCCESS).
     */
    protected function sendJson($data, $status = 'SUCCESS')
    {
        if (ob_get_level()) {
            while (ob_get_level()) { ob_end_clean(); }
        }
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'data'   => $data,
            'status' => $status,
        ]);
        exit;
    }

    /**
     * Send a security-related error response.
     * Body keeps a terse functional status; details live in the server logs.
     */
    protected function sendSecurityError(CoreSecurityException $e): void
    {
        if (ob_get_level()) {
            while (ob_get_level()) { ob_end_clean(); }
        }
        http_response_code($e->getHttpStatus());
        header('Content-Type: application/json');
        error_log('[CoreSecurityException] ' . static::class . ': ' . $e->getMessage());
        $this->auditCall(['status' => $e->getFunctionalStatus()]);
        echo json_encode([
            'data'   => null,
            'status' => $e->getFunctionalStatus(),
            'error'  => true,
        ]);
        exit;
    }

    /**
     * Build a generic error response (validation failure, unhandled exception).
     *
     * @param string $message
     * @param int    $status HTTP status code.
     * @return array
     */
    protected function errorResponse($message, $status = 400)
    {
        http_response_code($status);
        return [
            'error'   => true,
            'message' => $message,
            'status'  => $status,
        ];
    }
}
