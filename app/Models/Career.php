<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Career extends Model
{
    protected $guarded = ['id'];

    public function appliedUsers()
    {
        return $this->hasMany(ApplyForm::class, 'career_id');
    }
}
