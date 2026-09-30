<?php

namespace Packstub\FormBuilder;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Packstub\FormBuilder\Commands\PruneCommand;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Fields\ChoiceSources;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Http\Controllers\DownloadFileController;
use Packstub\FormBuilder\Http\Controllers\EmbedScriptController;
use Packstub\FormBuilder\Http\Controllers\FormDefinitionController;
use Packstub\FormBuilder\Http\Controllers\ShowFormController;
use Packstub\FormBuilder\Http\Controllers\ShowShareLinkController;
use Packstub\FormBuilder\Http\Controllers\SubmitFormController;
use Packstub\FormBuilder\Http\Controllers\UnlockFormController;
use Packstub\FormBuilder\Http\Controllers\ValidateFormController;
use Packstub\FormBuilder\Listeners\DispatchToSinks;
use Packstub\FormBuilder\Listeners\DispatchWebhook;
use Packstub\FormBuilder\Listeners\SendAutoresponder;
use Packstub\FormBuilder\Listeners\SendChannelMessages;
use Packstub\FormBuilder\Listeners\SendPanelNotifications;
use Packstub\FormBuilder\Listeners\SendSubmissionNotifications;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Livewire\LivewireAssets;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Uploads\Uploads;
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
            ->hasCommand(PruneCommand::class)
            ->hasMigrations(['create_form_builder_tables', 'add_logic_and_webhooks_to_form_builder_tables', 'add_share_links_and_owner_to_form_builder_tables'])
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

        $this->app->singleton(ChoiceSources::class, function (): ChoiceSources {
            return (new ChoiceSources)->register((array) config('packstub-form-builder.choice_sources', []));
        });

        $this->app->singleton(FormBuilder::class);
    }

    public function packageBooted(): void
    {
        Event::listen(SubmissionReceived::class, SendSubmissionNotifications::class);
        Event::listen(SubmissionReceived::class, SendAutoresponder::class);
        Event::listen(SubmissionReceived::class, SendPanelNotifications::class);
        Event::listen(SubmissionReceived::class, DispatchWebhook::class);
        Event::listen(SubmissionReceived::class, SendChannelMessages::class);
        Event::listen(SubmissionReceived::class, DispatchToSinks::class);

        FormBuilder::submissionModel()::deleting(fn (FormSubmission $submission) => Uploads::deleteFor($submission));

        Blade::componentNamespace('Packstub\\FormBuilder\\View\\Components', 'form-builder');
        Livewire::component('form-builder', FormBuilderForm::class);

        // The Livewire renderer's stylesheet: published by `filament:assets`,
        // linked by LivewireAssets on the pages that need it, never by panels.
        FilamentAsset::register([
            Css::make(LivewireAssets::STYLESHEET, __DIR__.'/../resources/dist/livewire.css')->loadedOnRequest(),
        ], LivewireAssets::PACKAGE);

        Event::listen(RequestHandled::class, [LivewireAssets::class, 'inject']);

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
        $throttled = array_values(array_filter([...$middleware, $throttle ? "throttle:{$throttle}" : null]));

        Route::prefix($prefix)->name('packstub-form-builder.')->group(function () use ($middleware, $throttled): void {
            Route::get('files/{submission}/{field}/{index?}', DownloadFileController::class)
                ->where('submission', '[0-9]+')
                ->where('index', '[0-9]+')
                ->name('file');

            Route::post('{form}', SubmitFormController::class)
                ->middleware($throttled)
                ->name('submit');

            Route::post('{form}/validate', ValidateFormController::class)
                ->middleware($middleware)
                ->name('validate');

            Route::post('{form}/unlock', UnlockFormController::class)
                ->middleware($throttled)
                ->name('unlock');

            Route::get('{form}/definition', FormDefinitionController::class)
                ->middleware($middleware)
                ->name('definition');

            if (config('packstub-form-builder.routes.embed', true)) {
                Route::get('{form}/embed.js', EmbedScriptController::class)
                    ->name('embed');
            }

            if (config('packstub-form-builder.routes.page', true)) {
                Route::get('{form}', ShowFormController::class)
                    ->middleware((array) config('packstub-form-builder.routes.page_middleware', ['web']))
                    ->name('show');
            }
        });

        $share = trim((string) config('packstub-form-builder.routes.share_prefix', 'f'), '/');

        if ($share !== '' && config('packstub-form-builder.routes.page', true)) {
            Route::get($share.'/{token}', ShowShareLinkController::class)
                ->middleware((array) config('packstub-form-builder.routes.page_middleware', ['web']))
                ->where('token', '[A-Za-z0-9]+')
                ->name('packstub-form-builder.share');
        }
    }
}
