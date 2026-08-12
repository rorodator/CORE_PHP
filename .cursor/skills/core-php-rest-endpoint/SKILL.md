---
name: core-php-rest-endpoint
description: Adds a REST endpoint via CORE_PHP RestService pattern. Use when creating or modifying RestService classes in CORE_PHP or any app that extends RestService.
---

# CORE_PHP — REST endpoint

## Before coding

Read `.cursor/rules/core-php-rest.mdc` and `ai-instructions/rest-services.md`.

## Steps

1. **RestService class** (in `CORE_PHP/Core/Rest/` for platform endpoints, or app `PHP/App/Rest/` for domain):
   - `$securityLevel`, `$httpMethod`, `$security`, optional `$policy`
   - `$paramSpecs` with types, sources, validation
   - `process()` with **no arguments** — read `$this->params` only
   - Return functional `status` (`SUCCESS`, error codes) — no exceptions for normal flows
   - Resource checks: `checkOwnership()` / `checkSharedAccess()` / default deny
2. **App only:** register path `api/entity/action` in the app Router; add/update IO + migration if persistence needed.

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

## App wrappers

MyJourney: skill `myjourney-rest-endpoint` — IO, Router, frontend `$svc('ajax')` only.
