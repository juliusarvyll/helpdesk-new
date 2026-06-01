<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\IssueCategory;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use Config;
use Illuminate\Http\Request;
use Validator;

class AdminIssueCategoryController extends Controller
{
    public function index()
    {
        Config::set('adminlte.plugins.datatables', true);
        $data = IssueCategory::where('is_deleted', 0)->get();
        SystemLogs::saveLogs('visited issue category list management!');
        Menu::menu_controller();

        return view('admin.management.issue.category.index', compact('data'));
    }

    public function create()
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        SystemLogs::saveLogs('visited create issue category form!');
        Menu::menu_controller();

        return view('admin.management.issue.category.create');
    }

    public function store(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:191|unique:issue_category',
        ]);

        if ($validation->passes()) {

            $data = new IssueCategory;
            $data->name = strtoupper($request->name);

            if ($data->save()) {
                SystemLogs::saveLogs('successfully created '.strtoupper($request->name).' as new issue category!');
                $msg = strtoupper($request->name).' issue category has been successfully added!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/issue/category')]);
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
        $category = IssueCategory::find(decrypt($id));
        SystemLogs::saveLogs('visited '.$category->name.' issue category for updating!');
        Menu::menu_controller();

        return view('admin.management.issue.category.edit', compact('category'));
    }

    public function update(Request $request)
    {

        $id = decrypt($request->id);

        $validation = Validator::make($request->all(), [

            'name' => 'required|string|max:191|unique:issue_category,id,'.$id,

        ]);

        if ($validation->passes()) {

            $data = IssueCategory::find($id);

            SystemLogs::saveLogs('successfully updated '.$data->name.' to '.$request->name.'!');
            $msg = $data->name.' issue category has been successfully updated to '.$request->name.'!';

            $data->name = $request->name;

            if ($data->save()) {
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/issue/category')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function destroy(Request $request)
    {

        $id = decrypt($request->id);
        $update = IssueCategory::find($id);
        $update->is_deleted = 1;

        if ($update->save()) {

            SystemLogs::saveLogs('successfully deleted '.$update->name.' issue category!');
            $msg = "<strong><font size='3' color='green'>'.$update->name.' issue category has been successfully deleted! </font></strong>";
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => $msg, 'page' => url('issue/category/manage/table')]);
        }

        return response()->json(['success' => false, 'message' => "<strong><font size='3' color='red'>An error has occurred while  updating record! </font></strong>", 'page' => url('issue/category/manage/table')]);
    }
}
