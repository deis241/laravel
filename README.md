# Laravel API Template

Template modulaire pour API Laravel 13 avec authentification Sanctum, RBAC et multi-tenancy.

---

## Stack

- **Laravel 13** + **PHP 8.2+**
- **Laravel Sanctum** — tokens API
- **Architecture modulaire** — chaque feature dans son propre module
- **Repository pattern** — abstraction de la couche data
- **RBAC** — rôles & permissions

---

## Structure

```
app/
├── Shared/                     # Classes de base (héritage)
│   ├── Controllers/BaseController.php
│   ├── Services/BaseService.php
│   ├── Repositories/BaseRepository.php
│   └── Policies/BasePolicy.php
│
├── Traits/                     # Traits réutilisables
│   ├── ApiResponseTrait.php    ← réponses JSON standardisées
│   ├── FilterableTrait.php     ← filtrage dynamique Eloquent
│   ├── PolicyTrait.php         ← helpers d'autorisation
│   ├── HasStates.php           ← gestion des statuts
│   ├── SecurePaginationTrait.php
│   └── SecureValidationTrait.php
│
├── Http/Middleware/
│   ├── Cors.php                ← CORS configurable via CORS_ALLOWED_ORIGINS
│   ├── SecureHeaders.php       ← headers OWASP
│   └── ValidateJsonRequest.php ← validation JSON + limite taille
│
├── Modules/
│   ├── Auth/                   ← login, register, logout, me, forgot/reset password
│   └── User/                   ← CRUD utilisateurs + gestion des rôles
│
└── Providers/AppServiceProvider.php
```

---

## Installation

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

# 4. Configurer .env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080

# 5. Enregistrer les middleware dans bootstrap/app.php
# (voir section Configuration ci-dessous)

# 6. Migrations + seed
php artisan migrate
php artisan db:seed
```

---

## Configuration bootstrap/app.php

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'cors'           => \App\Http\Middleware\Cors::class,
        'secure.headers' => \App\Http\Middleware\SecureHeaders::class,
        'validate.json'  => \App\Http\Middleware\ValidateJsonRequest::class,
    ]);
})
->withRouting(function () {
    Route::middleware('api')
        ->prefix('api')
        ->group(base_path('routes/api.php'));

    Route::middleware('api')
        ->prefix('api')
        ->group(base_path('routes/api-modules.php'));
})
```

---

## Migrations à créer

```bash
php artisan make:migration create_users_table
php artisan make:migration create_roles_table
php artisan make:migration create_permissions_table
php artisan make:migration create_role_user_table
php artisan make:migration create_permission_role_table
```

**Table users** (colonnes minimales) :
```php
$table->id();
$table->string('name');
$table->string('email')->unique();
$table->string('password');
$table->string('status')->default('active');
$table->unsignedBigInteger('tenant_id')->nullable();
$table->timestamp('email_verified_at')->nullable();
$table->rememberToken();
$table->timestamps();
```

**Table roles** :
```php
$table->id();
$table->string('libelle');
$table->string('code')->unique();
$table->text('description')->nullable();
$table->timestamps();
```

**Table permissions** :
```php
$table->id();
$table->string('libelle');
$table->string('code')->unique();
$table->text('description')->nullable();
$table->timestamps();
```

---

## Créer un nouveau module

### 1. Copier les stubs

```
app/Modules/MonModule/
├── Controllers/MonModelController.php   ← stubs/module/Controller.stub
├── Models/MonModel.php                  ← stubs/module/Model.stub
├── Policies/MonModelPolicy.php          ← stubs/module/Policy.stub
├── Repositories/
│   ├── Contracts/MonModelRepositoryInterface.php  ← stubs/module/RepositoryInterface.stub
│   └── MonModelRepository.php                     ← stubs/module/Repository.stub
├── Requests/
│   ├── CreateMonModelRequest.php        ← stubs/module/Request.stub
│   └── UpdateMonModelRequest.php
├── Resources/MonModelResource.php       ← stubs/module/Resource.stub
├── Routes/api.php                       ← stubs/module/Routes.stub
└── Services/MonModelService.php         ← stubs/module/Service.stub
```

### 2. Enregistrer dans AppServiceProvider

```php
// register()
$this->app->bind(MonModelRepositoryInterface::class, MonModelRepository::class);

// boot()
Gate::policy(MonModel::class, MonModelPolicy::class);
```

### 3. Les routes sont chargées automatiquement

Le fichier `routes/api-modules.php` scanne `app/Modules/*/Routes/api.php` automatiquement.

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

## Endpoints Auth

```
POST   /api/v1/auth/login
POST   /api/v1/auth/register
POST   /api/v1/auth/logout          (auth:sanctum)
GET    /api/v1/auth/me              (auth:sanctum)
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
```

## Endpoints Users

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
