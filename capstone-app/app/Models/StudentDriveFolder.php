<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDriveFolder extends Model
{
    protected $fillable = ['user_id', 'period_id', 'folder_id', 'folder_name'];
}
