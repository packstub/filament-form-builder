<?php

namespace Packstub\FormBuilder;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Http\Controllers\FormDefinitionController;
use Packstub\FormBuilder\Http\Controllers\ShowFormController;
use Packstub\FormBuilder\Http\Controllers\SubmitFormController;
use Packstub\FormBuilder\Listeners\DispatchToSinks;
use Packstub\FormBuilder\Listeners\SendSubmissionNotifications;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FormBuilderServiceProvider extends PackageServiceProvider
{
    public static string $name = 'packstub-form-builder';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$name)
            ->hasTranslations()
            ->hasMigration('create_form_builder_tables')
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('packstub/filament-form-builder');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FieldTypeRegistry::class, function (): FieldTypeRegistry {
            return (new FieldTypeRegistry)->register(config('packstub-form-builder.field_types', []));
        });

        $this->app->singleton(FormBuilder::class);
    }

    public function packageBooted(): void
    {
        Event::listen(SubmissionReceived::class, SendSubmissionNotifications::class);
        Event::listen(SubmissionReceived::class, DispatchToSinks::class);

        Blade::componentNamespace('Packstub\\FormBuilder\\View\\Components', 'form-builder');
        Livewire::component('form-builder', FormBuilderForm::class);

        $this->publishes([
            __DIR__.'/../resources/css/form-builder.css' => public_path('vendor/packstub-form-builder/form-builder.css'),
            __DIR__.'/../resources/js/form-builder.js' => public_path('vendor/packstub-form-builder/form-builder.js'),
        ], 'packstub-form-builder-assets');

        if (config('packstub-form-builder.routes.enabled', true)) {
            $this->registerRoutes();
        }
    }

    protected function registerRoutes(): void
    {
        $prefix = trim((string) config('packstub-form-builder.routes.prefix', 'forms'), '/');
        $middleware = (array) config('packstub-form-builder.routes.middleware', ['web']);
        $throttle = config('packstub-form-builder.submissions.throttle', '10,1');

        Route::prefix($prefix)->name('packstub-form-builder.')->group(function () use ($middleware, $throttle): void {
            Route::post('{form}', SubmitFormController::class)
                ->middleware(array_values(array_filter([...$middleware, $throttle ? "throttle:{$throttle}" : null])))
                ->name('submit');

            Route::get('{form}/definition', FormDefinitionController::class)
                ->middleware($middleware)
                ->name('definition');

            if (config('packstub-form-builder.routes.page', true)) {
                Route::get('{form}', ShowFormController::class)
                    ->middleware((array) config('packstub-form-builder.routes.page_middleware', ['web']))
                    ->name('show');
            }
        });
    }
}
