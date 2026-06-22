<?php

namespace App\Http\Controllers\Admin;

use App\Admin;
use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index()
    {
        $this->authorizeSettingsAccess();

        return view('admin.admins.index');
    }

    public function create()
    {
        $this->authorizeSettingsAccess();

        return view('admin.admins.create', [
            'roles' => app(RolePermissionService::class)->assignableAdminRoles(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeSettingsAccess();

        $roleSlugs = app(RolePermissionService::class)->assignableAdminRoles()->pluck('slug')->all();

        $data = $request->validate([
            'admin_name' => 'required|string|max:100',
            'admin_username' => 'required|string|max:100|unique:admins,username',
            'admin_email' => 'required|email|max:100|unique:admins,email',
            'admin_password' => 'required|string|min:6|max:100',
            'admin_role' => ['required', Rule::in($roleSlugs)],
            'admin_status' => 'required|in:active,inactive',
        ]);

        Admin::create([
            'name' => $data['admin_name'],
            'username' => $data['admin_username'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'role' => $data['admin_role'],
            'status' => $data['admin_status'],
        ]);

        return redirect(route('admin.admins.index'))->with('success', __('admins.added_success'));
    }

    public function edit($id)
    {
        $this->authorizeSettingsAccess();

        return view('admin.admins.edit', [
            'admin' => Admin::findOrFail(decrypt($id)),
            'roles' => app(RolePermissionService::class)->assignableAdminRoles(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeSettingsAccess();

        $admin = Admin::findOrFail(decrypt($id));

        $roleSlugs = app(RolePermissionService::class)->assignableAdminRoles()->pluck('slug')->all();

        $data = $request->validate([
            'admin_name' => 'required|string|max:100',
            'admin_username' => 'required|string|max:100|unique:admins,username,' . $admin->id,
            'admin_email' => 'required|email|max:100|unique:admins,email,' . $admin->id,
            'admin_password' => 'nullable|string|min:6|max:100',
            'admin_role' => ['required', Rule::in($roleSlugs)],
            'admin_status' => 'required|in:active,inactive',
        ]);

        if ((int) Auth::guard('web_admin')->id() === (int) $admin->id && $data['admin_status'] === Admin::STATUS_INACTIVE) {
            return back()->withInput()->with('error', __('admins.cannot_deactivate_self'));
        }

        $update = [
            'name' => $data['admin_name'],
            'username' => $data['admin_username'],
            'email' => $data['admin_email'],
            'role' => $data['admin_role'],
            'status' => $data['admin_status'],
        ];

        if (!empty($data['admin_password'])) {
            $update['password'] = Hash::make($data['admin_password']);
        }

        $admin->update($update);

        return redirect(route('admin.admins.index'))->with('success', __('admins.updated_success'));
    }

    public function destroy($id)
    {
        $this->authorizeSettingsAccess();

        $admin = Admin::findOrFail(decrypt($id));

        if ((int) Auth::guard('web_admin')->id() === (int) $admin->id) {
            return redirect(route('admin.admins.index'))->with('error', __('admins.cannot_delete_self'));
        }

        $admin->delete();

        return redirect(route('admin.admins.index'))->with('success', __('admins.deleted_success'));
    }

    public function fetch_admins(Request $request)
    {
        $this->authorizeSettingsAccess();

        $columns = ['id', 'options', 'admin_name', 'admin_username', 'admin_email', 'admin_role', 'admin_status', 'created_at'];
        $totalRecords = DB::table('admins')->count();
        $totalFiltered = $totalRecords;

        $limit = $request->input('length') == -1 ? $totalRecords : $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'admin_name';
        $dir = $request->input('order.0.dir') ?? 'asc';

        $query = DB::table('admins')
            ->select('id', 'name', 'username', 'email', 'role', 'status', 'created_at');

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
            $totalFiltered = (clone $query)->count();
        }

        $records = $query->offset($start)->limit($limit)->orderBy(
            $order === 'admin_name' ? 'name' : ($order === 'admin_username' ? 'username' : ($order === 'admin_email' ? 'email' : 'created_at')),
            $dir
        )->get();

        $roleNames = app(RolePermissionService::class)->assignableAdminRoles()->pluck('name', 'slug')->toArray();
        $data = [];

        foreach ($records as $record) {
            $checked = ($record->status ?? Admin::STATUS_ACTIVE) === Admin::STATUS_ACTIVE ? 'checked' : '';
            $data[] = [
                'id' => $record->id,
                'admin_name' => $record->name,
                'admin_username' => $record->username,
                'admin_email' => $record->email,
                'admin_role' => $roleNames[$record->role] ?? ucfirst((string) $record->role),
                'admin_status' => '<div class="form-check form-switch">'
                    . '<input class="form-check-input toggle-status" type="checkbox" data-id="' . $record->id . '" ' . $checked . '>'
                    . '</div>',
                'created_at' => date('d-m-Y', strtotime($record->created_at)),
                'options' => '<a href="' . route('admin.admins.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a>'
                    . '<button type="button" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#delete" data-action="' . route('admin.admins.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>',
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    public function update_status(Request $request)
    {
        $this->authorizeSettingsAccess();

        $request->validate([
            'admin_id' => 'required|exists:admins,id',
            'status' => 'required|in:active,inactive',
        ]);

        if ((int) Auth::guard('web_admin')->id() === (int) $request->admin_id && $request->status === Admin::STATUS_INACTIVE) {
            return response()->json(['success' => false, 'message' => __('admins.cannot_deactivate_self')], 422);
        }

        Admin::find($request->admin_id)->update(['status' => $request->status]);

        return response()->json(['success' => true]);
    }

    private function authorizeSettingsAccess(): void
    {
        $admin = Auth::guard('web_admin')->user();
        if (!$admin || !$admin->canManageAdminUsers()) {
            abort(403, 'Only superadmin can manage admin users.');
        }
    }
}
