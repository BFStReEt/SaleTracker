<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Sale;

use Gate;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SalesExport;

date_default_timezone_set('Asia/Ho_Chi_Minh');

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::guard('admin')->user();
        
        $salesQuery = Sale::with('admin')->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('blacklists')
                ->whereColumn('blacklists.business_name', 'sales.customer_name');
        });

        if ($request->filled('id') || $request->has('data')) {
            $salesQuery->where(function ($query) use ($request, $user) {
                if ($request->filled('id')) {
                    $employeeId = $request->input('id');
                    
                    if (Gate::allows('QUẢN LÍ DATA.viewall')) {
                        $query->whereHas('admin', function ($subQuery) use ($employeeId) {
                            $subQuery->where('id', $employeeId);
                        });
                    } elseif ($user->is_manager && $user->business_group_id) {
                        $query->whereHas('admin', function ($subQuery) use ($employeeId, $user) {
                            $subQuery->where('id', $employeeId)
                                ->where('business_group_id', $user->business_group_id);
                        });
                    } else {
                        $query->whereHas('admin', function ($subQuery) use ($employeeId) {
                            $subQuery->where('id', $employeeId);
                        });
                    }
                }

                if ($request->has('data')) {
                    $searchTerm = $request->data;
                    $query->where(function ($subQuery) use ($searchTerm) {
                        $subQuery->where(function ($q) use ($searchTerm) {
                            $q->where('customer_name', 'like', "%$searchTerm%")
                                ->whereNotExists(function ($blacklistQuery) {
                                    $blacklistQuery->select(DB::raw(1))
                                        ->from('blacklists')
                                        ->whereColumn('blacklists.business_name', 'sales.customer_name');
                                });
                        })
                        ->orWhereHas('admin', function ($adminQuery) use ($searchTerm) {
                            $adminQuery->where('business_name', 'like', "%$searchTerm%");
                        })
                        ->orWhere('item', 'like', "%$searchTerm%")
                        ->orWhere('sales_result', 'like', "%$searchTerm%");
                    });
                }
            });
        }

        if ($request->has('start_time') || $request->has('end_time')) {
            try {
                $startTime = $request->input('start_time');
                $endTime = $request->input('end_time');

                if (!empty($startTime) && !empty($endTime)) {
                    $startTime = Carbon::createFromFormat('d/m/Y', $startTime)->startOfDay();
                    $endTime = Carbon::createFromFormat('d/m/Y', $endTime)->endOfDay();
                    $salesQuery->whereBetween('start_time', [$startTime, $endTime]);
                } elseif (!empty($startTime)) {
                    $startTime = Carbon::createFromFormat('d/m/Y', $startTime)->startOfDay();
                    $now = Carbon::now()->endOfDay();
                    $salesQuery->whereBetween('start_time', [$startTime, $now]);
                } elseif (!empty($endTime)) {
                    $endTime = Carbon::createFromFormat('d/m/Y', $endTime)->endOfDay();
                    $salesQuery->where('start_time', '<=', $endTime);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Invalid date format for filtering'
                ], 400);
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

        if ($request->has('export')) {
            // if (!$user->is_default) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'no permission'
            //     ], 403);
            // }
            return Excel::download(new SalesExport($salesQuery), 'sales_data.xlsx');
        }

        $sales = $salesQuery->orderBy('id', 'desc')->paginate(10);

        $nows = now()->timestamp;
        $now = date('d-m-Y, g:i:s A', $nows);
        DB::table('adminlogs')->insert([
            'admin_id' => $user->id,
            'time' => $now,
            'ip' => $request->ip() ?? null,
            'action' => 'index data',
            'cat' => $user->display_name,
            'page' => 'Quản lí khách hàng',
        ]);

        $formattedSales = $sales->map(function ($sale) {
            return [
                "id" => $sale->id,
                "start_time" => $sale->start_time ? Carbon::parse($sale->start_time)->format('d/m/Y g:i:s A') : null,
                "end_time" => $sale->end_time ? Carbon::parse($sale->end_time)->format('d/m/Y g:i:s A') : null,
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
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total()
            ]
        ]);
    }

    public function destroy(Request $request, string $id)
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
                'cat' => $user->display_name,
                'page' => 'Quản lí khách hàng',
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
                'cat' => $user->display_name,
                'page' => 'Quản lí khách hàng',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi xóa dữ liệu'
            ], 500);
        }
    }

    public function edit(Request $request, string $id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json([
                'status' => false,
                'message' => 'Data id not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'customer_name' => $sale->customer_name,
                'item' => $sale->item,
                'quantity' => $sale->quantity,
                'sales_result' => $sale->sales_result,
                'note' => $sale->note,
            ],
        ]);
    }

    public function updateNote(Request $request, string $id)
    {
        $sale = Sale::find($id);
        $user = Auth::guard('admin')->user();
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
                'cat' => $user->display_name,
                'page' => 'Quản lí khách hàng',

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