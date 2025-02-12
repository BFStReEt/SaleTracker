<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\Admin;
use App\Models\BusinessGroup;
use App\Models\DefaultPassword;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = Auth::guard('admin')->user();

        if (!$currentUser) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 401);
        }

        $query = Admin::with('businessGroup'); 

        if ($currentUser->is_default === 1) {
        // } elseif ($currentUser->is_manager && $currentUser->business_group_id) {
        //     $query->where('business_group_id', $currentUser->business_group_id);
        // 
        } else {
            return response()->json(['status' => false, 'message' => 'No permission.'], 403);
        }

        $searchUsername = $request->query('data');
        if (!empty($searchUsername)) {
            $query->where('username', 'like', '%' . $searchUsername . '%');
        }

        // Tìm kiếm theo nhóm (nếu có)
        $searchBusinessGroupId = $request->query('group_id');
        if (!empty($searchBusinessGroupId)) {
            $query->where('business_group_id', $searchBusinessGroupId);
        }

        $users = $query->orderBy('id', 'asc')->paginate(10);


        if ($users instanceof \Illuminate\Http\JsonResponse) { 
            return $users;
        }

        $formattedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'business_group_id' => $user->business_group_id,
                'business_group_name' => $user->businessGroup ? $user->businessGroup->name : null,

            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formattedUsers,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'total_pages' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
   
    public function create()
    {
    }

    
    public function store(Request $request)
    {
        $currentUser = Auth::guard('admin')->user();

        if (!$currentUser->is_default) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to create new admins.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:admins',
            'display_name' => 'required|string',
            'email' => 'nullable|email|unique:admins',
            'business_group_id' => 'required|exists:business_groups,id',
            'is_manager' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $userAdmin = new Admin();
            $userAdmin->username = $request->username;
            $userAdmin->display_name = $request->display_name;
            $userAdmin->email = $request->email;
            $userAdmin->is_manager = $request->input('is_manager', 0);

            $businessGroupId = $request->input('business_group_id');

            if ($userAdmin->is_manager && $businessGroupId) {
                $existingManager = Admin::where('business_group_id', $businessGroupId)
                    ->where('is_manager', 1)
                    ->first();

                if ($existingManager) {
                    return response()->json([
                        'status' => false,
                        'message' => 'This business group already has a manager.'
                    ], 400);
                }
            }
            $userAdmin->business_group_id = $businessGroupId;


            $defaultPassword = DefaultPassword::where('key', 'default_password')->value('value'); 
            if (!$defaultPassword) {
                return response()->json([
                    'status' => false,
                    'message' => 'Default password not found.'
                ], 500);
            }

            $userAdmin->password = $defaultPassword;
            $userAdmin->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'successful',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Failed to create admin. Please try again.'
            ], 500);
        }
    }

    public function show(string $id)
    {
        $currentUser = auth('admin')->user(); 
        if (!$currentUser) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        if (!$currentUser->is_default) { 
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to view this admin.',
            ], 403); 
        }

        $user = Admin::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'admin_detail' => [
                'username' => $user->username,
                //'pasword' => $user->password,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'is_manager' => $user->is_manager,
                'business_group_id' => $user->business_group_id, 
            ],
        ]);
    }

    public function getProfile(Request $request){

        $user = auth('admin')->user(); 
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

    $defaultPassword = DefaultPassword::where('key', 'default_password')->value('value');

    $check_password = false; 

    if ($defaultPassword && Hash::check($user->password, $defaultPassword)) {
        $check_password = true;
    }

        return response()->json([
            'status' => true,
            'admin_detail' => [
                'username' => $user->username,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'is_manager' => $user->is_manager,
                'is_admin' => $user->is_default ? true : false,
                'check_password' => $check_password,
            ],
        ]);
    }

    public function edit(string $id){
        
    }

    public function updateID(Request $request)
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'required|string',
            'email' => 'required|email|unique:admins,email,' . $user->id, 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->display_name = $request->input('display_name');
        $user->email = $request->input('email');
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $user = Admin::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'nullable|string',
            'email' => 'nullable|email|unique:admins,email,' . $id,
            'is_manager' => 'boolean',
            'business_group_id' => 'required|exists:business_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user->display_name = $request->input('display_name');
            $user->email = $request->input('email');

            $isManager = $request->input('is_manager');
            $user->is_manager = $isManager ? 1 : 0;
            $user->save(); 

            $businessGroupId = $request->input('business_group_id');

            if ($user->is_manager) {
                $originalBusinessGroupId = $user->getOriginal('business_group_id');

                if ($originalBusinessGroupId && $originalBusinessGroupId != $businessGroupId) {
                    BusinessGroup::where('id', $originalBusinessGroupId)->update(['manager_id' => null]);
                }

                if ($businessGroupId) {
                    $existingManager = Admin::where('business_group_id', $businessGroupId)
                        ->where('is_manager', 1)
                        ->where('id', '!=', $user->id)
                        ->first();

                    if ($existingManager) {
                        DB::rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => 'This business group already has a manager.'
                        ], 400);
                    }

                    BusinessGroup::where('id', $businessGroupId)->update(['manager_id' => $user->id]);
                }
            } else {
                $originalBusinessGroupId = $user->getOriginal('business_group_id');
                if ($originalBusinessGroupId) {
                    BusinessGroup::where('id', $originalBusinessGroupId)->update(['manager_id' => null]);
                }
                $businessGroupId = null; 
            }

            $user->business_group_id = $businessGroupId; 
            $user->save(); 

            DB::commit();

            $user->refresh(); 
            $user->load('businessGroup'); 

            return response()->json([
                'status' => true,
                'message' => 'Update successful',
                'admin_detail' => [
                    'username' => $user->username,
                    'email' => $user->email,
                    'display_name' => $user->display_name,
                    'business_group_id' => $user->business_group_id,
                    'is_manager' => $user->is_manager,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Update failed'
            ], 500);
        }
    }
    
    public function delete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:admins,id',
            ]);
    
            $ids = $request->input('ids'); 

            if (is_array($ids)) {
                $ids = implode(",", $ids);
            }

            $idsArray = explode(",", $ids); 
            $currentUser = Auth::guard('admin')->user();
        
            foreach ($idsArray as $id) {
                $user = Admin::find($id);

                if (!$user) {
                    return response()->json([
                        'status' => false,
                        'message' => "Admin với ID $id không tồn tại"
                    ], 404);
                }

                if ($user->id === $currentUser->id) {
                    return response()->json([
                        'status' => false,
                        'message' => "You cannot delete your own account (ID: $id)"
                    ], 400);
                }

                if ($user->id === 1) {
                    return response()->json([
                        'status' => false,
                        'message' => "Can delete Super Admin"
                    ], 400);
                }

                $user->delete();
            }
    
            return response()->json([
                'status' => true,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Lỗi khi xóa dữ liệu'], 500);
        }

        
    }

    public function destroy(string $id)
    {
        $currentUser = Auth::guard('admin')->user();

        try {
            $userToDelete = Admin::findOrFail($id);

            if ($userToDelete->id === $currentUser->id) {
                return response()->json([
                    'status' => false,
                    'message' => "You cannot delete your own account (ID: $id)"
                ], 400); 
            }

            if ($userToDelete->is_default === 1) {  
                return response()->json([
                    'status' => false,
                    'message' => "Cannot delete Super Admin" 
                ], 400);
            }

            if ($userToDelete->is_manager) {
                BusinessGroup::where('manager_id', $userToDelete->id)->update(['manager_id' => null]);
            }

            $userToDelete->delete();

            return response()->json([
                'status' => true,
                'message' => 'Admin deleted successfully.'
            ]);

        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Admin not found.'
                ], 404);
            } else {
                \Log::error($e); 
                return response()->json([
                    'status' => false,
                    'message' => 'An error occurred during deletion.'
                ], 500);
            }
        }
    }

    public function updateDefaultPassword(Request $request)
    {
        $newPassword = $request->input('default_password');

        $request->validate([
            'default_password' => 'required',
        ]);

        $hashedPassword = Hash::make($newPassword);

        DefaultPassword::where('key', 'default_password')->update(['value' => $hashedPassword]);

        return response()->json([
            'status' => true,
            'message' => 'Update default passwrod successfully.'
        ]);
    }
   
    public function updatePasswordID(string $id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'status' => false,
                'message' => 'Admin not found.'
            ], 404);
        }

        $currentUser = Auth::guard('admin')->user();
        if (!$currentUser->is_default) { 
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to update this admin\'s password.'
            ], 403);
        }

        try {
            $defaultPassword = DefaultPassword::where('key', 'default_password')->value('value');
            if (!$defaultPassword) {
                return response()->json([
                    'status' => false,
                    'message' => 'Default password not found.'
                ], 500);
            }

            $admin->password = $defaultPassword; 
            $admin->must_change_password = true;
            $admin->save();

            return response()->json([
                'status' => true,
                'message' => 'Password updated successfully.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Failed to update password. Please try again.'
            ], 500);
        }
    }

    // public function changePassword(Request $request){
    //     $currentUser = Auth::guard('admin')->user();

    // }
}
