<?php

namespace App\Http\Controllers;

use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use App\Http\Models\Ticket;
use Auth;
use Config;
use Illuminate\Http\Request;
use Validator;

class TicketController extends Controller
{
    public function index()
    {

        Config::set('adminlte.plugins.datatables', true);
        $data = Ticket::active(Auth::id());
        $count_active = Ticket::count_active(Auth::id());
        $count_closed = Ticket::count_closed(Auth::id());
        SystemLogs::saveLogs('visited user ticket active list!');
        Menu::menu_controller();

        return view('ticket.index', compact('data', 'count_active', 'count_closed'));

    }

    public function closed()
    {

        Config::set('adminlte.plugins.datatables', true);
        $data = Ticket::closed(Auth::id());
        $count_active = Ticket::count_active(Auth::id());
        $count_closed = Ticket::count_closed(Auth::id());
        SystemLogs::saveLogs('visited user ticket closed list!');
        Menu::menu_controller();

        return view('ticket.closed', compact('data', 'count_active', 'count_closed'));

    }

    public function create()
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        $count_active = Ticket::count_active(Auth::id());
        $count_closed = Ticket::count_closed(Auth::id());
        SystemLogs::saveLogs('visited client create ticket form!');
        Menu::menu_controller();

        return view('ticket.create', compact('count_active', 'count_closed'));
    }

    public function store(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'subject' => 'required',
            'description' => 'required',
            'location' => 'required',
        ]);

        if ($validation->passes()) {

            $data = new Ticket;

            $data->subject = $request->subject;
            $data->description = $request->description;
            $data->location = $request->location;
            $data->client = Auth::user()->name;
            $data->department = Auth::user()->department;
            $data->position = Auth::user()->position;
            $data->role = Auth::user()->role;
            $data->client_id = Auth::id();
            $data->status = 'active';
            $data->created_ticket = Auth::user()->name;

            if ($data->save()) {
                SystemLogs::saveLogs('successfully created #'.$data->OrderNo.' subject '.$request->subject.' as new ticket!');
                $msg = 'Created ticket #'.$data->OrderNo.' subject '.$request->subject.' as new ticket!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('ticket')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function details(Request $request, $id)
    {

        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        $data = Ticket::find($id);

        if ($data) {

            if (Auth::user()->role == 'admin' || Auth::user()->role == 'technical') {

                // $count_active = Ticket::count_active(Auth::id());
                // $count_closed = Ticket::count_closed(Auth::id());
                // SystemLogs::saveLogs('visited user ticket active list!');
                // Menu::menu_controller();
                // return view('ticket.details',compact('data','count_active','count_closed'));
                return redirect('admin/ticket/view/'.$id);
            } else {

                if ($data->client_id == Auth::id() || $data->created_ticket == Auth::user()->name) {

                    $count_active = Ticket::count_active(Auth::id());
                    $count_closed = Ticket::count_closed(Auth::id());
                    SystemLogs::saveLogs('visited user ticket active list!');
                    Menu::menu_controller();

                    return view('ticket.details', compact('data', 'count_active', 'count_closed'));

                }

            }

        }

        $msg = 'Your not allowed to access this ticket!';
        $request->session()->flash('msg', $msg);
        SystemLogs::saveLogs(''.Auth::user()->name.' is trying to access other tickets #'.request()->segment(2).'!');

        return redirect('ticket');

    }

    public function close(Request $request)
    {

        $data = Ticket::find(decrypt($request->id));

        if ($data) {

            if ($data->client_id == Auth::id() || $data->created_ticket == Auth::user()->name) {

                $data->client_confirmation = 1;

                switch ($data->status) {
                    case 'pending':
                        $data->status = 'pending/closed';

                        break;
                    case 'overdue':
                        $data->status = 'overdue/closed';

                        break;
                    default:
                        $data->status = 'closed';
                        break;
                }

                if ($data->save()) {
                    SystemLogs::saveLogs('successfully confirm #'.$data->OrderNo.' subject '.strtolower($data->subject).' closed!');
                    $msg = 'Confirm ticket #'.$data->OrderNo.' subject '.strtolower($data->subject).' closed!';
                    $request->session()->flash('msg', $msg);

                    return response()->json(['success' => true, 'message' => 'record added', 'url' => url('ticket/'.$data->id)]);
                }

            }

        }

        $msg = 'Your not allowed to access this ticket!';
        $request->session()->flash('msg', $msg);
        SystemLogs::saveLogs(''.Auth::user()->name.' is trying to access other tickets #'.request()->segment(2).'!');

        return response()->json(['success' => true, 'message' => $msg, 'url' => url('ticket/'.$data->id)]);

    }

    public function rate_my_support(Request $request)
    {

        $data = Ticket::find(decrypt($request->id));

        if ($data) {

            if ($data->client_id == Auth::id() || $data->created_ticket == Auth::user()->name) {

                $validation = Validator::make($request->all(), [
                    'comment' => 'required',
                ]);

                if ($validation->passes()) {

                    $data->client_comments = $request->comment;
                    $data->rate = $request->rate;
                    $data->client_confirmation = 1;

                    if ($data->save()) {
                        SystemLogs::saveLogs('successfully commented and rate ticket #'.$data->OrderNo.' subject '.strtolower($data->subject).'!');
                        $msg = 'commented and rate ticket #'.$data->OrderNo.' subject '.strtolower($data->subject).'!';
                        $request->session()->flash('msg', $msg);

                        return response()->json(['success' => true, 'message' => 'record added', 'url' => url('ticket/'.$data->id)]);
                    }

                } else {
                    $errors = $validation->errors();
                    $errors = json_decode($errors);

                    return response()->json(['success' => false, 'message' => $errors]);
                }

            }

        }

        $msg = 'Your not allowed to access this ticket!';
        $request->session()->flash('msg', $msg);
        SystemLogs::saveLogs(''.Auth::user()->name.' is trying to access other tickets #'.request()->segment(2).'!');

        return response()->json(['success' => true, 'message' => $msg, 'url' => url('ticket/'.$data->id)]);

    }
}
