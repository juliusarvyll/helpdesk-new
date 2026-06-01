<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\IssueCategory;
use App\Http\Models\Admin\IssueList;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use App\Http\Models\Ticket;
use App\User;
use Auth;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Validator;

class AdminTicketsController extends Controller
{
    public function index()
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        Config::set('adminlte.plugins.select2.js', true);
        Config::set('adminlte.plugins.datatables', true);
        $data = Ticket::active(null);
        $count_active = Ticket::count_active(null);
        $count_closed = Ticket::count_closed(null);
        SystemLogs::saveLogs('admin visited all active  tickets list!');
        $technical = User::where('role', 'admin')->where('status', 1)->where('is_deleted', 0)->get();
        Menu::menu_controller();

        return view('admin.tickets.index', compact('data', 'count_active', 'count_closed', 'technical'));

    }

    public function closed()
    {

        Config::set('adminlte.plugins.datatables', true);
        $data = Ticket::closed(null);
        $count_active = Ticket::count_active(null);
        $count_closed = Ticket::count_closed(null);
        SystemLogs::saveLogs('admin visited all closed  tickets list!');
        Menu::menu_controller();

        return view('admin.tickets.closed', compact('data', 'count_active', 'count_closed'));

    }

    public function create()
    {

        Config::set('adminlte.plugins.daterangepicker.js', true);
        Config::set('adminlte.plugins.select2.js', true);
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        SystemLogs::saveLogs('visited create ticket form!');
        $users = User::where('is_deleted', 0)->where('status', 1)->get();
        $issues = IssueList::optgroup();
        $technical = User::where('role', 'admin')->where('status', 1)->where('is_deleted', 0)->get();
        // $technical = User::where('status',1)->where('is_deleted',0)->get();
        SystemLogs::saveLogs('admin visited create ticket form!');
        Menu::menu_controller();

        return view('admin.tickets.create', compact('users', 'issues', 'technical'));
    }

    public function store(Request $request)
    {

        $validation = Validator::make($request->all(), [

            'client' => 'required',
            'department' => 'required',
            'position' => 'required',
            'priority' => 'required',
            'status' => 'required',
            'location' => 'required',
            // 'issues' => 'required',
            // 'subject' => 'required',
            // 'description' => 'required',
            // 'technical_support' => 'required',
            // 'remarks' => 'required',

        ]);

        if ($validation->passes()) {
            if ($request->technical_support) {
                $technical_names = self::get_name($request->technical_support);
                $technical_names = implode(', ', $technical_names);
                $technical_id = implode(',', $request->technical_support);
            }

            $client = User::find($request->client);

            if ($request->issues) {

                $issues = IssueList::find($request->issues);
                $category = IssueCategory::find($issues->issue_category_id);
            }

            $datetime = explode('-', $request->date_time_range);

            $data = new Ticket;

            // $data->description = $request->description;
            $data->priority = $request->priority;

            if ($request->issues) {

                $data->subject = $category->name;
                $data->category = $category->name;
                $data->issue = $issues->issue;
                $data->issue_id = $issues->id;

            } else {

                $data->subject = '';
            }
            $data->description = '';
            $data->location = '';
            $data->client = $client->name;
            $data->client_id = $client->id;

            if ($request->technical_support) {

                $data->technical_support = $technical_names;
                $data->technical_support_id = $technical_id;
                $data->support_assignment_status = 'Assigned';
            }
            $data->department = $request->department;
            $data->position = $request->position;
            $data->role = $client->role;
            $data->status = $request->status;
            $data->location = $request->location;

            if ($request->status == 'active' || $request->status == 'overdue' || $request->status == 'in progress') {

                $data->start_time = null;
                $data->end_time = null;

            } else {

                $data->start_time = date('Y-m-d H:i:s', strtotime($datetime[0]));
                $data->end_time = date('Y-m-d H:i:s', strtotime($datetime[1]));
            }

            $data->technical_support_remarks = $request->remarks;
            $data->client_confirmation = 1;
            $data->created_ticket = Auth::user()->name;

            if ($data->save()) {
                SystemLogs::saveLogs('admin successfully created #'.$data->OrderNo.' subject '.$request->subject.' as new ticket!');
                $msg = 'Created ticket #'.$data->OrderNo.' subject '.$request->subject.' as new ticket!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/tickets')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public static function get_name($arrId)
    {

        foreach ($arrId as $key => $value) {
            $data[] = User::find($value)->name;
        }

        return $data;
    }

    public function priority(Request $request)
    {

        $data = Ticket::find($request->id);

        $data->priority = $request->priority;

        if ($data->save()) {
            SystemLogs::saveLogs('admin successfully updated priority level  #'.$data->OrderNo.' subject '.$request->subject.' ticket to '.$data->priority.'!');
            $msg = 'Updated priority level of ticket #'.$data->OrderNo.' subject '.$request->subject.' ticket to '.$data->priority.'!';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/tickets')]);
        }

    }

    public function technical_assignement(Request $request)
    {

        $technical_names = self::get_name($request->technical_support);
        $technical_names = implode(', ', $technical_names);
        $data = Ticket::find($request->id);
        $data->technicalSupportUsers()->sync($request->technical_support ?? []);
        $data->support_assignment_status = $data->technicalSupportUsers()->exists() ? 'Assigned' : 'Not Yet Assigned';

        if (Schema::hasColumn($data->getTable(), 'assigned_at')) {
            $data->assigned_at = $data->technicalSupportUsers()->exists() ? ($data->assigned_at ?? now()) : null;
        }

        if ($data->save()) {
            SystemLogs::saveLogs('admin successfully assigned technical support ('.$technical_names.') to ticket  #'.$data->OrderNo.' subject '.$request->subject.'!');
            $msg = 'Assigned technical support ('.$technical_names.') to ticket #'.$data->OrderNo.' subject '.$request->subject.'!';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/tickets')]);
        }
    }

    public function close(Request $request)
    {

        $status = 'closed';
        $data = Ticket::find($request->id);

        if ($data->category == null) {

            $msg = 'Issue category is not yet fillup by technical support!';
            $request->session()->flash('msg', $msg);
            SystemLogs::saveLogs(''.Auth::user()->name.' failed to update status issue category is empty ticket#'.$data->OrderNo.'!');

            return response()->json(['success' => true, 'message' => $msg, 'url' => url('admin/tickets')]);
        }

        if ($data->issue == null) {

            $msg = 'Issue is not yet fillup by technical support!';
            $request->session()->flash('msg', $msg);
            SystemLogs::saveLogs(''.Auth::user()->name.' failed to update status issue is empty ticket#'.$data->OrderNo.'!');

            return response()->json(['success' => true, 'message' => $msg, 'url' => url('admin/tickets')]);
        }

        if ($data->start_time == null && $data->end_time == null) {

            $msg = 'Time duration is not yet is not set!';
            $request->session()->flash('msg', $msg);
            SystemLogs::saveLogs(''.Auth::user()->name.' failed to update status time duration is not yet set ticket#'.$data->OrderNo.'!');

            return response()->json(['success' => true, 'message' => $msg, 'url' => url('admin/tickets')]);
        }

        switch ($data->status) {
            case 'pending':
                $status = 'pending/closed';
                break;
            case 'overdue':
                $status = 'overdue/closed';
                break;
            default:
                $status = 'closed';
                break;
        }

        $data->status = $status;
        $data->client_confirmation = 1;
        // $data->

        if ($data->save()) {
            SystemLogs::saveLogs('admin successfully update ticket  #'.$data->OrderNo.' subject '.$request->subject.' status to '.$status.'!');
            $msg = 'Updated status  of ticket #'.$data->OrderNo.' subject '.$request->subject.' ticket to '.$status.'!';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/tickets')]);
        }

    }

    public function details(Request $request, $id)
    {

        $data = Ticket::find($id);

        if ($data) {

            if ($data->client_id == Auth::id() || $data->created_ticket == Auth::user()->name || Auth::user()->role == 'admin') {

                $count_active = Ticket::count_active(null);
                $count_closed = Ticket::count_closed(null);
                SystemLogs::saveLogs('visited user ticket active list!');
                Menu::menu_controller();

                return view('admin.tickets.details', compact('data', 'count_active', 'count_closed'));

            }

        }

        $msg = 'Your not allowed to access this ticket!';
        $request->session()->flash('msg', $msg);
        SystemLogs::saveLogs(''.Auth::user()->name.' is trying to access other tickets #'.$data->OrderNo.'!');

        return redirect()->back();

    }
}
