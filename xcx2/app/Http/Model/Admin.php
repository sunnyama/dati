<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $table = 'admin_tb';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';
}
