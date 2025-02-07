<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Admin;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $salesQuery = Sale::with('admin');
    
        if ($request->has('start_time') && $request->has('end_time')) {
            try {
                $startTime = \Carbon\Carbon::createFromFormat('d/m/Y', $request->start_time)->startOfDay();
                $endTime = \Carbon\Carbon::createFromFormat('d/m/Y', $request->end_time)->endOfDay();
                $salesQuery->whereBetween('start_time', [$startTime, $endTime]);
            } catch (\Exception $e) {
                return response()->json(['status' => false, 'message' => 'Invalid date format for filtering'], 400);
            }
        }

        if ($request->has('data')) {
            $searchTerm = $request->data;
            $salesQuery->where(function ($query) use ($searchTerm) {
                $query->where('customer_name', 'like', "%$searchTerm%") 
                      ->orWhereHas('admin', function ($adminQuery) use ($searchTerm) {
                          $adminQuery->where('business_name', 'like', "%$searchTerm%");
                      })
                      ->orWhere(function($query) use ($searchTerm) { 
                            $query->where('item', 'like', "%$searchTerm%"); 
                      });
    
            });
        }

        if ($user->is_default === 1) {
        } elseif ($user->is_manager && $user->business_group_id) {
            $salesQuery->whereHas('admin', function ($query) use ($user) {
                $query->where('business_group_id', $user->business_group_id);
            });
        } else {
            $salesQuery->where('user_name', $user->username);
        }

        $sales = $salesQuery->orderBy('id', 'desc')->paginate(10);

        $formattedSales = $sales->map(function ($sale) {
            if ($sale->start_time) {
                $sale->start_time = \Carbon\Carbon::parse($sale->start_time)->format('d/m/Y g:i:s A');
            }
    
            if ($sale->end_time) {
                $sale->end_time = \Carbon\Carbon::parse($sale->end_time)->format('d/m/Y g:i:s A');
            }
    
            return [
                "id" => $sale->id,
                "start_time" => $sale->start_time,
                "end_time" => $sale->end_time,
                "business_name" => $sale->business_name,
                "customer_name" => $sale->customer_name,
                "item" => $sale->item,
                "quantity" => $sale->quantity,
                "price" => $sale->price,
                "sales_result" => $sale->sales_result,
                "suggestions" => $sale->suggestions,
            ];
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

        if ($user->is_default !== 1 && $user->is_manager !==1) { 
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

    public function delete(Request $request)
    {
        $user = Auth::guard('admin')->user();

        if ($user->is_default !== 1 && $user->is_manager !==1) { 
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete this data.',
            ], 403);
        }
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:sales,id',
            ]);
    
            $ids = $request->input('ids'); 

            if (is_array($ids)) {
                $ids = implode(",", $ids);
            }

            $idsArray = explode(",", $ids); 
        
            foreach ($idsArray as $id) {
                $sale = Sale::find($id);

                if (!$sale) {
                    return response()->json([
                        'status' => false,
                        'message' => "Data với ID $id không tồn tại"
                    ], 404);
                }

                $sale->delete();
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
}