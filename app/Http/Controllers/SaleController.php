<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Sale;
use Gate;

date_default_timezone_set('Asia/Ho_Chi_Minh');

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $salesQuery = Sale::with('admin');
        if ($request->filled('id') || $request->has('data')) {
            $salesQuery->where(function($query) use ($request, $user) {
                if ($request->filled('id')) {
                $employeeId = $request->input('id');

                if (Gate::allows('QUẢN LÍ DATA.viewall')) {
                    $query->whereHas('admin', function ($subQuery) use ($employeeId) {
                        $subQuery->where('id', $employeeId);
                    });
                } 
                elseif ($user->is_manager && $user->business_group_id) {
                    $query->whereHas('admin', function ($subQuery) use ($employeeId, $user) {
                        $subQuery->where('id', $employeeId)
                            ->where('business_group_id', $user->business_group_id);
                    });
                } 
                else {
                    $query->whereHas('admin', function ($subQuery) use ($employeeId) {
                        $subQuery->where('id', $employeeId);
                    });
                }
            }
                if ($request->has('data')) {
                    $searchTerm = $request->data;
                    if ($request->filled('id')) {
                        $query->where(function($subQuery) use ($searchTerm) {
                            $subQuery->where('customer_name', 'like', "%$searchTerm%")
                                ->orWhereHas('admin', function ($adminQuery) use ($searchTerm) {
                                    $adminQuery->where('business_name', 'like', "%$searchTerm%");
                                })
                                ->orWhere('item', 'like', "%$searchTerm%")
                                ->orWhere('sales_result', 'like', "%$searchTerm%");
                        });
                    } else {
                        $query->where('customer_name', 'like', "%$searchTerm%")
                            ->orWhereHas('admin', function ($adminQuery) use ($searchTerm) {
                                $adminQuery->where('business_name', 'like', "%$searchTerm%");
                            })
                            ->orWhere('item', 'like', "%$searchTerm%")
                            ->orWhere('sales_result', 'like', "%$searchTerm%");
                    }
                }
            });
        }
        
        if ($request->has('start_time') || $request->has('end_time')) {
            try {
                $startTime = $request->input('start_time');
                $endTime = $request->input('end_time');
    
                if (!empty($startTime) && !empty($endTime)) {
                    $startTime = \Carbon\Carbon::createFromFormat('d/m/Y', $startTime)->startOfDay();
                    $endTime = \Carbon\Carbon::createFromFormat('d/m/Y', $endTime)->endOfDay();
                    $salesQuery->whereBetween('start_time', [$startTime, $endTime]);
                } elseif (!empty($startTime)) {
                    $startTime = \Carbon\Carbon::createFromFormat('d/m/Y', $startTime)->startOfDay();
                    $now = \Carbon\Carbon::now()->endOfDay();
                    $salesQuery->whereBetween('start_time', [$startTime, $now]);
                } elseif (!empty($endTime)) {
                    $endTime = \Carbon\Carbon::createFromFormat('d/m/Y', $endTime)->endOfDay();
                    $salesQuery->where('start_time', '<=', $endTime);
                }
            } catch (\Exception $e) {
                return response()->json(['status' => false, 'message' => 'Invalid date format for filtering'], 400);
            }
        }

        if (Gate::allows('QUẢN LÍ KHÁCH HÀNG.view')) { 
        } elseif ($user->is_manager && $user->business_group_id) {
            $salesQuery->whereHas('admin', function ($query) use ($user) {
                $query->where('business_group_id', $user->business_group_id);
            });

        } else {
            $salesQuery->where('user_name', $user->username);
        }

        $sales = $salesQuery->orderBy('id', 'desc')->paginate(10);
        //$sales = $salesQuery->orderBy('start_time', 'desc')->paginate(10); 
        $nows = now()->timestamp;
        $now = date('d-m-Y, g:i:s A', $nows);
        DB::table('adminlogs')->insert([
        'admin_id' => Auth::guard('admin')->user()->id,
        'time' => $now,
        'ip' => $request->ip() ?? null,
        'action' => 'index data',
        'cat' => 'admin',
        ]);

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

        if (!Gate::allows('QUẢN LÍ KHÁCH HÀNG.destroy')) { 
            return response()->json([
                'status' => false,
                'message' => 'no permission',
            ], 403);
        }

        try {
            $nows = now()->timestamp;
            $now = date('d-m-Y, g:i:s A', $nows);
            DB::table('adminlogs')->insert([
            'admin_id' => Auth::guard('admin')->user()->id,
            'time' => $now,
            'ip' => $request->ip() ?? null,
            'action' => 'destroy data',
            'cat' => 'admin',
            ]);
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

        if (!Gate::allows('QUẢN LÍ KHÁCH HÀNG.delete')) { 
            return response()->json([
                'status' => false,
                'message' => 'no permission',
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
            $nows = now()->timestamp;
            $now = date('d-m-Y, g:i:s A', $nows);
            DB::table('adminlogs')->insert([
            'admin_id' => Auth::guard('admin')->user()->id,
            'time' => $now,
            'ip' => $request->ip() ?? null,
            'action' => 'delete data',
            'cat' => 'admin',
            ]);
    
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

    public function edit(string $id){
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json([
                'status' => false,
                'message' => 'Data id not found'
            ], 404);
        }

        $nows = now()->timestamp;
        $now = date('d-m-Y, g:i:s A', $nows);
        DB::table('adminlogs')->insert([
        'admin_id' => Auth::guard('admin')->user()->id,
        'time' => $now,
        'ip' => $request->ip() ?? null,
        'action' => 'edit data',
        'cat' => 'admin',
        ]);

        return response()->json([
            'status' => true,
            'data' => [
            'customer_name' => $sale->customer_name,
            'item' => $sale->item,
            'quantity' => $sale->quantity,
            'sales_result' => $sale->sales_result,
            'note' => $sale->note,
        ],]);
    }

    public function updateNote(Request $request, string $id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json([
                'status' => false,
                'message' => 'Data id not found'
            ], 404);
        }

        $request->validate([
            'note' => 'nullable|string', 
        ]);

        try {
            $sale->note = $request->input('note');
            $sale->save();

            $nows = now()->timestamp;
            $now = date('d-m-Y, g:i:s A', $nows);
            DB::table('adminlogs')->insert([
            'admin_id' => Auth::guard('admin')->user()->id,
            'time' => $now,
            'ip' => $request->ip() ?? null,
            'action' => 'update note',
            'cat' => 'admin',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Note updated successfully',
                'data' => [
                    'note' => $sale->note, 
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error($e); 
            return response()->json([
                'status' => false,
                'message' => 'Failed to update note'
            ], 500);
        }
    }
}