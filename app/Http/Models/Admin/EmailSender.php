<?php

namespace App\Http\Models\Admin;

use App\Mail\EmailNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class EmailSender extends Model
{
    public static function send($data)
    {
        set_time_limit(0);
        $objNotification = new \stdClass;
        $objNotification->ticket_no = $data->ticket_no;
        $objNotification->sender = $data->sender;
        $objNotification->receiver = $data->receiver;

        Mail::to($data->receiver_email)->send(new EmailNotification($objNotification));
    }
}
