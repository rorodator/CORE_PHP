# Encapsulation — CORE_PHP

**Before adding code, rules, or skills:** confirm it belongs in **CORE_PHP** (generic PHP platform), not a consuming app.

## This repo (CORE_PHP)

| Belongs here | Does not belong here |
|--------------|----------------------|
| `RestService` base, `core()`, validation, mail abstraction | Domain endpoints, IO classes, migrations |
| Platform rules/skills in **this** `.cursor/` | App Router registrations, business SQL |
| Generic lang/ping REST in `Core/Rest/` | Product-specific security rules |

## Sibling repos

- **CORE_JS** / **CORE_UX** — JavaScript stack (parallel to PHP app layer)
- **Consuming app** — `PHP/App/Rest/`, `PHP/App/IO/`, app Router

Details: [layering.md](./layering.md).

## Rules & skills in this repo

When you change the **RestService contract** or PHP platform patterns:

1. Update `.cursor/rules/`, `ai-instructions/` **here**
2. Add/update `.cursor/skills/core-php-rest-endpoint/` if needed
3. Apps extend RestService but keep only **bridge** rules — not duplicated contracts

## Dual context

All paths in CORE_PHP docs are **relative to this repository root**. They work standalone or symlinked (`CORE_PHP/` in an app workspace).

CORE docs must **never require** an app file. App Router/IO steps are described generically in [rest-services.md](./rest-services.md#app-integration-not-core_php).

## App follow-up (outside this repo)

Domain services: subclass `RestService` in the app, register routes in the app Router, implement IO in the app repo.
