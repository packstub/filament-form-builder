<?php

namespace Packstub\FormBuilder\Tests\Fixtures;

use Filament\Panel;

/**
 * The admin panel with workspaces: /admin/{team slug}/forms.
 */
class TeamPanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->tenant(Team::class, slugAttribute: 'slug');
    }
}
