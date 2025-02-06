<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessGroup extends Model
{
    protected $fillable = [
        'name', 
        'description', 
        'manager_id', 
    ];
    public function manager()
    {
        return $this->belongsTo(Admin::class, 'manager_id');
    }

    /**
     * Get the admins that belong to the business group.
     */
    public function admins()
    {
        return $this->hasMany(Admin::class);
    }
}