<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [
        'variables' => 'json'
    ];

    public function template() {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id', 'id');
    }

    public function session() {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }
}
