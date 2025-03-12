<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Blacklist;
use Gate;

class BlacklistController extends Controller
{
    public function index(Request $request)
    {
        if(!Gate::allows('QUẢN LÍ BLACKLIST.index'))
        {
            return response()->json([
                'status' => false,
                'message' => 'no permission',
            ], 403);
        }
        
        $searchName = $request->query('data');
        $query = Blacklist::query(); 

        if (!empty($searchName)) {
            $query->where('business_name', 'like', '%' . $searchName . '%');
        }

        $perPage = 10;
        $page = $request->query('page', 1);

        $businessGroups = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedBusinessGroups = $businessGroups->map(function ($businessGroup) {
            return [
                'id' => $businessGroup->id,
                'business_name' => $businessGroup->business_name,
                'reason' => $businessGroup->reason,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formattedBusinessGroups,
            'pagination' => [
                'current_page' => $businessGroups->currentPage(),
                'total_pages' => $businessGroups->lastPage(),
                'per_page' => $businessGroups->perPage(),
                'total' => $businessGroups->total(),
            ],
        ]);
    }
    
    public function delete(Request $request)
    {
        if (!Gate::allows('QUẢN LÍ BLACKLIST.delete')) {
            return response()->json([
                'status' => false,
                'message' => 'no permission',
            ], 403);
        }

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:blacklists,id',
            ]);

            $ids = $request->input('ids');
            if (is_array($ids)) {
                $ids = implode(",", $ids);
            }

            $idsArray = explode(",", $ids);

            foreach ($idsArray as $id) {
                Blacklist::whereIn('id', $idsArray)->delete();
            }

            return response()->json([
                'status' => true,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi xóa dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id, Request $request)
    {
        if (!Gate::allows('QUẢN LÍ BLACKLIST.delete')) {
            return response()->json([
                'status' => false,
                'message' => 'no permission',
            ], 403);
        }

        try {
            $blacklist = Blacklist::find($id);

            if (!$blacklist) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blacklist item not found'
                ], 404);
            }

            $blacklist->delete();

            // $admin = Auth::guard('admin')->user();
            // $nows = now()->timestamp;
            // $now = date('d-m-Y, g:i:s A', $nows);
            // DB::table('adminlogs')->insert([
            //     'admin_id' => $admin->id,
            //     'time' => $now,
            //     'ip' => $request->ip() ?? null,
            //     'action' => 'destroy blacklist',
            //     'cat' => $admin->display_name,
            //     'page' => 'Quản lí blacklist',
            // ]);

            return response()->json([
                'status' => true,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting blacklist item: ' . $e->getMessage()
            ], 500);
        }
    }
}