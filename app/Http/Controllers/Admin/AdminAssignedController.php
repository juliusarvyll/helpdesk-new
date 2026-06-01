<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\EmailSender;
use App\Http\Models\Admin\IssueCategory;
use App\Http\Models\Admin\IssueList;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use App\Http\Models\Ticket;
use App\User;
use Auth;
use Carbon\Carbon;
use Config;
use Illuminate\Http\Request;
use Validator;

class AdminAssignedController extends Controller
{
    public function index()
    {

        Menu::menu_controller();
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        Config::set('adminlte.plugins.select2.js', true);
        Config::set('adminlte.plugins.datatables', true);

        $data = Ticket::technical_assigment_active(Auth::id());

        $count_active = Ticket::technical_assigment_count_active(Auth::id());
        $count_closed = Ticket::technical_assigment_count_closed(Auth::id());

        SystemLogs::saveLogs('admin visited all active  tickets list!');
        $technical = User::where('role', 'admin')->where('status', 1)->where('is_deleted', 0)->get();

        return view('admin.assigned.index', compact('data', 'count_active', 'count_closed', 'technical'));

    }

    public function details(Request $request, $id)
    {

        Menu::menu_controller();

        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        Config::set('adminlte.plugins.select2.js', true);
        // Config::set('adminlte.plugins.apprise', true);

        $issues = IssueList::optgroup();
        $data = Ticket::find($id);

        if ($data) {

            if ($data->client_id == Auth::id() || $data->created_ticket == Auth::user()->name || Auth::user()->role == 'admin') {

                $count_active = Ticket::count_active(null);
                $count_closed = Ticket::count_closed(null);
                SystemLogs::saveLogs('visited user ticket active list!');

                return view('admin.assigned.details', compact('data', 'count_active', 'count_closed', 'issues'));

            }

        }
    }

    public function start(Request $request)
    {

        $find = Ticket::find(decrypt($request->id));

        $data = Ticket::find(decrypt($request->id));

        $mytime = Carbon::now();

        $data->start_time = $mytime->toDateTimeString();

        switch ($find->status) {
            case 'overdue':
                $status = 'overdue/in progress';
                break;
            case 'pending':
                $status = 'pending/in progress';
                break;

            default:
                $status = 'in progress';
                break;
        }

        $data->status = $status;

        if ($data->save()) {
            SystemLogs::saveLogs('technical support successfully updated to start issue/problem in ticket #'.$data->OrderNo.' subject '.$request->subject.'!');
            $msg = 'Successfully updated issue/problem of ticket #'.$data->OrderNo.' subject '.$request->subject.' <br><b>  You can now start your ticket!  </b><br>';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/assigned/ticket/view/'.$data->id)]);
        }

    }

