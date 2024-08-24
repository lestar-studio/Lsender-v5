<?php

namespace App\Models;

use App\Helpers\Lyn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    public static function record($request, $ops)
    {
        $table = new History();
        $table->user_id = auth()->id();
        $table->session_id = session()->get('main_device');
        $table->receiver = $request->receiver;
        $table->from = $ops['from'];
        $table->status = $ops['status'];
        $table->message_type = $request->message_type;
        return Lyn::genereate_message($table, $request, 'save');
    }

    public static function apiRecord($request, $ops)
    {
        $table = new static();
        $table->user_id = null;
        $table->session_id = $request->device_key;
        $table->receiver = static::validate_receiver("$request->receiver");
        $table->message_type = $request->message_type;
        $table->message = json_encode($request->data);
        $table->from = $ops['from'];
        $table->status = $ops['status'];
        $table->save();
    }

    public static function validate_receiver($number)
    {
        $cleaned_number = preg_replace('/[^0-9]/', '', $number);

        if (substr($cleaned_number, 0, 1) === '+') {
            return '62' . substr($cleaned_number, 1);
        } elseif (substr($cleaned_number, 0, 1) === '0') {
            return '62' . substr($cleaned_number, 1);
        } else {
            return $cleaned_number;
        }
    }
}
