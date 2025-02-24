<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

date_default_timezone_set('Asia/Ho_Chi_Minh');

use Gate;

class AdminlogsController extends Controller
{
    public function index(Request $request){
        // if(!Gate::allows("Lịch sử hoạt động.index")){
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'no permission',
        //     ], 403); ss
        // }
            $adminLogs = DB::table('adminlogs')
            ->join('admins', 'adminlogs.admin_id', '=', 'admins.id')
            ->select(
                'admins.username', 
                'adminlogs.page',
                'adminlogs.action',
                'admins.display_name', 
                'adminlogs.time',
                'adminlogs.ip'
            )
            ->orderBy('adminlogs.time', 'desc') 
            ->paginate(10); 

        $nows = now()->timestamp;
        $now = date('d-m-Y, g:i:s A', $nows);
        DB::table('adminlogs')->insert([
        'admin_id' => Auth::guard('admin')->user()->id,
        'time' => $now,
        'ip' => $request->ip() ?? null,
        'action' => 'index adminlog',
        'cat' => 'admin',
        'page' => 'Lịch sử hoạt động admin'
        ]);

        return response()->json([
            'status' => true,
            'count' => $adminLogs->total(),
            'data' => $adminLogs, 
        ]);
    }

    public function destroy(string $id){

    }

    public function delete(string $id){
        if (!Gate::allows('QUẢN LÍ KHÁCH HÀNG.destroy')) { 
            return response()->json([
                'status' => false,
                'message' => 'no permission',
            ], 403);
        }
    }
}
