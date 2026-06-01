<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\Position;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use Config;
use Illuminate\Http\Request;
use Validator;

class AdminPositionController extends Controller
{
    public function index()
    {
        Config::set('adminlte.plugins.datatables', true);
        $data = Position::where('is_deleted', 0)->get();
        SystemLogs::saveLogs('visited position list management!');
        Menu::menu_controller();

        return view('admin.management.position.index', compact('data'));
    }

    public function create()
    {
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        SystemLogs::saveLogs('visited create position form!');
        Menu::menu_controller();

        return view('admin.management.position.create');
    }

    public function store(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:191|unique:position',
        ]);

        if ($validation->passes()) {

            $data = new Position;
            $data->name = $request->name;

            if ($data->save()) {
                SystemLogs::saveLogs('successfully created '.$request->name.' as new position!');
                $msg = $request->name.' position has been successfully added!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/position')]);
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
        $position = Position::find(decrypt($id));
        SystemLogs::saveLogs('visited '.$position->name.' position for updating!');
        Menu::menu_controller();

        return view('admin.management.position.edit', compact('position'));
    }

    public function update(Request $request)
    {

        $id = decrypt($request->id);

        $validation = Validator::make($request->all(), [

            'name' => 'required|string|max:191|unique:position,id,'.$id,

        ]);

        if ($validation->passes()) {

            $data = Position::find($id);

            SystemLogs::saveLogs('successfully updated '.$data->name.' to '.$request->name.'!');
            $msg = $data->name.' position has been successfully updated to '.$request->name.'!';

            $data->name = $request->name;

            if ($data->save()) {
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/position')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function destroy(Request $request)
    {

        $id = decrypt($request->id);
        $update = Position::find($id);
        $update->is_deleted = 1;

        if ($update->save()) {

            SystemLogs::saveLogs('successfully deleted '.$update->name.' position!');
            $msg = "<strong><font size='3' color='green'>".$update->name.' position has been successfully deleted! </font></strong>';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => $msg, 'page' => url('position/manage/table')]);
        }

        return response()->json(['success' => false, 'message' => "<strong><font size='3' color='red'>An error has occurred while  updating record! </font></strong>", 'page' => url('position/manage/table')]);
    }
}
