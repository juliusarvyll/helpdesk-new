<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

class AdminReportsController extends Controller
{
    // https://quickadminpanel.com/blog/reports-and-charts-in-laravel-two-useful-packages/
    // https://github.com/Jimmy-JS/laravel-report-generator
    // https://github.com/barryvdh/laravel-dompdf

    public function index(Request $request, $selected)
    {

        Menu::menu_controller();
        // Config::set('adminlte.plugins.select2.js', true);
        Config::set('adminlte.plugins.daterangepicker.js', true);
        // Config::set('adminlte.plugins.customize.js', true);
        // Config::set('adminlte.plugins.form', true);
        $technical = User::where('role', 'admin')->where('status', 1)->where('is_deleted', 0)->get();
        $department = Department::all();
        $category = IssueCategory::all();
        $issues = IssueList::optgroup();
        SystemLogs::saveLogs('visited create report form!');

        return view('admin.reports.index', compact('department', 'category', 'issues', 'technical', 'selected'));
    }

    public function generate(Request $request, $selected)
    {
        $selected = $request->selected;
        $data = new \stdClass;
        $data->selected = $request->selected;
        $data->range = $request->range;
        $data->department = $request->department;
        $data->status = $request->status;
        $data->category = $request->category;
        $data->issues = $request->issues;
        $data->technical_support = $request->technical_support;

        $date = explode(' - ', $request->range);

        $start = date('d/m/Y h:i:s A', strtotime($date[0]));
        $end = date('d/m/Y h:i:s A', strtotime($date[1]));

        $generated = Reports::generate($data);
        $count_generated = Reports::count_generate($data);

        // echo "<pre>";
        // print_r($count_generated);exit;

        if (! $generated) {
            return redirect()->back()->withInput();
        }

        $pdf = PDF::loadView('admin.reports.'.$selected, compact('generated', 'count_generated', 'start', 'end', 'status'))->setPaper('a4', 'landscape');

        return $pdf->stream($selected.'-report', '.pdf');

    }

    public function displayReport(Request $request)
    {

        $customPaper = [0, 0, 216, 356];

        $pdf = PDF::loadView('admin.reports.issue-pdf-template')->setPaper('a4', 'landscape');

        return $pdf->stream('service-record', '.pdf');

    }

    public function ticket(Request $request)
    {

        Menu::menu_controller();
        $selected = 'Specific Ticket';
        Config::set('adminlte.plugins.datatables', true);
        $data = Ticket::all();
        SystemLogs::saveLogs('visited create report form!');

        return view('admin.reports.ticket', compact('data', 'selected'));
    }

    public function details($id)
    {

        if (Auth::user()->role != 'admin') {

            SystemLogs::saveLogs('Your not allowed to access this page! '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> Your not allowed to access this page! ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return redirect()->back()->withInput();

        }

        $data = Ticket::find($id);

        if (! $data) {

            SystemLogs::saveLogs('No ticket #'.str_pad($id, 4, '0', STR_PAD_LEFT).' found! '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No ticket #".str_pad($id, 4, '0', STR_PAD_LEFT).' found! '.$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return redirect()->back()->withInput();

        }

        $pdf = PDF::loadView('admin.reports.details', compact('data'))->setPaper('a4','portrait');

        return $pdf->stream('ticket-report','.pdf');

    }
}
