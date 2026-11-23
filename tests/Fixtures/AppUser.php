<?php

namespace Soara\Debugger\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A user model on a custom table with UUID keys and different column names. */
class AppUser extends Authenticatable
{
    use HasUuids;

    protected $table = 'app_users';

    protected $guarded = [];
}
