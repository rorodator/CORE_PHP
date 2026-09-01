---
name: core-php-rest-endpoint
description: Adds a REST endpoint via CORE_PHP RestService pattern. Use when creating or modifying RestService classes in CORE_PHP or any app that extends RestService.
---

# CORE_PHP — REST endpoint

## Before coding

Read `.cursor/rules/core-php-rest.mdc`, `ai-instructions/rest-services.md`, `ai-instructions/layering.md`.

## Steps

1. **RestService class**
   - **Platform endpoint:** `Core/Rest/`
   - **Domain endpoint:** consuming app tree (e.g. `PHP/App/Rest/`) — outside this repo
   - `$securityLevel`, `$httpMethod`, `$security`, optional `$policy`
   - `$paramSpecs` with types, sources, validation
   - `process()` with **no arguments** — read `$this->params` only
   - Return functional `status` — no exceptions for normal flows
   - Resource checks: `checkOwnership()` / `checkSharedAccess()` / default deny
   - **Authorization:** every endpoint enforces caller rights — see “Authorization — mandatory on every endpoint” in `.cursor/rules/core-php-rest.mdc`
2. **App only (outside this repo):** register `api/entity/action` in the app Router; add/update IO + migration if persistence needed.

## JSON request body

For a JSON mutation, declare body parameters with `source => 'json'` and read only validated `$this->params` in `process()`.

`RestService::getJsonBody()` uses a function-local `static` cache intentionally to decode `php://input` once during one request. In normal PHP web runtimes (`php -S`, PHP-FPM, Apache/mod_php), that static state is reset between HTTP requests and must not be treated as a cross-request body leak.

Do not override `getJsonBody()` in an application or add JSON→`$_POST` / `RAW_JSON_BODY` compatibility solely because the CORE implementation uses `static`. Suspected leakage must first be reproduced with separate real HTTP requests. Custom long-lived worker/test harnesses that execute multiple logical requests inside one PHP process are responsible for their own isolation/reset semantics.

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

## References

- `ai-instructions/rest-services.md`
- `.cursor/rules/core-php-rest.mdc`
- `Core/Base/RestService.php`

## App follow-up (outside this repo)

Domain endpoints: IO layer, Router registration, frontend `$svc('ajax')` — maintained in the consuming app repo.
