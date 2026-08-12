# REST services (CORE_PHP)

Authoritative contract: `Core/Base/RestService.php`. Cursor rule: `.cursor/rules/core-php-rest.mdc`.

## `core()` and base class

- Services access platform via `core()` — `core()->io`, `core()->session`, `core()->db`, …
- Concrete endpoints extend `\Core\Base\RestService`.
- Implement `protected function process()` and declare `$paramSpecs`.

## `process()` signature — mandatory

- **`process()` takes no arguments.** Never declare `process($id)`, `process(...$args)` or similar.
- Every input — URL captures, query string, POST body, JSON body — is declared in `$paramSpecs` (with `source` ∈ `path | get | post | json | request`) and read from `$this->params['name']` inside `process()`.
- The Router never injects business arguments into `process()`; route captures are exposed only through `paramSpecs` with `source => 'path'` (and optional `index`).
- `handle(...$args)` keeps `...$args` only as an internal bridge to `paramSpecs.source = path`. Concrete services do **not** override or rely on it.

## Security — declarative, deny-by-default

Every concrete service MUST declare three properties or it is refused (HTTP 500, `SECURITY_DECLARATION_ERROR`):

| Property | Type | Role |
|----------|------|------|
| `$securityLevel` | `\Core\Base\SecurityLevel` | Access level (Public, Authenticated, Owner, Shared, Admin, Ai) |
| `$httpMethod`    | `\Core\Base\HttpMethod`    | Expected HTTP method; mismatched → 405 |
| `$security`      | `array`                    | Audit metadata: `auth`, `public`, `resource`, `resourceIdParam`, `operation`, `visibilityAware` |

Lifecycle in `RestService::handle()`:

1. assert declarations (level, method, security keys, security coherence, policy keys);
2. assert request method matches;
3. policy gate: rate-limit (`enforceRateLimit()`);
4. policy gate: CSRF (`enforceCsrf()`, mutating methods only);
5. validate `$paramSpecs` into `$this->params`;
6. enforce security level (auth/role/ownership) with `$this->params` available;
7. call `process()`;
8. audit (`auditCall($result)`, best-effort).

Any failure before step 7 short-circuits with a JSON error payload — `process()` is never reached.

### Resource-level hooks

- `Owner`  → override `checkOwnership()`.
- `Shared` → override `checkSharedAccess()`.
- `Ai`     → override `checkAiAccess()`.

All default to **deny** (return false). Use `$this->params[...]` and the request context.

## Policy — cross-cutting defaults

Optional `protected array $policy = []`. Defaults merged from `RestService::DEFAULT_POLICY`; unknown keys → `SECURITY_DECLARATION_ERROR`.

| Key | Type | Default | Role |
|-----|------|---------|------|
| `csrf`      | `bool`         | `true`       | Verify CSRF token on state-changing methods. Skipped on GET. |
| `rateLimit` | `string\|false`| `'standard'` | Named bucket; `false` disables. |
| `audit`     | `bool`         | `true`       | Structured audit log per call. |

Override hooks: `enforceRateLimit()`, `enforceCsrf()`, `auditCall($result)`.

## paramSpecs

```php
protected $paramSpecs = [
    [
        'name' => 'title',
        'type' => 'string',
        'required' => true,
        'minLength' => 1,
        'maxLength' => 200,
        'source' => 'json'
    ]
];
```

Use `$this->params['title']` in `process()`.

Supported types are `email`, `int`, `float`, `string`, `bool`, and `array`.
Set `strict => true` when the native JSON type must match instead of accepting
the normal request coercion (for example, reject `"12"` when `int` is required).
For arrays:

- `minItems` / `maxItems` constrain the number of elements;
- `list => true` rejects associative arrays;
- `uniqueItems => true` rejects duplicates after item normalization;
- `items` accepts a recursive parameter specification and validates each element.

```php
[
    'name'        => 'ids',
    'type'        => 'array',
    'list'        => true,
    'uniqueItems' => true,
    'items'       => [
        'type'      => 'string',
        'strict'    => true,
        'minLength' => 26,
        'maxLength' => 26,
    ],
    'source'      => 'json',
]
```

Domain-specific validation such as resource membership and ownership still
belongs in the concrete service.

## Examples

### Public GET ping

```php
use Core\Base\HttpMethod;
use Core\Base\SecurityLevel;

class PingService extends \Core\Base\RestService
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;
    protected ?HttpMethod   $httpMethod    = HttpMethod::Get;

    protected array $security = [
        'auth' => false, 'public' => true,
        'resource' => null, 'resourceIdParam' => null,
        'operation' => 'health_check', 'visibilityAware' => false,
    ];

    protected $paramSpecs = [];

    protected function process()
    {
        return ['data' => ['pong' => true], 'status' => 'SUCCESS'];
    }
}
```

### Owner-only update

```php
protected SecurityLevel $securityLevel = SecurityLevel::Owner;
protected ?HttpMethod   $httpMethod    = HttpMethod::Put;

protected array $security = [
    'auth' => true, 'public' => false,
    'resource' => 'journey', 'resourceIdParam' => 'id',
    'operation' => 'update', 'visibilityAware' => false,
];

protected function checkOwnership(): bool
{
    $journeyId = (int)($this->params['id'] ?? 0);
    $userId    = (int)(core()->session->get('user')['id'] ?? 0);
    return $journeyId > 0 && $userId > 0 && core()->io->journey->isOwner($journeyId, $userId);
}
```

## Anti-patterns

```php
// BAD
public function process($id) { ... $_GET['foo'] ... }

// GOOD
public function process() {
    $id = $this->params['id'];
    ...
}
```

## App integration (not CORE_PHP)

Each **consuming app**:

- registers routes in its own Router (paths like `api/entity/action`);
- implements domain RestService classes under its app tree (e.g. `PHP/App/Rest/`);
- owns IO classes and migrations.

This repo defines the **framework contract** only — not app routes or domain SQL.
