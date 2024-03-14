<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $table = 'mektep_message';

    protected $primaryKey = 'id_mes';
    public $timestamps = false;

    protected $fillable = [
        'otpravitel_id',
        'poluchatel_id',
        'tema',
        'text',
        'data_otpravki',
        'date_server',
        'otpravitel_action',
        'poluchatel_action',
        'child_id',
        'id_mektep'
    ];
}
