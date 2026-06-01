<?php

namespace App\Http\Controllers;

use App\Http\Models\Admin\SystemLogs;
use Auth;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return Renderable
     */
    public function index(Request $request)
    {

        if (Auth::check()) {

            if (Auth::user()->status == 0) {
                Auth::logout();
                $msg = 'Your account has been disabled please see your system administrator!';
                $request->session()->flash('msg', $msg);

                return redirect('login');
            }

            if (Auth::user()->is_deleted == 1) {
                Auth::logout();
                $msg = 'Your account has been deleted please see your system administrator!';
                $request->session()->flash('msg', $msg);

                return redirect('login');
            }

            switch (Auth::user()->role) {
                case 'admin':
                    SystemLogs::saveLogs('login admin account successful!');

                    return redirect('admin/dashboard');
                    break;
                case 'client':
                    SystemLogs::saveLogs('login client account successful!');

                    return redirect('dashboard');
                    break;
                default:
                    // code...
                    break;
            }

        }

    }
}
