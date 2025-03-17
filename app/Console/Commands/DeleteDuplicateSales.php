<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteDuplicateSales extends Command
{
    protected $signature = 'sales:clean-duplicates';  
    protected $description = 'Xóa các bản ghi trùng lặp trong bảng sales, giữ lại ID lớn nhất';

    public function handle()
    {
        $this->info("Bắt đầu chạy hàm xóa trùng lặp mỗi 1 giây... ");

        while (true) {
            $totalDeleted = 0;

            DB::beginTransaction();
            try {
                $duplicates = DB::table('sales AS s1')
                    ->join('sales AS s2', function ($join) {
                        $join//->on('s1.start_time', '=', 's2.start_time')
                            ->on('s1.business_name', '=', 's2.business_name')
                            ->on('s1.customer_name', '=', 's2.customer_name')
                            ->on('s1.item', '=', 's2.item');
                    })
                    ->whereRaw('s1.id < s2.id') 
                    ->orderBy('s1.id', 'ASC')
                    ->limit(500)
                    ->pluck('s1.id');

                if ($duplicates->isNotEmpty()) {
                    $deletedCount = DB::table('sales')->whereIn('id', $duplicates)->delete();
                    $totalDeleted += $deletedCount;
                    $this->info("Đã xóa $deletedCount bản ghi...");
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Lỗi: " . $e->getMessage());
            }

            if ($totalDeleted === 0) {
                $this->info("Không còn bản ghi trùng lặp. Đang chờ...");
            }

            sleep(1); 
        }
    }
}