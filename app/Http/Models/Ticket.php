<?php

namespace App\Http\Models;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    public function getOrderNoAttribute()
    {
        // $model->OrderNo;
        return str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function technicalSupportUsers()
    {
        return $this->belongsToMany(\App\Models\User::class, 'ticket_technical_support', 'ticket_id', 'user_id')
            ->withTimestamps();
    }

    public static function active_ticket_count()
    {
        return 0;
    }

    public static function active($user_id)
    {

        self::checkOverDue();

        $status = ['active', 'pending', 'overdue', 'in progress'];

        if ($user_id) {

            $not_low = Ticket::where('client_id', $user_id)->whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::where('client_id', $user_id)->whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();

        } else {

            $not_low = Ticket::whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
        }

        $data = $not_low->merge($low);

        return $data;

    }

    public static function closed($user_id)
    {

        //	self::checkOverDue();

        $status = ['closed', 'pending/closed', 'overdue/closed'];

        if ($user_id) {

            $not_low = Ticket::where('client_id', $user_id)->whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::where('client_id', $user_id)->whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();

        } else {

            $not_low = Ticket::whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
        }

        $data = $not_low->merge($low);

        return $data;

    }

    public static function technical_assigment_active($user_id)
    {

        self::checkOverDue();

        $status = ['active', 'pending', 'overdue', 'in progress', 'overdue/in progress', 'pending/in progress'];
        // $user_id = [$user_id];

        // $data = Ticket::whereRaw('FIND_IN_SET('.$user_id.',technical_support_id)')->whereIn('status',$status)->get();

        if ($user_id) {

            $not_low = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))->whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))->whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();

        } else {

            $not_low = Ticket::whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
        }

        $data = $not_low->merge($low);

        return $data;

        // $data = DB::select("SELECT * FROM tickets WHERE FIND_IN_SET (".$user_id.",technical_support_id) > 0 ");

        // return $data;

    }

    public static function technical_assigment_closed($user_id)
    {

        $status = ['closed', 'pending/closed', 'overdue/closed'];

        if ($user_id) {

            $not_low = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))->whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))->whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();

        } else {

            $not_low = Ticket::whereIn('status', $status)->where('priority', '!=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
            $low = Ticket::whereIn('status', $status)->where('priority', '=', 'low')->orderBy('priority', 'ASC')->orderBy('id', 'DESC')->get();
        }

        $data = $not_low->merge($low);

        return $data;

    }

    public static function count_active($user_id)
    {

        $status = ['active', 'pending', 'overdue', 'in progress', 'overdue/in progress', 'pending/in progress'];

        if ($user_id) {
            $data = Ticket::where('client_id', $user_id)
                ->whereIn('status', $status)->count(); // ->whereRaw('Date(created_at) = CURDATE()')->whereRaw('Date(created_at) = CURDATE()')

        } else {
            $data = Ticket::whereIn('status', $status)->whereRaw('Date(created_at) = CURDATE()')->count();
        }

        return $data;

    }

    public static function count_closed($user_id)
    {

        $status = ['closed', 'pending', 'overdue/closed', 'overdue/in progress', 'pending/in progress'];

        if ($user_id) {
            $data = Ticket::where('client_id', $user_id)
                ->whereIn('status', $status)->count(); // ->whereRaw('Date(created_at) = CURDATE()')->whereRaw('Date(created_at) = CURDATE()')

        } else {
            $data = Ticket::whereIn('status', $status)->whereRaw('Date(created_at) = CURDATE()')->count();
        }

        return 0;

        return $data;

    }

    public static function technical_assigment_count_active($user_id)
    {

        $status = ['active', 'pending', 'overdue', 'in progress', 'overdue/in progress', 'pending/in progress'];
        // $user_id = [$user_id];

        $data = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))
            ->whereIn('status', $status)->count(); // ->whereRaw('Date(created_at) = CURDATE()')->whereRaw('Date(created_at) = CURDATE()')

        return $data;

    }

    public static function technical_assigment_count_closed($user_id)
    {

        $status = ['closed', 'pending', 'overdue/closed'];
        // $user_id = [$user_id];

        $data = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))
            ->whereIn('status', $status)->count(); // ->whereRaw('Date(created_at) = CURDATE()')->whereRaw('Date(created_at) = CURDATE()')

        return 0;

        return $data;

    }

    public static function checkOverDue()
    {
        // Global update status to overdue
        $status = ['active', 'in progress'];

        Ticket::where('created_at', '<', Carbon::parse('-24 hours'))
            ->whereIn('status', $status)
            ->update(['status' => 'overdue']);

        // End Global

    }

    public static function department_graph($department)
    {

        if (! $department) {
            $department = '';
        } else {
            $department = " AND department.name = '".$department."'";
        }

        $where = 'WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())';
        $record = DB::select('SELECT department.name as department, COUNT(*) as count FROM tickets LEFT JOIN department ON department.id = tickets.department_id '.$where.' '.$department.' GROUP BY department.name ORDER BY department.name');

        return $record;
    }

    public static function priority_graph()
    {

        $query = "SELECT * ,
              (low/all_low * 100) as low_percentage,
              (normal/all_normal * 100) as normal_percentage,
              (critical/all_critical * 100) as critical_percentage
              FROM (SELECT 
              (SELECT COUNT(*) FROM tickets WHERE priority = 'low' AND status IN ('closed','pending/closed','overdue/closed')   AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as low,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'low'  AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as all_low,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'normal' AND status IN ('closed','pending/closed','overdue/closed')   AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as normal,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'normal'  AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ) as all_normal,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'critical' AND status IN ('closed','pending/closed','overdue/closed')   AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ) as critical,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'critical'  AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ) as all_critical) as priority_level
              ";

        $record = DB::select($query);

        return $record;

    }

    public static function per_monthly_priority_graph()
    {

        $query = "SELECT * ,
              (low/all_low * 100) as low_percentage,
              (normal/all_normal * 100) as normal_percentage,
              (critical/all_critical * 100) as critical_percentage
              FROM (SELECT 
              (SELECT COUNT(*) FROM tickets WHERE priority = 'low' AND status IN ('closed','pending/closed','overdue/closed')  AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as low,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'low' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as all_low,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'normal' AND status IN ('closed','pending/closed','overdue/closed')  AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as normal,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'normal' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ) as all_normal,

              (SELECT COUNT(*) FROM tickets WHERE priority = 'critical' AND status IN ('closed','pending/closed','overdue/closed')  AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ) as critical,
              
              (SELECT COUNT(*) FROM tickets WHERE priority = 'critical' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ) as all_critical) as priority_level
              ";

        $record = DB::select($query);

        return $record;

    }

    public static function my_tickets_in_month($user_id)
    {

        $where = 'WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())';

        if (! $user_id) {
            $user_id = '';
        } else {
            $user_id = ' AND client_id ='.$user_id.'';
        }

        $record = DB::select('SELECT * FROM tickets '.$where.' '.$user_id.' ORDER BY id DESC');

        return $record;

    }

    public static function my_technical_tickets_in_month($user_id)
    {

        $status = ['closed', 'pending/closed', 'overdue/closed'];

        $data = Ticket::whereHas('technicalSupportUsers', fn ($query) => $query->whereKey($user_id))->whereIn('status', $status)->whereMonth('created_at', '=', date('m'))->whereYear('created_at', '=', date('Y'))->orderBy('id', 'DESC')->get();

        return $data;

    }

    public static function monthly_top_ticket_solver()
    {

        $record = DB::select("SELECT users.name, COUNT(ticket_technical_support.ticket_id) as count FROM users LEFT JOIN ticket_technical_support ON ticket_technical_support.user_id = users.id LEFT JOIN tickets ON tickets.id = ticket_technical_support.ticket_id AND tickets.status IN ('closed','pending/closed','overdue/closed' ,'overdue/in progress' ,'pending/in progress') AND MONTH(tickets.created_at) = MONTH(CURRENT_DATE()) AND YEAR(tickets.created_at) = YEAR(CURRENT_DATE()) WHERE users.status = 1 AND users.is_deleted = 0 GROUP BY users.id, users.name ORDER BY count DESC");

        return $record;

    }

    public static function issue_category()
    {

        $record = DB::select("SELECT IFNULL(issue_category.name, 'Anonymous') as category, COUNT(*) as count FROM tickets LEFT JOIN issue_list ON issue_list.id = tickets.issue_id LEFT JOIN issue_category ON issue_category.id = issue_list.issue_category_id WHERE MONTH(tickets.created_at) = MONTH(CURRENT_DATE()) AND YEAR(tickets.created_at) = YEAR(CURRENT_DATE()) GROUP BY issue_category.name ORDER BY issue_category.name");

        $data = [];
        foreach ($record as $key => $value) {

            $obj = new \stdClass;
            $obj->color = self::generate_colors($key);
            $obj->category = $value->category;
            $obj->count = $value->count;

            $data[] = $obj;

        }

        return $data;
    }

    public static function category_pie_graph()
    {

        $record = DB::select("SELECT IFNULL(issue_category.name, 'Anonymous') as category, COUNT(*) as count FROM tickets LEFT JOIN issue_list ON issue_list.id = tickets.issue_id LEFT JOIN issue_category ON issue_category.id = issue_list.issue_category_id WHERE MONTH(tickets.created_at) = MONTH(CURRENT_DATE()) AND YEAR(tickets.created_at) = YEAR(CURRENT_DATE()) GROUP BY issue_category.name ORDER BY issue_category.name");

        $data = [];
        foreach ($record as $key => $value) {

            $obj = new \stdClass;
            $obj->value = $value->count;
            $obj->color = self::generate_colors_hex($key);
            $obj->highlight = self::generate_colors_hex($key);
            $obj->label = $value->category;

            $data[] = $obj;

        }

        return $data;
    }

    public static function generate_colors($number)
    {
        switch ($number) {
            case '0':
                return 'red';
                break;
            case '1':
                return 'yellow';
                break;
            case '2':
                return 'aqua';
                break;
            case '3':
                return 'blue';
                break;
            case '4':
                return 'teal';
                break;

            case '5':
                return 'light-blue';
                break;

            case '5':
                return 'green';
                break;

            case '6':
                return 'gray';
                break;

            case '7':
                return 'navy';
                break;

            case '8':
                return 'olive';
                break;

            case '9':
                return 'lime';
                break;

            case '10':
                return 'orange';
                break;

            case '11':
                return 'fuchsia';
                break;
            case '12':
                return 'purple';
                break;
            case '13':
                return 'maroon';
                break;
            case '14':
                return 'black';
                break;
            default:
                return 'white';
                // code...
                break;
        }
    }

    public static function generate_colors_hex($number)
    {
        switch ($number) {
            case '0':
                return '#FF0000';
                break;
            case '1':
                return '#FFFF00';
                break;
            case '2':
                return '#00FFFF';
                break;
            case '3':
                return '#0000FF';
                break;
            case '4':
                return '#008080';
                break;

            case '5':
                return '#ADD8E6';
                break;

            case '5':
                return '#008000';
                break;

            case '6':
                return '#808080';
                break;

            case '7':
                return '#000080';
                break;

            case '8':
                return '#BAB86C';
                break;

            case '9':
                return '#00FF00';
                break;

            case '10':
                return '#FFA500';
                break;

            case '11':
                return '#FF77FF';
                break;
            case '12':
                return '#800080';
                break;
            case '13':
                return '#800000';
                break;
            case '14':
                return '#000000';
                break;
            default:
                return '#FFFFFF';
                // code...
                break;
        }
    }

    public static function overview_summary()
    {

        self::checkOverDue();

        $overdue = Ticket::where('status', 'overdue')->whereMonth('created_at', '=', date('m'))->whereYear('created_at', '=', date('Y'))->count();
        $active = Ticket::where('status', 'active')->whereMonth('created_at', '=', date('m'))->whereYear('created_at', '=', date('Y'))->count();
        $in_progress = Ticket::where('status', 'in progress')->whereMonth('created_at', '=', date('m'))->whereYear('created_at', '=', date('Y'))->count();
        $pending = Ticket::where('status', 'pending')->whereMonth('created_at', '=', date('m'))->whereYear('created_at', '=', date('Y'))->count();

        $closed_status = ['closed', 'pending/closed', 'overdue/closed'];
        $closed = Ticket::whereIn('status', $closed_status)->whereMonth('created_at', '=', date('m'))->whereYear('created_at', '=', date('Y'))->count();

        $obj = new \stdClass;
        $obj->overdue = $overdue;
        $obj->active = $active;
        $obj->in_progress = $in_progress;
        $obj->closed = $closed;
        $obj->pending = $pending;

        return $obj;
    }
}
