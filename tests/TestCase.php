<?php

namespace Packstub\FormBuilder\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Packstub\FormBuilder\FormBuilderServiceProvider;
use Packstub\FormBuilder\Tests\Fixtures\AdminPanelProvider;
use Packstub\FormBuilder\Tests\Fixtures\User;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Config pinned before the application boots, for values the provider
     * reads while registering (routes). Set through rebootWith().
     *
     * @var array<string, mixed>
     */
    public static array $bootConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    protected function tearDown(): void
    {
        static::$bootConfig = [];

        parent::tearDown();
    }

    /**
     * Re-create the application with extra config pinned before it boots.
     *
     * @param  array<string, mixed>  $config
     */
    public function rebootWith(array $config): void
    {
        static::$bootConfig = $config;

        $this->reloadApplication();
        $this->migrate();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LivewireServiceProvider::class,
            FormBuilderServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktv6fZ7c7ZQfF1Zt6Q0Wd9jz5bJ6rKq8nX0m3Yk=');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('packstub-form-builder.spam.min_seconds', 0);
        $app['config']->set('packstub-form-builder.submissions.throttle', null);

        foreach (static::$bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    protected function migrate(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        (include __DIR__.'/../database/migrations/create_form_builder_tables.php.stub')->up();
    }
}
