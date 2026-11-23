<?php

namespace Packstub\FormBuilder\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model without Filament's HasTenants, on the same table.
 */
class PlainUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
