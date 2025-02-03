<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $val = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ],); 
        
        if ($val->fails()) {
            $errorMessage = $val->errors()->first();
        
            return response()->json([
                'status' => false,
                'message' => $errorMessage,
            ], 202);
        }


        $admin = Admin::where('username', $request->username)->first();

        if (!$admin) {
            return response()->json([
                'status' => false,
                'message' => 'Username does not exist.'
            ], 404);
        }

        if (!Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Incorrect password.'
            ], 401);
        }


        $token = $admin->createToken('Admin')->accessToken;

        return response()->json([
            'status' => true,
            'token' => $token,
            'display_name' => $admin->username
        ]);
    }


    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'success']);
    }
}
