# CORE_PHP

Bibliothèque PHP générique et réutilisable pour les projets web.

## Description

CORE_PHP fournit toutes les ressources génériques et réutilisables pour la partie PHP des applications web. Elle garantit que la fonction `core()` est disponible et que le singleton Core est instancié. Tous les services sont donc disponibles partout via `core()`.

## Fonctionnalités principales

- **Services Core** : Système de services centralisé avec `core()`
- **Base de données** : Interface PDO avec adaptateurs
- **Routing** : Système de routage pour les API REST
- **Authentification** : Gestion des utilisateurs connectés
- **Sessions** : Gestion des sessions utilisateur
- **Validation** : Validation des paramètres
- **Intégrations** : Clients JIRA et autres services externes
- **Mail** : interface `MailerInterface`, message builder, drivers null / PHP native
- **REST Services** : Base pour les services REST

## Installation

Cette bibliothèque est incluse dans les projets via des liens symboliques.

## Utilisation

```php
<?php
// Accès aux services
$logService = core('log');
$dbService = core('db');

// Utilisation de la base de données
$users = $dbService->query("SELECT * FROM users");

// Services REST
class ExampleService extends RestService {
    protected SecurityLevel $securityLevel = SecurityLevel::Public;
    protected ?HttpMethod $httpMethod = HttpMethod::Get;

    protected array $security = [
        'auth' => false,
        'public' => true,
        'resource' => null,
        'resourceIdParam' => null,
        'operation' => 'read',
        'visibilityAware' => false,
    ];

    protected function process() {
        return ['data' => ['value' => 'ok'], 'status' => 'SUCCESS'];
    }
}
```

## Mail (`core()->mailer`)

Abstraction d’envoi d’emails, swappable par configuration INI.

### Configuration

```ini
[services]
mailer = Core\Mail\Mailer

[mail]
driver = null          ; null (dev) | php (mail() + MIME)
from_address = "noreply@example.com"
from_name = "My App"
reply_to = "support@example.com"
reply_to_name = "Support"
message_id_host = "example.com"
```

| Driver | Classe | Usage |
|--------|--------|-------|
| `null` (défaut) | `NullMailer` | Log uniquement — safe en dev |
| `php` | `PhpNativeMailer` | `mail()` + MIME multipart (text, HTML, pièces jointes) |

Pour un provider tiers (SendGrid, SES, Mailgun, Postmark, Resend…), implémenter `MailerInterface` dans l’app et l’enregistrer dans `[services] mailer` — **sans dépendance vendor dans CORE_PHP**.

### Exemple

```php
use Core\Mail\MailAttachment;
use Core\Mail\MailMessage;

$message = MailMessage::create()
    ->to('user@example.com', 'Jane Doe')
    ->subject('Bienvenue')
    ->html('<p>Bienvenue sur <strong>My App</strong>.</p>')
    ->text('Bienvenue sur My App.')
    ->attachFile('/path/to/guide.pdf', 'guide.pdf')
    ->replyTo('support@example.com');

$result = core()->mailer->send($message);

if (!$result->success) {
    core()->log->error('Mail failed: ' . $result->error);
}
```

### API

| Type | Rôle |
|------|------|
| `MailerInterface` | Contrat : `isConfigured()`, `send(MailMessage)` |
| `MailMessage` | Builder fluide (to/cc/bcc, text, html, headers, attachments) |
| `MailAddress` | Email + nom affiché |
| `MailAttachment` | Fichier ou contenu inline (`fromPath`, `fromString`) |
| `MailSendResult` | `success`, `messageId`, `error` |

## Rich-text HTML (`Core\Util\RichTextHtml`)

Sanitizer serveur aligné sur le contrat CORE_UX `core-rich-text`. **Ne jamais faire confiance** à l’HTML déjà sanitizé côté navigateur — toujours appeler `RichTextHtml::sanitize()` avant persistance ou rendu. Stocker l’HTML sanitizé ; utiliser `getPlainText()` (textContent DOM brut, sans trim CORE) pour la validation métier — les limites et normalisations (`trim()`, maxlength) restent dans l’application consommatrice.

Détails : [ai-instructions/rich-text-html.md](./ai-instructions/rich-text-html.md).

## Structure

- `Core/Base/` : Classes de base (Core, DB, Router, etc.)
- `Core/Mail/` : Abstraction mail (interface, DTOs, drivers)
- `Core/Functional/` : Fonctionnalités spécifiques (JIRA, etc.)
- `Core/Util/` : Utilitaires génériques (`RichTextHtml`, …)

## Développement

Conventions CORE_PHP :

- Commentaires en anglais avec style PHPDoc
- Noms de variables, classes, fonctions en anglais
- Aucun appel direct à la base de données, sauf dans les IO (dans l’application consommatrice)
- Tout accès persistance passe par les IO de l’application
