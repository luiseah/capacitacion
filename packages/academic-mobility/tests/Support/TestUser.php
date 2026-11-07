<?php

namespace Module\AcademicMobility\Tests\Support;

use Illuminate\Foundation\Auth\User;

/** In-memory authenticatable standing in for the host's user model. */
class TestUser extends User
{
    protected $guarded = [];
}
