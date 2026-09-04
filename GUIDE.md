# Guide d'utilisation du template

---

## 1. Démarrer un nouveau projet

```bash
# Créer le projet Laravel
composer create-project laravel/laravel mon-projet
cd mon-projet

# Installer Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Installer Scramble (documentation API générée automatiquement)
composer require dedoc/scramble

# Copier le template
cp -r /chemin/vers/laravel-api-template/app/Shared      app/
cp -r /chemin/vers/laravel-api-template/app/Traits      app/
cp -r /chemin/vers/laravel-api-template/app/Http        app/
cp -r /chemin/vers/laravel-api-template/app/Modules     app/
cp -r /chemin/vers/laravel-api-template/app/Providers   app/
cp -r /chemin/vers/laravel-api-template/app/Console     app/
cp    /chemin/vers/laravel-api-template/routes/api-modules.php routes/
cp -r /chemin/vers/laravel-api-template/stubs           .
```

---

## 2. Configurer le projet

### .env
```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
```

### bootstrap/app.php
```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api-modules.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'cors'           => \App\Http\Middleware\Cors::class,
            'secure.headers' => \App\Http\Middleware\SecureHeaders::class,
            'validate.json'  => \App\Http\Middleware\ValidateJsonRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
```

### Enregistrer la commande make:module (config/app.php ou Kernel.php selon version)
```php
// Le framework découvre automatiquement les commandes dans app/Console/Commands/
// Rien à faire si tu utilises Laravel 11+
```

---

## 3. Créer les migrations de base

```bash
php artisan make:migration create_users_table
php artisan make:migration create_roles_table
php artisan make:migration create_permissions_table
php artisan make:migration create_role_user_table
php artisan make:migration create_permission_role_table
```

**users** :
```php
$table->id();
$table->string('name');
$table->string('email')->unique();
$table->string('password');
$table->string('status')->default('active');
$table->unsignedBigInteger('tenant_id')->nullable()->index();
$table->timestamp('email_verified_at')->nullable();
$table->rememberToken();
$table->timestamps();
```

**roles** :
```php
$table->id();
$table->string('libelle');
$table->string('code')->unique();
$table->text('description')->nullable();
$table->timestamps();
```

**permissions** :
```php
$table->id();
$table->string('libelle');
$table->string('code')->unique();
$table->text('description')->nullable();
$table->timestamps();
```

**role_user** :
```php
$table->foreignId('role_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->timestamps();
```

**permission_role** :
```php
$table->foreignId('permission_id')->constrained()->cascadeOnDelete();
$table->foreignId('role_id')->constrained()->cascadeOnDelete();
```

```bash
php artisan migrate
```

---

## 4. Créer un nouveau module

C'est là que la commande entre en jeu. Une seule commande génère **tout** :

```bash
php artisan make:module Article
```

**Ce qui est généré automatiquement :**
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
├── Routes/api.php
└── Services/ArticleService.php
```

**La commande affiche aussi les étapes suivantes :**
```
1. Créer la migration :
   php artisan make:migration create_articles_table

2. Enregistrer dans AppServiceProvider :
   $this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);
   Gate::policy(Article::class, ArticlePolicy::class);

3. Routes disponibles automatiquement à :
   GET    /api/v1/articles
   POST   /api/v1/articles
   ...

4. Permissions à créer en base :
   view_articles, create_articles, update_articles, delete_articles
```

---

## 5. Adapter les fichiers générés

Après `make:module`, il suffit de personnaliser :

### Model — ajouter les colonnes et relations
```php
// app/Modules/Article/Models/Article.php
protected $fillable = [
    'title',
    'content',
    'status',
    'tenant_id',
    'user_id',
];

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

### Repository — ajouter les colonnes searchables
```php
// app/Modules/Article/Repositories/ArticleRepository.php
protected array $searchableColumns = ['title', 'content'];
protected array $defaultRelations  = ['user'];
```

### Requests — ajouter les règles de validation
```php
// app/Modules/Article/Requests/CreateArticleRequest.php
public function rules(): array
{
    return [
        'title'   => ['required', 'string', 'max:255'],
        'content' => ['required', 'string'],
        'status'  => ['nullable', 'in:active,inactive'],
    ];
}
```

