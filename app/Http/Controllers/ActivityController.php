<?php

namespace App\Http\Controllers;

use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use App\User;
use Auth;
use Config;

class ActivityController extends Controller
{
    public static function index()
    {

        Config::set('adminlte.plugins.datatables', true);
        $user_id = Auth::id();
        $activity = SystemLogs::find_activity_trail($user_id);
        $data = User::find($user_id);
        SystemLogs::saveLogs('visited system activity logs!');
        Menu::menu_controller();

        return view('activity.index', compact('data', 'activity'));
    }
}
