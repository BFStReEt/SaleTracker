<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;

class SaleController extends Controller
{
    public function index()
    {
        $user = Auth::guard('admin')->user();
    
        if ($user->id === 1) {
            $sales = Sale::paginate(10);
        } else {
            $sales = Sale::where('user_name', $user->username)->paginate(10);
        }
    
        return response()->json([
            'status' => true,
            'data' => $sales,
        ]);
    }
}