### Resource — exposer les champs à l'API
```php
// app/Modules/Article/Resources/ArticleResource.php
public function toArray(Request $request): array
{
    return [
        'id'         => $this->id,
        'title'      => $this->title,
        'content'    => $this->content,
        'status'     => $this->status,
        'user'       => new UserResource($this->whenLoaded('user')),
        'created_at' => $this->created_at,
    ];
}
```

### AppServiceProvider — enregistrer le module
```php
// app/Providers/AppServiceProvider.php

// register()
$this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);

// boot()
Gate::policy(Article::class, ArticlePolicy::class);
```

---

## 6. Multi-tenancy

Le scope tenant est automatique via `BaseRepository`. Par défaut il cherche la colonne `tenant_id`.

**Si votre projet utilise une autre colonne**, redéfinir dans le repository :
```php
class ArticleRepository extends BaseRepository
{
    protected string $tenantColumn = 'organisation_id'; // changer ici
}
```

**Dans le controller**, récupérer le tenant de l'utilisateur connecté :
```php
$items = $this->service->getAll($this->getTenantId(), $filters, $perPage);
```

`getTenantId()` est dans `BaseController`. Si votre User a une relation différente, surcharger dans BaseController :
```php
protected function getTenantId(): ?int
{
    return auth()->user()?->organisation_id;
}
```

---

## 7. RBAC — Rôles et permissions

### Créer des permissions via seeder
```php
// database/seeders/PermissionSeeder.php
Permission::insert([
    ['libelle' => 'Voir articles',      'code' => 'view_articles'],
    ['libelle' => 'Créer articles',     'code' => 'create_articles'],
    ['libelle' => 'Modifier articles',  'code' => 'update_articles'],
    ['libelle' => 'Supprimer articles', 'code' => 'delete_articles'],
]);
```

### Créer un rôle super admin
```php
$role = Role::create(['libelle' => 'Super Admin', 'code' => 'super_admin']);
// Pas besoin de permissions — isSuperAdmin() bypass tout
```

### Utiliser les policies dans un service
```php
// Vérification manuelle depuis un service
$this->authorize('create', Article::class);
$this->authorize('update', $article);
```

### Vérification dans un controller
```php
// Laravel résout automatiquement la policy via Gate
$this->authorize('create', Article::class);
```

---

## 8. Filtrage dynamique

Les filtres sont passés en query params :

```
GET /api/v1/articles?search=laravel&status=active&sort_by=created_at&sort_direction=desc
GET /api/v1/articles?created_at=>=2024-01-01&per_page=25
GET /api/v1/articles?title=laravel*    (wildcard LIKE)
GET /api/v1/articles?status[]=active&status[]=archived  (IN clause)
```

Dans le controller, déclarer les filtres autorisés :
```php
['filters' => $filters, 'per_page' => $perPage] = $this->getPaginationAndFilters(
    $request,
    ['search', 'status', 'user_id', 'sort_by', 'sort_direction', 'date_debut', 'date_fin']
);
```

---

## 9. Réponses API standardisées

```php
// 200 - lecture
return $this->response(ArticleResource::collection($items), 200, 'Liste des articles.');

// 201 - création
return $this->responseCreated(new ArticleResource($item), 201, 'Article créé.');

// 202 - mise à jour
return $this->responseUpdated(new ArticleResource($item), 202, 'Article modifié.');

// 404
return $this->responseEmpty('Article introuvable.');

// Toutes les erreurs sont gérées automatiquement par execute() dans BaseController
```

---

## 10. Structure d'un module complet (rappel)

```
app/Modules/{NomModule}/
├── Controllers/        → reçoit la requête HTTP, délègue au service
├── Models/             → Eloquent model
├── Policies/           → autorisations (étend BasePolicy)
├── Repositories/
│   ├── Contracts/      → interface (contrat)
│   └── *Repository.php → implémentation (requêtes DB)
├── Requests/           → validation des données entrantes
├── Resources/          → formatage des réponses JSON
├── Routes/api.php      → déclaration des routes
└── Services/           → logique métier (étend BaseService)
```

**Règle :** le controller ne touche pas la DB. Il appelle le service. Le service appelle le repository.
