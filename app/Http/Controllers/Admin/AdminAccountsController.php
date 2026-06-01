<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\Accounts;
use App\Http\Models\Admin\Department;
use App\Http\Models\Admin\Position;
use App\Http\Models\Admin\Role;
use App\Http\Models\Admin\SystemLogs;
use App\Http\Models\MenuController as Menu;
use App\User;
use Config;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Validator;

class AdminAccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        Menu::menu_controller();
        Config::set('adminlte.plugins.datatables', true);
        $data = Accounts::where('is_deleted', 0)->get();
        SystemLogs::saveLogs('visited user account list management!');

        return view('admin.accounts.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        Menu::menu_controller();
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        $role = Role::all();
        $department = Department::all();
        $position = Position::all();
        SystemLogs::saveLogs('visited create account form!');

        return view('admin.accounts.create', compact('role', 'department', 'position'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'username' => 'required|string|max:191|unique:users',
            'email' => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:6',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'role' => 'required',
            'department' => 'required',
            'position' => 'required',
        ]);

        if ($validation->passes()) {

            $data = new User;
            $data->name = $request->name;
            $data->department = $request->department;
            $data->position = $request->position;
            $data->username = $request->username;
            $data->email = $request->email;
            $data->contact = $request->contact;
            $data->password = Hash::make($request->password);
            $data->role = $request->role;

            if ($request->file('photo')) {

                $file = $request->file('photo');
                $ext = strtolower($file->getClientOriginalExtension());
                Storage::disk('uploads')->delete('avatars/'.$request->username);
                $avatarName = $request->username.'.'.$ext;
                $request->file('photo')->storeAs('avatars', $avatarName, 'uploads');
                $data->photo = $avatarName;

            }

            if ($data->save()) {
                SystemLogs::saveLogs('successfully created '.$request->name.' new user account!');
                $msg = $request->name.' account has been successfully added!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/accounts')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function view($id)
    {

        Menu::menu_controller();
        Config::set('adminlte.plugins.datatables', true);
        $user_id = decrypt($id);
        $activity = SystemLogs::find_activity_trail($user_id);
        $data = User::find($user_id);
        SystemLogs::saveLogs('visited '.$data->name.' profile overview!');

        return view('admin.accounts.view', compact('data', 'activity'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        Menu::menu_controller();
        Config::set('adminlte.plugins.customize.js', true);
        Config::set('adminlte.plugins.form', true);
        $user = User::find(decrypt($id));
        $role = Role::all();
        $department = Department::all();
        $position = Position::all();
        SystemLogs::saveLogs('visited '.$user->name.' account for updating!');

        return view('admin.accounts.edit', compact('role', 'department', 'position', 'user'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {

        $user_id = decrypt($request->user_id);

        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'username' => 'required|string|max:191|unique:users,id,'.$user_id,
            'email' => 'required|string|email|max:191|unique:users,id,'.$user_id,
            'password' => 'nullable|string|min:6',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'role' => 'required',
            'department' => 'required',
            'position' => 'required',
        ]);

        if ($validation->passes()) {

            $data = User::find($user_id);
            $data->name = $request->name;
            $data->username = $request->username;
            $data->email = $request->email;
            $data->contact = $request->contact;
            $data->department = $request->department;
            $data->position = $request->position;
            $data->role = $request->role;
            if ($request->password) {
                $data->password = Hash::make($request->password);
            }

            if ($request->file('photo')) {

                $file = $request->file('photo');
                $ext = strtolower($file->getClientOriginalExtension());
                Storage::disk('uploads')->delete('avatars/'.$request->username);
                $avatarName = $request->username.'.'.$ext;
                $request->file('photo')->storeAs('avatars', $avatarName, 'uploads');
                $data->photo = $avatarName;

            }

            if ($data->save()) {
                SystemLogs::saveLogs('successfully updated '.$request->name.' user account information!');
                $msg = $request->name.' account has been successfully updated!';
                $request->session()->flash('msg', $msg);

                return response()->json(['success' => true, 'message' => 'record added', 'url' => url('admin/accounts')]);
            }
        }

        $errors = $validation->errors();
        $errors = json_decode($errors);

        return response()->json(['success' => false, 'message' => $errors]);
    }

    public function status(Request $request)
    {
        $id = decrypt($request->id);
        $update = User::find($id);

        if ($update->status == 1) {
            $update->status = 0;
            $msg = 'Block';
        } else {
            $update->status = 1;
            $msg = 'Unblocked';
        }

        if ($update->save()) {

            SystemLogs::saveLogs($update->name.' account has been successfully '.strtolower($msg).'!');

            $msg = '<strong><font size="3" color="green">'.$update->name.' account has been successfully '.$msg.'! </font></strong>';

            return response()->json(['success' => true, 'message' => $msg, 'status' => $update->status]);
        }

        return response()->json(['success' => false, 'message' => '<strong><font size="3" color="red">An error has occurred while  updating record! </font></strong>']);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request)
    {

        $id = decrypt($request->id);
        $update = User::find($id);
        $update->status = 0;
        $update->is_deleted = 1;

        if ($update->save()) {

            SystemLogs::saveLogs('successfully deleted '.$update->name.' account!');
            $msg = "<strong><font size='3' color='green'>".$update->name.' account has been successfully deleted! </font></strong>';
            $request->session()->flash('msg', $msg);

            return response()->json(['success' => true, 'message' => $msg, 'page' => url('accounts/manage/table')]);
        }

        return response()->json(['success' => false, 'message' => "<strong><font size='3' color='red'>An error has occurred while  updating record! </font></strong>", 'page' => url('accounts/manage/table')]);
    }

    public function info(Request $request)
    {

        $id = $request->id;
        $find = User::find($id);

        return response()->json(['success' => true, 'info' => $find]);
    }
}
