<?php
namespace App\Models\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class VpnServiceVpnSession extends Pivot
{
    use HasFactory;

    protected $connection = 'ecloud';
}
