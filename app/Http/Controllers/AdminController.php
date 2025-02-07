<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\Admin;
use App\Models\BusinessGroup;

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
        //
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
            'password' => 'required|min:6',
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
            $userAdmin->password = Hash::make($request->password);
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
            $userAdmin->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Successful',
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
        return response()->json([
            'status' => true,
            'admin_detail' => [
                'username' => $user->username,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'is_manager' => $user->is_manager,
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
            'business_group_id' => 'nullable|exists:business_groups,id',
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

            if ($user->is_manager && $businessGroupId) {
                $existingManager = Admin::where('business_group_id', $businessGroupId)
                    ->where('is_manager', 1)
                    ->where('id', '!=', $user->id) 
                    ->first();

                if ($existingManager) {
                    return response()->json([
                        'status' => false,
                        'message' => 'This business group already has a manager.'
                    ], 400);
                }
            }


            if ($user->business_group_id != $businessGroupId) {
                $originalBusinessGroupId = $user->getOriginal('business_group_id');
                $user->business_group_id = $businessGroupId;
                $user->save();

                if ($businessGroupId) {
                    BusinessGroup::where('id', $businessGroupId)->update(['manager_id' => $user->id]);
                }

                if ($originalBusinessGroupId && $originalBusinessGroupId != $businessGroupId) {
                    BusinessGroup::where('id', $originalBusinessGroupId)->update(['manager_id' => null]);
                }
            }

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

   
}
