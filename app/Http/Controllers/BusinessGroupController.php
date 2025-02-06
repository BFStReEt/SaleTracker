<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessGroup;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;


class BusinessGroupController extends Controller
{
   
    public function index(Request $request)
    {
        $searchName = $request->query('data');

        $query = BusinessGroup::with('manager:id,display_name');

        if (!empty($searchName)) {
            $query->where('name', 'like', '%' . $searchName . '%');
        }

        $perPage = 10; 
        $page = $request->query('page', 1); 

        $businessGroups = $query->paginate($perPage, ['*'], 'page', $page); 

        $formattedBusinessGroups = $businessGroups->map(function ($businessGroup) {
            return [
                'id' => $businessGroup->id,
                'name' => $businessGroup->name,
                'description' => $businessGroup->description,
                'manager_name' => $businessGroup->manager ? $businessGroup->manager->display_name : null,
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

    public function create()
    {
       
    }

    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:business_groups',
            'description' => 'nullable',
            //'manager_id' => 'exists:admins,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'errors' => $validator->errors()], 400);
        }

        $businessGroup = BusinessGroup::create($request->all());
        return response()->json([
            'status' => true, 
            'data' => $businessGroup->name], 201); 
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $businessGroup = BusinessGroup::find($id);

        if (!$businessGroup) {
            return response()->json([
                'status' => false,
                'message' => 'Business group not found'
            ], 404);
        }

        $manager = $businessGroup->manager; 

        return response()->json([
            'status' => true,
            'data' =>[
                'name' => $businessGroup->name,
                'description' =>$businessGroup->description,
                //'manage_by' => $manager ? $manager->display_name : null, 
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
    }

    public function update(Request $request, string $id)
    {
        $businessGroup = BusinessGroup::find($id);

        if (!$businessGroup) {
            return response()->json(['status' => false, 'message' => 'Business group not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:business_groups,name,' . $id,
            'description' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        $businessGroup->fill($request->only(['name', 'description'])); 
        $businessGroup->save();

        return response()->json(['status' => true, 'message' => 'Business group updated successfully', 'data' => $businessGroup], 200);
    }


    public function delete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:business_groups,id',
            ]);
    
            $ids = $request->input('ids'); 

            if (is_array($ids)) {
                $ids = implode(",", $ids);
            }

            $idsArray = explode(",", $ids); 

            foreach ($idsArray as $id) {
                BusinessGroup::whereIn('id', $idsArray)->delete();
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
        try {
            $businessGroup = BusinessGroup::find($id);

            if (!$businessGroup) {
                return response()->json([
                    'status' => false,
                    'message' => 'Business group not found'
                ], 404);
            }

            $businessGroup->delete();

            return response()->json([
                'status' => true,
                'message' => 'Business group deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting business group: ' . $e->getMessage()
            ], 500);
        }
    }

}
