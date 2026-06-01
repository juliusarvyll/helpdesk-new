<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\MenuController as Menu;
use App\Http\Models\Ticket;
use Auth;
use Config;

class AdminOverviewController extends Controller
{
    public function index()
    {

        $mytickets = Ticket::technical_assigment_active(Auth::id());
        $queues = Ticket::active(null);
        $d1 = Ticket::department_graph(null);
        $priority = Ticket::per_monthly_priority_graph();
        $d2 = Ticket::my_tickets_in_month(null);
        $ticket_solver = Ticket::monthly_top_ticket_solver();

        $category = Ticket::issue_category();
        $category_pie_graph = Ticket::category_pie_graph();
        $category_pie_graph = json_encode($category_pie_graph);

        $overview_summary = Ticket::overview_summary();

        Config::set('adminlte.layout', null);
        Config::set('adminlte.collapse_sidebar', true);
        Config::set('adminlte.plugins.chartjs', true);

        Menu::menu_controller();

        return view('admin.overview.index', compact('mytickets', 'category_pie_graph', 'overview_summary', 'category', 'queues', 'd1', 'd2', 'priority', 'ticket_solver'));
    }

    public function mytickets()
    {

        $mytickets = Ticket::technical_assigment_active(Auth::id());

        $html = view('admin.overview.mytickets', compact('mytickets'))->render();

        return response()->json(['html' => $html]);

    }

    public function queues()
    {
        $queues = Ticket::active(null);
        $html = view('admin.overview.queues', compact('queues'))->render();

        return response()->json(['html' => $html]);
    }

    public function d1()
    {
        $d1 = Ticket::department_graph(null);
        $html = view('admin.overview.d1', compact('d1'))->render();

        return response()->json(['html' => $html]);
    }

    public function priority()
    {
        $priority = Ticket::per_monthly_priority_graph();
        $html = view('admin.overview.priority', compact('priority'))->render();

        return response()->json(['html' => $html]);

    }

    public function d2()
    {
        $d2 = Ticket::my_tickets_in_month(null);
        $html = view('admin.overview.d2', compact('d2'))->render();

        return response()->json(['html' => $html]);

    }

    public function ticket_solver()
    {
        $ticket_solver = Ticket::monthly_top_ticket_solver();
        $html = view('admin.overview.ticket_solver', compact('ticket_solver'))->render();

        return response()->json(['html' => $html]);

    }

    public function category()
    {

        $category = Ticket::issue_category();
        $category_pie_graph = Ticket::category_pie_graph();
        $category_pie_graph = json_encode($category_pie_graph);
        $html = view('admin.overview.category', compact('category', 'category_pie_graph'))->render();

        return response()->json(['html' => $html]);
    }

    public function overview_summary()
    {
        $overview_summary = Ticket::overview_summary();

        return json_encode($overview_summary);
    }
}
