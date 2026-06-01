<?php

namespace App\Http\Models\Admin;

use DB;
use Illuminate\Database\Eloquent\Model;

class IssueList extends Model
{
    protected $table = 'issue_list';

    public static function category()
    {
        $query = DB::select('SELECT id as category_id, name as category FROM issue_category WHERE is_deleted = 0');

        return $query;
    }

    public static function data_list()
    {
        $query = DB::select('SELECT id,issue,(SELECT name as category FROM issue_category as ic WHERE ic.id = il.issue_category_id) as category FROM issue_list as il WHERE is_deleted = 0');

        return $query;
    }

    public static function optgroup()
    {
        $category = DB::select('SELECT issue_category_id,(SELECT name FROM issue_category as ic WHERE ic.is_deleted = 0 AND ic.id = il.issue_category_id) as category FROM issue_list as il WHERE is_deleted = 0 GROUP BY issue_category_id');

        $data = [];
        // $data = new \stdClass();

        foreach ($category as $key => $value) {
            $data[$value->category] = self::getissues($value->issue_category_id);
        }

        return $data;
    }

    public static function getissues($id)
    {
        $issue = DB::select('SELECT id,issue FROM issue_list WHERE issue_category_id = '.$id);

        return $issue;
    }
}
