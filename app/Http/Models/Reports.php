<?php

namespace App\Http\Models;

use App\Http\Models\Admin\SystemLogs;
use Auth;
use DateInterval;
use DatePeriod;
use DateTime;
use DB;
use Illuminate\Database\Eloquent\Model;
use Session;

class Reports extends Model
{
    public static function generate($data)
    {

        switch ($data->selected) {

            case 'overall':
                $record = self::overall_data($data);
                break;

            case 'department':
                $record = self::department($data);
                break;

            case 'status':
                $record = self::status($data);
                break;

            case 'category':
                $record = self::category($data);
                break;

            case 'issue':
                $record = self::issue($data);
                break;

            case 'technical_support':
                $record = self::technical_support($data);
                break;

            case 'ratings':
                $record = self::ratings($data);
                break;

            case 'client':
                $record = self::client($data);
                break;

            default:
                // code...
                break;
        }

        return $record;
    }

    public static function count_generate($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $technical_support = $data->technical_support;
        $department = $data->department;
        $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        if (strtolower($technical_support) == 'all' || strtolower($technical_support) == '') {
            $technical_support = '';
            $technical_support_id = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            $technical_support_id = ' AND id = '.$data->technical_support.'';
            // $issues = " AND issue_id = '".$issues."'";
        }

        switch ($data->selected) {

            case 'overall':

                $record = DB::select("SELECT department, COUNT(*) as count FROM tickets  WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' GROUP BY department ORDER BY department');

                break;

            case 'department':

                $record = DB::select("SELECT department, COUNT(*) as count FROM tickets  WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' GROUP BY department ORDER BY department');

                break;

            case 'status':

                $record = DB::select("SELECT status, COUNT(*) as count FROM tickets  WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' GROUP BY status ORDER BY status');
                break;

            case 'category':

                $record = DB::select("SELECT category, COUNT(*) as count FROM tickets  WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.'  GROUP BY category ORDER BY category');
                break;

            case 'issue':

                $record = DB::select("SELECT issue, COUNT(*) as count FROM tickets  WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' GROUP BY issue ORDER BY issue');

                break;

            case 'technical_support':

                $record = DB::select("SELECT *, ( SELECT COUNT(*) as count FROM tickets as t WHERE FIND_IN_SET(ts.technical_support_id,t.technical_support_id) AND  created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' ORDER BY technical_support ) as count FROM (SELECT id as technical_support_id, name as technical_support FROM users as u WHERE status = 1 AND is_deleted = 0 '.$technical_support_id." AND role = 'admin' OR role = 'technical' ORDER BY name) as ts");

                break;

            case 'ratings':

                $record = DB::select("SELECT *, ( SELECT ROUND(((SUM(rate) / ( COUNT(*) * 5 )) * 100 )) as count FROM tickets as t WHERE FIND_IN_SET(ts.technical_support_id,t.technical_support_id) AND  created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' ORDER BY technical_support ) as count FROM (SELECT id as technical_support_id, name as technical_support FROM users as u WHERE   status = 1 AND is_deleted = 0 '.$technical_support_id."  AND role = 'admin' OR role = 'technical' ORDER BY name) as ts");

                break;

            case 'client':

                $record = DB::select('SELECT status, COUNT(*) as count FROM tickets  WHERE  client_id = '.Auth::id()." AND created_at BETWEEN '".$start."' AND '".$end."'".$category.''.$status.''.$issues.''.$technical_support.'  GROUP BY status ORDER BY status');
                break;

            default:
                // code...
                break;
        }

        return $record;
    }

    public static function overall_data($data)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];

        $department = $data->department;
        $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;
        $technical_support = $data->technical_support;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        if (strtolower($technical_support) == 'all' || strtolower($issues) == '') {
            $technical_support = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            // $issues = " AND issue_id = '".$issues."'";
        }

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support.' ORDER BY ID DESC');

        if (! $find) {

            SystemLogs::saveLogs('No overall report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No overall report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $find;

    }

    // ////////////////////////////////////////////
    public static function department($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $department = $data->department;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        $department = DB::select("SELECT department FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."'".$department.'  GROUP BY department ORDER BY department');

        if ($department) {
            foreach ($department as $key => $value) {

                $record[$value->department] = self::department_data($data, $value->department);
            }
        } else {

            SystemLogs::saveLogs('No department report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No department report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function department_data($data, $department)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];

        // $department = $data->department;
        $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;
        $technical_support = $data->technical_support;

        // if(strtolower($department) == "all" || strtolower($department) == ''){
        // 	$department = "";
        // }else{
        $department = " AND department = '".$department."'";
        // }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        if (strtolower($technical_support) == 'all' || strtolower($issues) == '') {
            $technical_support = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            // $issues = " AND issue_id = '".$issues."'";
        }

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support);

        return $find;

    }

    // //////////////////////////////////////////////////

    public static function status($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $status = $data->status;

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';
        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        $status = DB::select("SELECT status FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."'".$status.'  GROUP BY status ORDER BY status');

        if ($status) {
            foreach ($status as $key => $value) {

                $record[$value->status] = self::status_data($data, $value->status);
            }
        } else {

            SystemLogs::saveLogs('No status report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No status report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function status_data($data, $status)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];

        $department = $data->department;
        // $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;
        $technical_support = $data->technical_support;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        // if(strtolower($status) == "all" || strtolower($status) == ''){
        // 	$status = "";
        // }else{
        // $status = " AND status = '".$status."'";
        // }

        if (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        if (strtolower($technical_support) == 'all' || strtolower($issues) == '') {
            $technical_support = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            // $issues = " AND issue_id = '".$issues."'";
        }

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support);

        return $find;

    }

    // //////////////////////////////////////////////////

    public static function category($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $category = $data->category;

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        $category = DB::select("SELECT category FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."'".$category.'  GROUP BY category  ORDER BY category');

        if ($category) {
            foreach ($category as $key => $value) {

                $record[$value->category] = self::category_data($data, $value->category);
            }
        } else {

            SystemLogs::saveLogs('No category report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No category report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function category_data($data, $category)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];

        $department = $data->department;
        $status = $data->status;
        // $category = $data->category;
        $issues = $data->issues;
        $technical_support = $data->technical_support;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        // if(strtolower($category) == "all" || strtolower($category) == ''){
        // 	$category = "";
        // }else{
        $category = " AND category = '".$category."'";
        // }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        if (strtolower($technical_support) == 'all' || strtolower($issues) == '') {
            $technical_support = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            // $issues = " AND issue_id = '".$issues."'";
        }

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support);

        return $find;

    }

    // //////////////////////////////////////////////////

    public static function issue($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $issue = $data->issues;

        if (strtolower($issue) == 'all' || strtolower($issue) == '') {
            $issue = '';
        } else {
            $issue = " AND issue = '".$issue."'";
        }

        $issue = DB::select("SELECT issue FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."'".$issue.' GROUP BY issue ORDER BY issue');

        if ($issue) {
            foreach ($issue as $key => $value) {

                $record[$value->issue] = self::issue_data($data, $value->issue);
            }
        } else {

            SystemLogs::saveLogs('No issue report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No issue report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function issue_data($data, $issue)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];

        $department = $data->department;
        $status = $data->status;
        $category = $data->category;
        // $issues = $data->issues;
        $technical_support = $data->technical_support;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        $issue = " AND issue = '".$issue."'";

        if (strtolower($technical_support) == 'all' || strtolower($issue) == '') {
            $technical_support = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            // $issues = " AND issue_id = '".$issues."'";
        }

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issue.''.$technical_support);

        return $find;

    }

    // /////////////////////////////////////////////////////

    public static function technical_support($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $technical_support = $data->technical_support;

        if (strtolower($technical_support) == 'all' || strtolower($technical_support) == '') {
            $technical_support_id = '';
        } else {
            $technical_support_id = ' AND id = '.$technical_support.'';
        }

        $technical_support = DB::select('SELECT  id as technical_support_id, name as technical_support FROM users WHERE  status = 1 AND is_deleted = 0  '.$technical_support_id." AND role = 'admin' OR role = 'technical'  ORDER BY name");

        if ($technical_support) {
            foreach ($technical_support as $key => $value) {

                $record[$value->technical_support] = self::technical_support_data($data, $value->technical_support_id);
            }
        } else {

            SystemLogs::saveLogs('No technical support report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No technical support report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function technical_support_data($data, $technical_support)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];
        $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;
        $department = $data->department;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";

        // echo "SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department."".$category."".$status."".$issues."".$technical_support;exit;

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support);

        return $find;

    }

    public static function ratings($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $technical_support = $data->technical_support;

        if (strtolower($technical_support) == 'all' || strtolower($technical_support) == '') {
            $technical_support_id = '';
        } else {
            $technical_support_id = ' AND id = '.$technical_support.'';
        }

        $technical_support = DB::select('SELECT  id as technical_support_id, name as technical_support FROM users WHERE  status = 1 AND is_deleted = 0  '.$technical_support_id." AND role = 'admin' OR role = 'technical'  ORDER BY name");

        if ($technical_support) {
            foreach ($technical_support as $key => $value) {

                $record[$value->technical_support] = self::technical_support_data($data, $value->technical_support_id);
            }
        } else {

            SystemLogs::saveLogs('No technical support report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No technical support report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function ratings_data($data, $technical_support)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];
        $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;
        $department = $data->department;

        if (strtolower($department) == 'all' || strtolower($department) == '') {
            $department = '';
        } else {
            $department = " AND department = '".$department."'";
        }

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';

        } elseif (strtolower($status) == 'all/closed') {
            $array = "closed','pending/closed','overdue/closed";
            $status = " AND status IN ('".$array."')";

        } elseif (strtolower($status) == 'all/active') {
            $array = "active','pending','overdue','in progress','overdue/in progress','pending/in progress";
            $status = " AND status IN ('".$array."')";

        } else {
            $status = " AND status = '".$status."'";
        }

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";

        $find = DB::select("SELECT * FROM tickets WHERE created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support);

        return $find;

    }

    public static function client($data)
    {

        $date = explode(' - ', $data->range);
        $start = $date[0];
        $end = $date[1];

        $status = $data->status;

        if (strtolower($status) == 'all' || strtolower($status) == '') {
            $status = '';
        } else {
            $status = " AND status = '".$status."'";
        }

        $status = DB::select('SELECT status FROM tickets WHERE client_id = '.Auth::id()." AND created_at BETWEEN '".$start."' AND '".$end."'".$status.'  GROUP BY status ORDER BY status');

        if ($status) {
            foreach ($status as $key => $value) {

                $record[$value->status] = self::status_data($data, $value->status);
            }
        } else {

            SystemLogs::saveLogs('No status report record found between '.$start.' to '.$end.'!');
            $msg = "<strong><font size='3' color='red'> No status report record found between ".$start.' to '.$end.'! </font></strong>';
            Session::flash('msg', $msg);

            return null;
        }

        return $record;

    }

    public static function client_data($data, $status)
    {

        $date = explode(' - ', $data->range);

        $selected = $data->selected;
        $start = $date[0];
        $end = $date[1];

        $department = $data->department;
        // $status = $data->status;
        $category = $data->category;
        $issues = $data->issues;
        $technical_support = $data->technical_support;

        $status = " AND status = '".$status."'";

        if (strtolower($category) == 'all' || strtolower($category) == '') {
            $category = '';
        } else {
            $category = " AND category = '".$category."'";
        }

        if (strtolower($issues) == 'all' || strtolower($issues) == '') {
            $issues = '';
        } else {
            $issues = " AND issue_id = '".$issues."'";
        }

        if (strtolower($technical_support) == 'all' || strtolower($issues) == '') {
            $technical_support = '';
        } else {
            $technical_support = " AND FIND_IN_SET('".$technical_support."',technical_support_id)";
            // $issues = " AND issue_id = '".$issues."'";
        }

        $find = DB::select('SELECT * FROM tickets WHERE  client_id = '.Auth::id()." AND created_at BETWEEN '".$start."' AND '".$end."' ".$department.''.$category.''.$status.''.$issues.''.$technical_support);

        return $find;

    }

    public static function biss_hours($start, $end)
    {

        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        $periodInterval = new DateInterval('PT1H');

        $period = new DatePeriod($startDate, $periodInterval, $endDate);
        $count = 0;

        foreach ($period as $date) {

            $startofday = clone $date;
            $startofday->setTime(8, 30);

            $endofday = clone $date;
            $endofday->setTime(17, 30);

            if ($date > $startofday && $date <= $endofday && ! in_array($date->format('l'), ['Sunday', 'Saturday'])) {

                $count++;
            }

        }

        // Get seconds of Start time
        $start_d = date('Y-m-d H:00:00', strtotime($start));
        $start_d_seconds = strtotime($start_d);
        $start_t_seconds = strtotime($start);
        $start_seconds = $start_t_seconds - $start_d_seconds;

        // Get seconds of End time
        $end_d = date('Y-m-d H:00:00', strtotime($end));
        $end_d_seconds = strtotime($end_d);
        $end_t_seconds = strtotime($end);
        $end_seconds = $end_t_seconds - $end_d_seconds;

        $diff = $end_seconds - $start_seconds;

        if ($diff != 0) {
            $count--;
        }

        $total_min_sec = date('i:s', $diff);

        return date('h:iA', strtotime($start)).'-'.date('h:iA', strtotime($end)).' ('.$count.':'.$total_min_sec.')';
    }

    public static function duration($start, $end)
    {

        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        $periodInterval = new DateInterval('PT1H');

        $period = new DatePeriod($startDate, $periodInterval, $endDate);
        $count = 0;

        foreach ($period as $date) {

            $startofday = clone $date;
            $startofday->setTime(8, 30);

            $endofday = clone $date;
            $endofday->setTime(17, 30);

            if ($date > $startofday && $date <= $endofday && ! in_array($date->format('l'), ['Sunday', 'Saturday'])) {

                $count++;
            }

        }

        // Get seconds of Start time
        $start_d = date('Y-m-d H:00:00', strtotime($start));
        $start_d_seconds = strtotime($start_d);
        $start_t_seconds = strtotime($start);
        $start_seconds = $start_t_seconds - $start_d_seconds;

        // Get seconds of End time
        $end_d = date('Y-m-d H:00:00', strtotime($end));
        $end_d_seconds = strtotime($end_d);
        $end_t_seconds = strtotime($end);
        $end_seconds = $end_t_seconds - $end_d_seconds;

        $diff = $end_seconds - $start_seconds;

        if ($diff != 0) {
            $count--;
        }

        $total_min_sec = date('i:s',$diff);

        return $count.':'.$total_min_sec;
    }
}
