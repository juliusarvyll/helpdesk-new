<?php

namespace App\Http\Models\Admin;

use Auth;
use DB;
use Illuminate\Database\Eloquent\Model;
use Soumen\Agent\Agent;

class SystemLogs extends Model
{
    protected $table = 'system_logs';

    public static function saveLogs($logs)
    {
        $uid = Auth::user()->id;
        $name = Auth::user()->name;
        $save = new SystemLogs;
        $save->user_id = $uid;
        $save->name = $name;
        $save->ip_address = Agent::ip();
        $save->mac_address = '';
        $save->message = $logs;
        $save->save();
    }

    public static function activity_trail_all()
    {
        $find = DB::select('SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 1000');

        return $find;
    }

    public static function find_activity_trail($id)
    {
        $find = DB::table('system_logs')->where('user_id', $id)->orderBy('created_at', 'desc')->get();

        return $find;
    }
}
