<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\IssueList;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use Config;
use Illuminate\Http\Request;
use Validator;

class AdminIssueListController extends Controller
{
    public function index()
    {
        Config::set('adminlte.plugins.datatables', true);
        $data = IssueList::data_list();
        SystemLogs::saveLogs('visited issue list management!');
        Menu::menu_controller();

        return view('admin.management.issue.list.index', compact('data'));
    }

    public function create()
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        $category = IssueList::category();
        SystemLogs::saveLogs('visited create issue form!');

        Menu::menu_controller();

        return view('admin.management.issue.list.create', compact('category'));
    }

    public function store(Request $request)
    {

        $customize = [
            'category_id.required' => 'The category field is required',
            'issue.required' => 'The issue/description field is required',

        ];
        $validation = Validator::make($request->all(), [
            'issue' => 'required|string|max:191|unique:issue_list',
            'category_id' => 'required',
        ], $customize);

        if ($validation->passes()) {

            $data = new IssueList;
            $data->issue = $request->issue;
            $data->issue_category_id = $request->category_id;

            if ($data->save()) {
                SystemLogs::saveLogs('successfully created '.strtoupper($request->issue).' as new issue!');
                $msg = strtoupper($request->issue).' issue has been successfully added!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/list/issue')]);
            }

        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function edit($id)
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        $category = IssueList::category();
        $issue = IssueList::find(decrypt($id));
        SystemLogs::saveLogs('visited '.$issue->issue.' issue issue for updating!');
        Menu::menu_controller();

        return view('admin.management.issue.list.edit', compact('issue', 'category'));
    }

    public function update(Request $request)
    {

        $id = decrypt($request->id);

        $customize = [
            'category_id.required' => 'The category field is required',
            'issue.required' => 'The issue/description field is required',

        ];

        $validation = Validator::make($request->all(), [

            'issue' => 'required|string|max:191|unique:issue_list,id,'.$id,
            'category_id' => 'required',

        ], $customize);

        if ($validation->passes()) {

            $data = IssueList::find($id);

            SystemLogs::saveLogs('successfully updated '.$data->issue.' to '.$request->issue.'!');
            $msg = $data->issue.' issue has been successfully updated to '.$request->issue.'!';
            $data->issue_category_id = $request->category_id;
            $data->issue = $request->issue;

            if ($data->save()) {
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/list/issue')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function destroy(Request $request)
    {

        $id = decrypt($request->id);
        $update = IssueList::find($id);
        $update->is_deleted = 1;

        if ($update->save()) {

            SystemLogs::saveLogs('successfully deleted '.$update->issue.' issue!');
            $msg = "<strong><font size='3' color='green'>".$update->issue.' issue has been successfully deleted! </font></strong>';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => $msg, 'page' => url('list/issue/manage/table')]);
        }

        return response()->json(['success' => false, 'message' => "<strong><font size='3' color='red'>An error has occurred while  updating record! </font></strong>", 'page' => url('list/issue/manage/table')]);
    }
}
