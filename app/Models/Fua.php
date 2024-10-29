<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fua extends Model
{
    use HasFactory;
    protected $table = 'PLATAFORMA.FUA';
    protected $primaryKey = 'IdFUA';
}