    public function end(Request $request)
    {

        $find = Ticket::find(decrypt($request->id));

        $data = Ticket::find(decrypt($request->id));

        $array = [];

        if (! $find->issue) {
            $array['issues'] = 'required';
        }
        if (! $find->technical_support_remarks) {
            $array['remarks'] = 'required';
        }

        $validation = Validator::make($request->all(), $array);

        if ($validation->passes()) {

            if (! $find->issue) {
                $issues = IssueList::find($request->issues);
                $category = IssueCategory::find($issues->issue_category_id);
            }
            $mytime = Carbon::now();

            if (! $find->issue) {
                $data->category = $category->name;
                $data->issue = $issues->issue;
                $data->issue_id = $issues->id;
            }
            $data->end_time = $mytime->toDateTimeString();
            $data->technical_support_remarks = $request->remarks;

            switch ($find->status) {
                case 'overdue':
                    $status = 'overdue/closed';
                    break;
                case 'pending':
                    $status = 'pending';
                    break;
                case 'overdue/in progress':
                    $status = 'overdue/closed';
                    break;
                case 'pending/in progress':
                    $status = 'pending/closed';
                    break;

                default:
                    $status = 'closed';
                    break;
            }

            if ($request->action == 'pending') {

                $data->status = 'pending';
            } else {

                $data->status = $status;
            }

            if ($data->save()) {

                if ($data->status == 'closed') {
                    $client = User::find($data->client_id);
                    $obj = new \stdClass;
                    $obj->ticket_no = $data->OrderNo;
                    $obj->receiver = $data->client;
                    $obj->sender = Auth::user()->name;
                    $obj->receiver_email = $client->email;

                    EmailSender::send($obj);

                    SystemLogs::saveLogs('technical support successfully updated to '.$data->status.' issue/problem in ticket #'.$data->OrderNo.' subject '.$request->subject.'!');
                    $msg = 'Successfully updated issue/problem of ticket #'.$data->OrderNo.' subject '.$request->subject.' <br><b>  Your ticket is successfully '.$data->status.'!  </b><br>';
                    $request->session()->flash('msg', $msg);

                    return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/assigned/tickets/closed')]);
                } else {
                    SystemLogs::saveLogs('technical support successfully updated to '.$data->status.' issue/problem in ticket #'.$data->OrderNo.' subject '.$request->subject.'!');
                    $msg = 'Successfully updated issue/problem of ticket #'.$data->OrderNo.' subject '.$request->subject.' <br><b>  Your ticket is '.$data->status.'!  </b><br>';
                    $request->session()->flash('msg', $msg);

                    return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/assigned/tickets/closed')]);
                }

            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);

    }

    public function pending(Request $request)
    {

        $find = Ticket::find(decrypt($request->id));

        $data = Ticket::find(decrypt($request->id));

        $array = [];

        if (! $find->issue) {
            $array['issues'] = 'required';
        }
        if (! $find->technical_support_remarks) {
            $array['remarks'] = 'required';
        }

        $validation = Validator::make($request->all(), $array);

        if ($validation->passes()) {

            if (! $find->issue) {
                $issues = IssueList::find($request->issues);
                $category = IssueCategory::find($issues->issue_category_id);
            }
            $mytime = Carbon::now();

            if (! $find->issue) {
                $data->category = $category->name;
                $data->issue = $issues->issue;
                $data->issue_id = $issues->id;
            }
            $data->end_time = $mytime->toDateTimeString();
            $data->technical_support_remarks = $request->remarks;

            switch ($find->status) {
                case 'overdue':
                    $status = 'overdue/closed';
                    break;
                case 'closed':
                    $status = 'closed';
                    break;
                case 'overdue/in progress':
                    $status = 'overdue/closed';
                    break;
                case 'pending/in progress':
                    $status = 'pending/closed';
                    break;

                default:
                    $status = 'pending';
                    break;
            }

            if ($request->action == 'closed') {

                $data->status = 'pending/closed';
            } else {

                $data->status = $status;
            }

            if ($data->save()) {

                $client = User::find($data->client_id);
                $obj = new \stdClass;
                $obj->ticket_no = $data->OrderNo;
                $obj->receiver = $data->client;
                $obj->sender = Auth::user()->name;
                $obj->receiver_email = $client->email;

                EmailSender::send($obj);

                SystemLogs::saveLogs('technical support successfully updated to '.$data->status.' issue/problem in ticket #'.$data->OrderNo.' subject '.$request->subject.'!');
                $msg = 'Successfully updated issue/problem of ticket #'.$data->OrderNo.' subject '.$request->subject.' <br><b>  Your ticket is successfully '.$data->status.'!  </b><br>';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/assigned/tickets/closed')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);

    }

    public function closed()
    {

        Config::set('adminlte.plugins.datatables', true);

        $data = Ticket::technical_assigment_closed(Auth::id());

        $count_active = Ticket::technical_assigment_count_active(Auth::id());
        $count_closed = Ticket::technical_assigment_count_closed(Auth::id());

        SystemLogs::saveLogs('admin visited all closed  tickets list!');
        Menu::menu_controller();

        return view('admin.assigned.closed',compact('data','count_active','count_closed'));

    }
}
