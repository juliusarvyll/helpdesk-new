<?php

namespace App\Http\Controllers;

use App\Http\Models\MenuController as Menu;
use App\Http\Models\Ticket;
use Auth;
use Config;

class ClientDashboardController extends Controller
{
    public function index()
    {

        $mytickets = Ticket::active(Auth::id());
        $queues = Ticket::active(null);
        $d1 = Ticket::department_graph(null);
        $priority = Ticket::priority_graph();
        $d2 = Ticket::my_tickets_in_month(Auth::id());
        $ticket_solver = Ticket::monthly_top_ticket_solver();

        // Config::set('adminlte.layout',Null);
        // Config::set('adminlte.collapse_sidebar', true);

        Menu::menu_controller();

        return view('dashboard.index', compact('mytickets', 'queues', 'd1', 'd2', 'priority', 'ticket_solver'));
    }

    public function mytickets()
    {
        $mytickets = Ticket::active(Auth::id());
        $html = view('dashboard.mytickets', compact('mytickets'))->render();

        return response()->json(['html' => $html]);

    }

    public function queues()
    {
        $queues = Ticket::active(null);
        $html = view('dashboard.queues', compact('queues'))->render();

        return response()->json(['html' => $html]);

    }

    public function d1()
    {
        $d1 = Ticket::department_graph(null);
        $html = view('dashboard.d1', compact('d1'))->render();

        return response()->json(['html' => $html]);
    }

    public function priority()
    {
        $priority = Ticket::priority_graph();
        $html = view('dashboard.priority', compact('priority'))->render();

        return response()->json(['html' => $html]);

    }

    public function d2()
    {
        $d2 = Ticket::my_tickets_in_month(Auth::id());
        $html = view('dashboard.d2', compact('d2'))->render();

        return response()->json(['html' => $html]);

    }

    public function ticket_solver()
    {
        $ticket_solver = Ticket::monthly_top_ticket_solver();
        $html = view('dashboard.ticket_solver', compact('ticket_solver'))->render();

        return response()->json(['html' => $html]);

    }
}
