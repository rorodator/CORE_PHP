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
class MonService extends RestService {
    public function get() {
        return $this->success(['data' => 'value']);
    }
}
```

## Structure

- `Core/Base/` : Classes de base (Core, DB, Router, etc.)
- `Core/Functional/` : Fonctionnalités spécifiques (JIRA, etc.)

## Développement

Cette bibliothèque suit les règles de développement du projet MyManager :
- Commentaires en anglais avec style PHPDoc
- Noms de variables, classes, fonctions en anglais
- Aucun appel direct à la base de données, sauf dans les IO
- Tout doit être abstrait via les IO
