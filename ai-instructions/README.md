# AI instructions — CORE_PHP

Platform PHP layer — REST framework, `core()`, PDO, session, validation.

**Cursor rule:** `.cursor/rules/core-php-rest.mdc`, `.cursor/rules/encapsulation.mdc` (scoped to `CORE_PHP/**/*.php`).  
**Workflow skill:** `.cursor/skills/core-php-rest-endpoint/SKILL.md`.

| File | Topic |
|------|--------|
| [encapsulation.md](./encapsulation.md) | Repo boundaries, dual context |
| [layering.md](./layering.md) | Stack placement (standalone-safe) |
| [rest-services.md](./rest-services.md) | RestService contract, security, policy, paramSpecs, examples |

Consuming apps extend RestService in their own app REST tree and register routes in their app Router — see the app's thin bridge rule, not duplicated here.

## Maintaining rules & skills

Add or update **`.cursor/rules/`**, **`ai-instructions/`**, and **`.cursor/skills/`** in **this repo** when the change is:

- RestService contract, validation, `core()` patterns, or other **generic PHP platform** behaviour.

Do **not** put framework rules in consuming apps — apps keep thin bridge rules that reference CORE_PHP. See [encapsulation.md](./encapsulation.md), [layering.md](./layering.md).
