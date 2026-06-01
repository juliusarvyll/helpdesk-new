<?php

namespace App\Http\Controllers;

use App\Http\Models\Admin\Department;
use App\Http\Models\Admin\IssueCategory;
use App\Http\Models\Admin\IssueList;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use App\Http\Models\Reports;
use App\Http\Models\Ticket;
use App\User;
use Auth;
use Config;
use Illuminate\Http\Request;
use PDF;
use Session;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        Menu::menu_controller();
        Config::set('adminlte.plugins.daterangepicker.js', true);
        $selected = '';
        $technical = User::where('role', 'admin')->where('status', 1)->where('is_deleted', 0)->get();
        $department = Department::all();
        $category = IssueCategory::all();
        $issues = IssueList::optgroup();
        SystemLogs::saveLogs('visited create report form!');

        return view('reports.index', compact('department', 'category', 'issues', 'technical', 'selected'));
    }

    public function generate(Request $request)
    {
        $selected = $request->selected;
        $data = new \stdClass;
        $data->selected = 'client';
        $data->range = $request->range;
        $data->department = Auth::user()->department;
        $data->status = $request->status;
        $data->category = $request->category;
        $data->issues = $request->issues;
        $data->technical_support = $request->technical_support;

        $date = explode(' - ', $request->range);

        $start = date('d/m/Y h:i:s A', strtotime($date[0]));
        $end = date('d/m/Y h:i:s A', strtotime($date[1]));

        $generated = Reports::generate($data);
        $count_generated = Reports::count_generate($data);

        if (! $generated) {
            return redirect()->back()->withInput();
        }

        $pdf = PDF::loadView('reports.status', compact('generated', 'count_generated', 'start', 'end'))->setPaper('a4', 'landscape');

        return $pdf->stream('department-report', '.pdf');

    }

    public function details($id)
    {

        $data = Ticket::find($id);

        if (Auth::user()->role != 'admin') {

            if (Auth::id() != $data->client_id) {

                SystemLogs::saveLogs('Your not allowed to access this page!');
                $msg = "<strong><font size='3' color='red'> Your not allowed to access this page! </font></strong>";
                Session::flash('msg', $msg);

                return redirect()->back()->withInput();

            }

        }

        if (! $data) {

            SystemLogs::saveLogs('No ticket #'.str_pad($id, 4, '0', STR_PAD_LEFT).' found!');
            $msg = "<strong><font size='3' color='red'> No ticket #".str_pad($id, 4, '0', STR_PAD_LEFT).' found! </font></strong>';
            Session::flash('msg', $msg);

            return redirect()->back()->withInput();

        }

        $pdf = PDF::loadView('admin.reports.details', compact('data'))->setPaper('a4', 'portrait');

        return $pdf->stream('ticket-report','.pdf');

    }
}
