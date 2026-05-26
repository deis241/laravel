<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name : Nom du module en PascalCase (ex: Article, BlogPost)}';

    protected $description = 'Génère un nouveau module complet (Controller, Service, Repository, Policy, Model, Routes...)';

    /**
     * Correspondance stub → chemin de destination dans le module
     */
    private array $stubsMap = [
        'Controller.stub'          => 'Controllers/{{ModelName}}Controller.php',
        'Model.stub'               => 'Models/{{ModelName}}.php',
        'Policy.stub'              => 'Policies/{{ModelName}}Policy.php',
        'Repository.stub'          => 'Repositories/{{ModelName}}Repository.php',
        'RepositoryInterface.stub' => 'Repositories/Contracts/{{ModelName}}RepositoryInterface.php',
        'Request.stub'             => 'Requests/Create{{ModelName}}Request.php',
        'Resource.stub'            => 'Resources/{{ModelName}}Resource.php',
        'Routes.stub'              => 'Routes/api.php',
        'Service.stub'             => 'Services/{{ModelName}}Service.php',
    ];

    public function handle(): int
    {
        $name = $this->argument('name');

        // Valider que le nom est en PascalCase
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error("Le nom doit être en PascalCase. Exemple: Article, BlogPost, UserProfile");
            return self::FAILURE;
        }

        $vars      = $this->buildVars($name);
        $moduleDir = app_path("Modules/{$name}");

        if (is_dir($moduleDir)) {
            if (!$this->confirm("Le module '{$name}' existe déjà. Écraser les fichiers existants ?", false)) {
                $this->info('Opération annulée.');
                return self::SUCCESS;
            }
        }

        $this->info("Génération du module <comment>{$name}</comment>...");
        $this->newLine();

        $generated = [];
        $stubsPath = base_path('stubs/module');

        foreach ($this->stubsMap as $stubFile => $destinationTemplate) {
            $stubPath = "{$stubsPath}/{$stubFile}";

            if (!file_exists($stubPath)) {
                $this->warn("  Stub introuvable: {$stubFile} — ignoré.");
                continue;
            }

            $destination = $moduleDir . '/' . $this->replace($destinationTemplate, $vars);
            $content     = $this->replace(file_get_contents($stubPath), $vars);

            $this->ensureDirectory(dirname($destination));
            file_put_contents($destination, $content);

            $relativePath = "app/Modules/{$name}/" . $this->replace($destinationTemplate, $vars);
            $this->line("  <info>✓</info> {$relativePath}");
            $generated[] = $relativePath;
        }

        // Générer aussi le UpdateRequest (pas de stub séparé, on adapte CreateRequest)
        $updateRequestPath = "{$moduleDir}/Requests/Update{$name}Request.php";
        $updateContent     = $this->generateUpdateRequest($vars);
        $this->ensureDirectory(dirname($updateRequestPath));
        file_put_contents($updateRequestPath, $updateContent);
        $this->line("  <info>✓</info> app/Modules/{$name}/Requests/Update{$name}Request.php");

        $this->newLine();
        $this->displayNextSteps($vars);

        return self::SUCCESS;
    }

    private function buildVars(string $name): array
    {
        return [
            '{{ModuleName}}'     => $name,
            '{{ModelName}}'      => $name,
            '{{modelName}}'      => Str::camel($name),
            '{{modelNamePlural}}'=> Str::plural(Str::lower($name)),
            '{{permissionPrefix}}'=> Str::plural(Str::snake($name)),
            '{{routePrefix}}'    => Str::plural(Str::kebab($name)),
        ];
    }

    private function replace(string $content, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $content);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function generateUpdateRequest(array $vars): string
    {
        $modelName  = $vars['{{ModelName}}'];
        $moduleName = $vars['{{ModuleName}}'];

        return <<<PHP
<?php

namespace App\Modules\\{$moduleName}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update{$modelName}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            // Ajouter les règles de mise à jour
        ];
    }
}
PHP;
    }

    private function displayNextSteps(array $vars): void
    {
        $modelName       = $vars['{{ModelName}}'];
        $moduleName      = $vars['{{ModuleName}}'];
        $permissionPrefix = $vars['{{permissionPrefix}}'];
        $routePrefix     = $vars['{{routePrefix}}'];

        $this->info("Module <comment>{$moduleName}</comment> généré avec succès !");
        $this->newLine();

        $this->line('<comment>Étapes suivantes :</comment>');
        $this->newLine();

        $this->line('<options=bold>1. Créer la migration :</>');
        $this->line("   php artisan make:migration create_{$permissionPrefix}_table");
        $this->newLine();

        $this->line('<options=bold>2. Enregistrer dans AppServiceProvider :</>');
        $this->line("   // Dans register():");
        $this->line("   \$this->app->bind({$modelName}RepositoryInterface::class, {$modelName}Repository::class);");
        $this->newLine();
        $this->line("   // Dans boot():");
        $this->line("   Gate::policy({$modelName}::class, {$modelName}Policy::class);");
        $this->newLine();

        $this->line('<options=bold>3. Les routes sont disponibles automatiquement à :</>');
        $this->line("   GET    /api/v1/{$routePrefix}");
        $this->line("   POST   /api/v1/{$routePrefix}");
        $this->line("   GET    /api/v1/{$routePrefix}/{id}");
        $this->line("   PUT    /api/v1/{$routePrefix}/{id}");
        $this->line("   DELETE /api/v1/{$routePrefix}/{id}");
        $this->newLine();

        $this->line('<options=bold>4. Permissions à créer en base (seeder) :</>');
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $this->line("   {$action}_{$permissionPrefix}");
        }
    }
}
