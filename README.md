# Laravel API Template

Template modulaire pour API Laravel 13 avec authentification Sanctum, RBAC et multi-tenancy.

Ce dépôt n'est **pas** une application Laravel complète : c'est un template (contrôleurs de
base, traits, middlewares, modules d'exemple, migrations) destiné à être injecté dans un
projet Laravel fraîchement créé. Avec Docker, tout ce processus est automatisé en une seule
commande — voir [Démarrage rapide](#démarrage-rapide).

---

## Pourquoi ce template

Chaque nouveau projet API chez Labs241 commençait pareil : `composer create-project`,
copier-coller les mêmes dossiers Shared, Traits, Modules depuis l'ancien projet, réinstaller
Sanctum, retaper les mêmes migrations users/roles/permissions, reconfigurer
`bootstrap/app.php` à la main.

En clonant pour la énième fois le même rituel sur une nouvelle API, ce temps perdu ne créait
aucune valeur — juste de la friction avant de pouvoir enfin coder la vraie feature. Ce rituel
a donc été transformé en template versionné, puis en un entrypoint Docker qui le rejoue tout
seul : un `docker compose up --build`, et le squelette Laravel, Sanctum, les migrations RBAC
et la stack complète (Postgres, Mailpit, nginx) sortent déjà prêts. Ce qui reste à faire,
c'est ce qui compte vraiment : `php artisan make:module`, et la logique métier.

---

## Sommaire

- [Stack](#stack)
- [Prérequis](#prérequis)
- [Démarrage rapide](#démarrage-rapide)
- [Ce que fait le premier démarrage](#ce-que-fait-le-premier-démarrage)
- [Variables d'environnement](#variables-denvironnement)
- [Commandes utiles](#commandes-utiles)
- [Structure du projet](#structure-du-projet)
- [Documentation API](#documentation-api)
- [Architecture](#architecture)
- [Créer un nouveau module](#créer-un-nouveau-module)
- [Réponses API](#réponses-api)
- [Filtrage dynamique](#filtrage-dynamique)
- [Multi-tenancy](#multi-tenancy)
- [RBAC — rôles et permissions](#rbac--rôles-et-permissions)
- [Endpoints fournis](#endpoints-fournis)
- [Dépannage](#dépannage)
- [Installation manuelle (sans Docker)](#installation-manuelle-sans-docker)

---

## Stack

- **Laravel 13** + **PHP 8.4** (image `php:8.4-fpm-alpine`)
- **Laravel Sanctum** — tokens API
- **PostgreSQL 16**
- **Mailpit** — capture des emails en développement
- **Architecture modulaire** — chaque feature dans son propre module (`app/Modules/*`)
- **Repository pattern** — abstraction de la couche data
- **RBAC** — rôles & permissions
- **Scramble** — documentation API OpenAPI générée automatiquement depuis le code

---

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) et Docker Compose v2 (inclus dans Docker
  Desktop, ou `docker-compose-plugin` sous Linux)
- Aucun besoin de PHP, Composer ou Postgres en local : tout tourne dans les conteneurs
- Ports libres sur la machine hôte : **8080** (API), **5432** (Postgres), **8025** et
  **1025** (Mailpit)

---

## Démarrage rapide

```bash
git clone <url-du-dépôt> mon-projet
cd mon-projet
docker compose up --build -d
```

Cette commande construit un projet Laravel complet à partir de ce template, installe les
dépendances, configure la base de données et lance les migrations. Le premier démarrage
prend quelques minutes (téléchargement des paquets Composer) — voir le détail ci-dessous.

Pour suivre la progression en direct :

```bash
docker compose logs -f app
```

Le service est prêt quand `docker compose ps` affiche `app` en `healthy`. L'API est alors
disponible sur **http://localhost:8080** et Mailpit sur **http://localhost:8025**.

Test rapide une fois prêt :

```bash
curl http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"wrong"}'
```

Une réponse JSON (même une erreur d'identifiants) confirme que l'API répond. La documentation
interactive de l'API est disponible sur **http://localhost:8080/docs/api**.

Pour tout arrêter :

```bash
docker compose down          # arrête les conteneurs, garde les données (DB, vendor)
docker compose down -v       # arrête tout ET supprime la base de données (reset complet)
```

---

## Ce que fait le premier démarrage

Au premier `docker compose up --build`, le service `app` (voir
[docker/php/entrypoint.sh](docker/php/entrypoint.sh)) :

1. lance `composer create-project laravel/laravel` dans un répertoire temporaire pour
   obtenir un squelette Laravel 13 neuf ;
2. copie ce squelette dans le projet, puis **réapplique le contenu de ce template
   par-dessus** (`app/`, `routes/`, `stubs/`) — nos fichiers priment sur les défauts
   Laravel (`AppServiceProvider`, `routes/api.php`, etc.) ;
3. installe `laravel/sanctum` et publie sa configuration ;
4. installe `dedoc/scramble` pour générer la documentation API automatiquement ;
5. installe un `bootstrap/app.php` déjà configuré (middlewares `cors`, `secure.headers`,
   `validate.json` + chargement automatique des routes de modules) ;
6. ajoute les migrations de base fournies dans
   [docker/laravel/migrations/](docker/laravel/migrations/) : `users` enrichi de
   `status`/`tenant_id`, `roles`, `permissions`, `role_user`, `permission_role` ;
7. génère `APP_KEY` et exécute les migrations.

Ce travail n'a lieu **qu'une seule fois** : dès que `artisan` existe à la racine du projet
(donc dès le deuxième `docker compose up`), cette étape est ignorée et seul le serveur
démarre. Le dossier est donc modifié sur le disque de la machine hôte (grâce au bind mount
`.:/var/www`) — après le premier démarrage, tu verras apparaître `public/`, `config/`,
`database/`, `bootstrap/`, `artisan`, `composer.json`, etc. dans le dépôt cloné. C'est
normal : ils font maintenant partie du projet Laravel généré.

Le service `queue` (worker de queues) attend que `app` soit `healthy` avant de démarrer,
pour ne pas lancer deux constructions du projet en parallèle.

---

## Variables d'environnement

Deux niveaux distincts :

- **Un `.env` optionnel à la racine du dépôt**, lu par Docker Compose pour la substitution
  de variables (`${DB_DATABASE:-laravel}`, etc. dans [docker-compose.yml](docker-compose.yml)).
  Crée-le **avant** le premier `docker compose up --build` si tu veux changer les valeurs
  par défaut :

  ```env
  DB_DATABASE=mon_projet
  DB_USERNAME=mon_projet
  DB_PASSWORD=change-me
  APP_URL=http://localhost:8080
  FRONTEND_URL=http://localhost:3000
  CORS_ALLOWED_ORIGINS=http://localhost:3000
  ```

  Ce même fichier devient ensuite le `.env` Laravel à l'intérieur du conteneur (le bind
  mount fait que la racine du dépôt et la racine de l'app Laravel sont le même dossier). Les
  valeurs `DB_*`/`MAIL_*` sont de toute façon réinjectées par la section `environment:` de
  `docker-compose.yml`, donc pas de risque de désynchronisation entre Postgres et Laravel.

- **Les autres réglages Laravel** (`APP_NAME`, `LOG_CHANNEL`, `SESSION_DRIVER`, etc.) se
  configurent normalement dans ce même `.env` après le premier démarrage, comme dans
  n'importe quel projet Laravel.

---

## Commandes utiles

Toutes les commandes `artisan` et `composer` s'exécutent **dans le conteneur** :

```bash
# Artisan
docker compose exec app php artisan make:module Article
docker compose exec app php artisan make:migration create_articles_table
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker
docker compose exec app php artisan route:list

# Composer
docker compose exec app composer require <paquet>

# Tests
docker compose exec app php artisan test

# Shell dans le conteneur
docker compose exec app bash

# Logs
docker compose logs -f app
docker compose logs -f queue

# Redémarrer un service après une modification de config
docker compose restart app
```

---

## Structure du projet

Structure du **template** telle que clonée (avant le premier build Docker) :

```
.
├── docker/                     # Dockerfile, entrypoint, config nginx, fichiers de bootstrap
├── docker-compose.yml
├── app/
│   ├── Shared/                 # Classes de base (héritage)
│   │   ├── Controllers/BaseController.php
│   │   ├── Services/BaseService.php
│   │   ├── Repositories/BaseRepository.php
│   │   └── Policies/BasePolicy.php
│   │
│   ├── Traits/                 # Traits réutilisables
│   │   ├── ApiResponseTrait.php    ← réponses JSON standardisées
│   │   ├── FilterableTrait.php     ← filtrage dynamique Eloquent
│   │   ├── PolicyTrait.php         ← helpers d'autorisation
│   │   ├── HasStates.php           ← gestion des statuts
│   │   ├── SecurePaginationTrait.php
│   │   └── SecureValidationTrait.php
│   │
│   ├── Http/Middleware/
│   │   ├── Cors.php                ← CORS configurable via CORS_ALLOWED_ORIGINS
│   │   ├── SecureHeaders.php       ← headers OWASP
│   │   └── ValidateJsonRequest.php ← validation JSON + limite taille
│   │
│   ├── Modules/
│   │   ├── Auth/                   ← login, register, logout, me, forgot/reset password
│   │   └── User/                   ← CRUD utilisateurs + gestion des rôles
│   │
│   └── Providers/AppServiceProvider.php
│
├── routes/
│   ├── api.php
│   └── api-modules.php         # scanne app/Modules/*/Routes/api.php automatiquement
│
└── stubs/module/               # gabarits utilisés par `php artisan make:module`
```

Après le premier `docker compose up --build`, le reste du squelette Laravel (`public/`,
`config/`, `database/`, `bootstrap/`, `resources/`, `vendor/`, `artisan`, `composer.json`,
`.env`) est généré à côté.

Pour le détail de chaque module (contrôleur, service, repository, policy...), voir le
[GUIDE.md](GUIDE.md).

---

## Documentation API

[Scramble](https://scramble.dedoc.co/) génère la documentation OpenAPI de l'API directement
à partir du code (routes, `FormRequest`, `Resource`) — **sans annotations à écrire ni à
maintenir**. Elle se met à jour automatiquement à chaque nouveau module ou endpoint.

Une fois le service démarré, elle est disponible sur :

```
http://localhost:8080/docs/api
```

Le fichier OpenAPI brut (JSON) est accessible sur `/docs/api.json` — utile pour l'importer
dans Postman/Insomnia ou générer un client HTTP.

---

## Architecture

Le template applique la même architecture en couches à **chaque** module, avec une règle
simple : le controller ne touche jamais la DB.

```
Requête HTTP
    │
    ▼
Controller   ← reçoit la requête, valide (Request), délègue, formate la réponse (Resource)
    │
    ▼
Service      ← logique métier : injection du tenant_id, transactions, autorisation (Gate)
    │
    ▼
Repository   ← seule couche qui construit des requêtes Eloquent (interface + implémentation)
    │
    ▼
Model
```

**Inversion de dépendance.** Chaque module expose un contrat
(`Contracts/ArticleRepositoryInterface`) plutôt que d'utiliser directement sa classe
concrète. Le binding se fait au démarrage dans `AppServiceProvider::register()` :

```php
$this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);
```

Les Controllers et Services ne dépendent donc que de l'interface — remplacer l'implémentation
(mock en test, autre source de données) ne demande de toucher qu'un seul binding.

**`BaseController::execute()` centralise la gestion d'erreurs.** Chaque action de controller
s'enveloppe dans un callback ; `execute()` intercepte `AuthorizationException`,
`ModelNotFoundException`, `ValidationException` et toute autre exception pour renvoyer la
réponse JSON standardisée qui va bien — plus aucun try/catch répété dans les controllers.

**`BaseRepository` porte le multi-tenancy et le filtrage**, communs à tous les modules :
scope automatique sur `tenant_id` si la colonne existe (`hasTenantScope()`), tri, recherche
et filtres par plage de dates via `FilterableTrait` — chaque repository n'a plus qu'à
déclarer ses `$searchableColumns` et `$defaultRelations`.

**`BasePolicy` généralise les policies par convention.** Une policy enfant ne déclare qu'un
préfixe de permission (`getPermissionPrefix() → 'articles'`) ; `viewAny`/`view`/`create`/
`update`/`delete` en dérivent automatiquement (`view_articles`, `create_articles`, ...) via
`PolicyTrait`.

**Traits transverses**, appliqués là où c'est nécessaire plutôt que par héritage :

| Trait                     | Rôle                                                   |
|---------------------------|---------------------------------------------------------|
| `ApiResponseTrait`        | réponses JSON standardisées (`response`, `responseCreated`, ...) |
| `FilterableTrait`         | filtrage/tri/recherche dynamiques sur les repositories |
| `PolicyTrait`             | résolution des permissions dans les policies           |
| `HasStates`               | gestion de statuts sur un modèle                       |
| `SecurePaginationTrait`   | bornes de pagination sûres dans les controllers        |
| `SecureValidationTrait`   | helpers de validation dans les controllers             |

**Découpage vertical par module plutôt qu'horizontal par type.** `app/Modules/Article/`
regroupe tout ce qui concerne Article (Controller, Model, Policy, Repository, Requests,
Resource, Routes, Service) au même endroit, au lieu d'éparpiller les fichiers dans
`app/Http/Controllers`, `app/Models`, etc. comme le fait un projet Laravel par défaut — un
module se comprend, se déplace et se supprime d'un bloc. `routes/api-modules.php` scanne
`app/Modules/*/Routes/api.php` automatiquement : un nouveau module n'a rien à enregistrer
côté routing.

---

## Créer un nouveau module

Une seule commande génère tout le squelette d'un module :

```bash
docker compose exec app php artisan make:module Article
```

Ce qui est généré :

```
app/Modules/Article/
├── Controllers/ArticleController.php
├── Models/Article.php
├── Policies/ArticlePolicy.php
├── Repositories/
│   ├── Contracts/ArticleRepositoryInterface.php
│   └── ArticleRepository.php
├── Requests/
│   ├── CreateArticleRequest.php
│   └── UpdateArticleRequest.php
├── Resources/ArticleResource.php
├── Routes/api.php              # chargé automatiquement par routes/api-modules.php
└── Services/ArticleService.php
```

Étapes suivantes (affichées aussi par la commande) :

```bash
docker compose exec app php artisan make:migration create_articles_table
```

Puis enregistrer le repository et la policy dans `app/Providers/AppServiceProvider.php` :

```php
// register()
$this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);

// boot()
Gate::policy(Article::class, ArticlePolicy::class);
```

Le détail complet (personnalisation du Model, du Repository, des Requests, des
Resources) est dans le [GUIDE.md](GUIDE.md#4-créer-un-nouveau-module).

---

## Réponses API

| Méthode            | HTTP | Usage               |
|--------------------|------|---------------------|
| `response()`       | 200  | Lecture              |
| `responseCreated()`| 201  | Création             |
| `responseUpdated()`| 202  | Modification         |
| `responseEmpty()`  | 404  | Ressource introuvable|
| `responseError()`  | 400  | Erreur générique     |

Format standard :
```json
{
  "data": [...],
  "message": "Liste des articles."
}
```

---

## Filtrage dynamique

Tous les repositories héritent de `FilterableTrait`. Dans un controller :

```php
$filters = $this->getFilters($request, [
    'search',          // LIKE sur searchableColumns
    'status',          // égalité
    'sort_by',         // colonne de tri
    'sort_direction',  // asc | desc
    'date_debut',      // >= dateFilterColumn
    'date_fin',        // <= dateFilterColumn
]);
```

Opérateurs supportés dans les valeurs : `>=100`, `!=active`, `john*`

---

## Multi-tenancy

- `BaseRepository` scope automatiquement sur `tenant_id` si la colonne existe
- Pour changer la colonne : redéfinir `$tenantColumn` dans le repository
- `getTenantId()` dans `BaseController` retourne le `tenant_id` de l'utilisateur connecté

---

## RBAC — rôles et permissions

- Tables `roles`, `permissions`, `role_user`, `permission_role` créées automatiquement au
  premier démarrage (voir [docker/laravel/migrations/](docker/laravel/migrations/))
- Un rôle avec le code `super_admin` bypasse toutes les vérifications de permissions
- Vérification dans un controller ou un service : `$this->authorize('create', Article::class)`

Détail des seeders et de la vérification des policies dans le
[GUIDE.md](GUIDE.md#7-rbac--rôles-et-permissions).

---

## Endpoints fournis

### Auth

```
POST   /api/v1/auth/login
POST   /api/v1/auth/register
POST   /api/v1/auth/logout          (auth:sanctum)
GET    /api/v1/auth/me              (auth:sanctum)
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
```

### Users

```
GET    /api/v1/users                (auth:sanctum)
POST   /api/v1/users                (auth:sanctum)
GET    /api/v1/users/{id}           (auth:sanctum)
PUT    /api/v1/users/{id}           (auth:sanctum)
DELETE /api/v1/users/{id}           (auth:sanctum)
GET    /api/v1/users/{id}/roles     (auth:sanctum)
POST   /api/v1/users/{id}/roles     (auth:sanctum)
DELETE /api/v1/users/{id}/roles/{roleId}  (auth:sanctum)
```

---

## Dépannage

**`docker compose ps` reste sur `app` en `starting` / jamais `healthy`**
Regarde les logs (`docker compose logs -f app`) : la première construction télécharge
Laravel et toutes les dépendances Composer, ça peut prendre plusieurs minutes selon la
connexion. Le healthcheck attend jusqu'à 10 minutes avant d'abandonner.

**Erreur de port déjà utilisé (`bind: address already in use`)**
Un autre projet occupe 8080, 5432, 8025 ou 1025. Change le port hôte dans
`docker-compose.yml` (partie gauche du mapping, ex. `"8081:80"`) ou arrête l'autre service.

**Je veux repartir de zéro**
```bash
docker compose down -v
git clean -fdx -e docker -e docker-compose.yml -e .dockerignore -e .gitignore \
  -e README.md -e GUIDE.md -e app -e routes -e stubs
docker compose up --build -d
```
Ceci supprime le projet Laravel généré (vendor, config, migrations exécutées, base de
données) pour tout reconstruire depuis le template.

**Erreur de permissions sur `storage/` ou `bootstrap/cache/`**
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
```

**Modifier `bootstrap/app.php` ou les migrations de base une fois le projet déjà généré**
Ces fichiers ne sont copiés qu'au premier démarrage. Une fois le projet construit, modifie-les
directement dans le dépôt comme n'importe quel fichier Laravel — l'entrypoint ne les
retouchera plus.

---

## Installation manuelle (sans Docker)

Si tu préfères ne pas utiliser Docker (PHP 8.2+, Composer et une base de données installés
en local) :

```bash
# 1. Créer un nouveau projet Laravel
composer create-project laravel/laravel mon-projet

# 2. Copier les dossiers du template
cp -r laravel-api-template/app/Shared      mon-projet/app/
cp -r laravel-api-template/app/Traits      mon-projet/app/
cp -r laravel-api-template/app/Http        mon-projet/app/
cp -r laravel-api-template/app/Modules     mon-projet/app/
cp -r laravel-api-template/app/Providers   mon-projet/app/
cp -r laravel-api-template/routes          mon-projet/

# 3. Installer Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 3bis. Installer Scramble (documentation API)
composer require dedoc/scramble

# 4. Configurer .env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080

# 5. Enregistrer les middleware dans bootstrap/app.php
# (voir docker/laravel/bootstrap-app.php pour un exemple prêt à copier)

# 6. Migrations + seed
php artisan migrate
php artisan db:seed
```

Le détail des colonnes de chaque table de base (`users`, `roles`, `permissions`,
`role_user`, `permission_role`) est dans le [GUIDE.md](GUIDE.md#3-créer-les-migrations-de-base),
et des fichiers de migration prêts à copier existent dans
[docker/laravel/migrations/](docker/laravel/migrations/).

---

Conçu et maintenu par **mapson241** ([deismapangou@gmail.com](mailto:deismapangou@gmail.com)),
avec [Claude Code](https://claude.com/claude-code) comme copilote pour l'architecture Docker
et la documentation.
