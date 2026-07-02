<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

final class TestClub extends Model
{
    public $timestamps = false;

    protected $table = 'clubs';

    protected $guarded = [];
}
