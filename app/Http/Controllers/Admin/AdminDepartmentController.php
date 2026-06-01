<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\Department;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use Config;
use Illuminate\Http\Request;
use Validator;

class AdminDepartmentController extends Controller
{
    public function index()
    {
        Config::set('adminlte.plugins.datatables', true);
        $data = Department::where('is_deleted', 0)->get();
        SystemLogs::saveLogs('visited department list management!');
        Menu::menu_controller();

        return view('admin.management.department.index', compact('data'));
    }

    public function create()
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        SystemLogs::saveLogs('visited create department form!');
        Menu::menu_controller();

        return view('admin.management.department.create');
    }

    public function store(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:191|unique:department',
        ]);

        if ($validation->passes()) {

            $data = new Department;
            $data->name = $request->name;

            if ($data->save()) {
                SystemLogs::saveLogs('successfully created '.$request->name.' as new department!');
                $msg = $request->name.' department has been successfully added!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/department')]);
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
        $department = Department::find(decrypt($id));
        SystemLogs::saveLogs('visited '.$department->name.' department for updating!');
        Menu::menu_controller();

        return view('admin.management.department.edit', compact('department'));
    }

    public function update(Request $request)
    {

        $id = decrypt($request->id);

        $validation = Validator::make($request->all(), [

            'name' => 'required|string|max:191|unique:department,id,'.$id,

        ]);

        if ($validation->passes()) {

            $data = Department::find($id);

            SystemLogs::saveLogs('successfully updated '.$data->name.' to '.$request->name.'!');
            $msg = $data->name.' department has been successfully updated to '.$request->name.'!';

            $data->name = $request->name;

            if ($data->save()) {
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/department')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function destroy(Request $request)
    {

        $id = decrypt($request->id);
        $update = Department::find($id);
        $update->is_deleted = 1;

        if ($update->save()) {

            SystemLogs::saveLogs('successfully deleted '.$update->name.' department!');
            $msg = "<strong><font size='3' color='green'>'.$update->name.' department has been successfully deleted! </font></strong>";
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => $msg, 'page' => url('department/manage/table')]);
        }

        return response()->json(['success' => false, 'message' => "<strong><font size='3' color='red'>An error has occurred while  updating record! </font></strong>", 'page' => url('departments/manage/table')]);
    }
}
