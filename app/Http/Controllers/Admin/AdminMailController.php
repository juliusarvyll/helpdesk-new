<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EmailNotification;
use Illuminate\Support\Facades\Mail;

class AdminMailController extends Controller
{
    public function send($objNotification)
    {
        set_time_limit(0);

        $objNotification = new \stdClass;
        $objNotification->receiver_name = 'Kristian Angelo D. Santiago';
        $objNotification->ticket_no = '000001';
        $objNotification->sender = 'kristianangelosantiago@gmail.com';
        $objNotification->receiver = 'kristianangelosantiago@gmail.com';

        Mail::to('kristianangelosantiago@gmail.com')->send(new EmailNotification($objNotification));
    }
}
