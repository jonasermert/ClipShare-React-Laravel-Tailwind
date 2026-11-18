<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'user_id', 'title', 'description', 'filename', 'mime_type'])]
class Video extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';
}
