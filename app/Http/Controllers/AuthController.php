<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function login(Request $request){
        $val = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);
        if ($val->fails()) {
            return response()->json($val->errors(), 202);
        }
        $now = date('d-m-Y H:i:s');
        $stringTime = strtotime($now);
        $admin = Admin::where('username',$request->username)->first();

        if(isset($admin)!=1)
        {
            return response()->json([
                'status' => false,
                'mess' => 'username'
            ]);
        }

        $check =  $admin->makeVisible('password');


        if(Hash::check($request->password,$check->password)){

                $success= $admin->createToken('Admin')->accessToken;

                $admin->lastlogin=$stringTime;
                $admin->save();

                return response()->json([
                    'status' => true,
                    'token' => $success,
                    'username'=>$admin->display_name
                ]);
        }else {

            return response()->json([
                    'status' => false,
                    'mess' => 'pass'
            ]);
        }
    }


    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'success']);
    }
}
