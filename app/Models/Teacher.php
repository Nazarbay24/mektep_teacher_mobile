<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Tymon\JWTAuth\Facades\JWTAuth;



class Teacher extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $table = 'mektep_teacher';
    public $timestamps = false;


    public function mektep() {
        return $this->hasOne(Mektep::class, 'id_mektep', 'id');
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    public function generateAuthToken($many = false)
    {
        return JWTAuth::fromUser($this);
    }
}
