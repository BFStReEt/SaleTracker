<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Admin;

class SaleController extends Controller
{
    public function index()
    {
        $user = Auth::guard('admin')->user();

        if ($user->is_default === 1) { 
            $sales = Sale::with('admin')->orderBy('id', 'desc')->paginate(10);

        } elseif ($user->is_manager && $user->business_group_id) { 
            $sales = Sale::whereHas('admin', function ($query) use ($user) {
                $query->where('business_group_id', $user->business_group_id);
            })->with('admin')->orderBy('id', 'desc')->paginate(10);

        } else { 
            $sales = Sale::where('user_name', $user->username)
                         ->with('admin')
                         ->orderBy('id', 'desc')
                         ->paginate(10);
        }

        $formattedSales = $sales->map(function ($sale) use ($user) { 
            if ($sale->start_time) {
                $sale->start_time = Carbon::parse($sale->start_time)->format('d/m/Y g:i:s A');
            }

            if ($sale->end_time) {
                $sale->end_time = Carbon::parse($sale->end_time)->format('d/m/Y g:i:s A');
            }

            $phoneNumber = null;
            if ($sale->admin) {
                if ($user->is_default === 1 || ($user->is_manager && $user->business_group_id == $sale->admin->business_group_id)) { 
                    $phoneNumber = $sale->admin->username; 
                } elseif ($user->username === $sale->user_name) { 
                    $phoneNumber = $sale->admin->username;
                }
            }

            $sale->phone_number = $phoneNumber; 
            return $sale;
        });

        return response()->json([
            'status' => true,
            'count' => $sales->total(),
            'data' => $formattedSales,
        ]);
    }

    public function destroy(string $id)
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 401);
        }

        if ($user->is_default !== 1) { 
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete this data.',
            ], 403);
        }

        try {
            $sale = Sale::findOrFail($id);

            $sale->delete();

            return response()->json([
                'status' => true,
                'message' => 'Sale deleted successfully.',
            ]);

        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found.',
                ], 404);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ], 500);
            }
        }
    }
}