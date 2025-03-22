<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SalesImport;
use Carbon\Carbon; 

class CheckAndImportExcel extends Command
{
    protected $signature = 'check:import-excel';
    protected $description = 'Check if the Excel file has been updated and import data if necessary';

    protected $importPath = 'C:\\Users\\longh\\OneDrive - chinhnhan.vn\\Folder Import';

    protected function removeDuplicates(){
        $totalDeleted = 0;

        DB::beginTransaction();
        try {
            $duplicates = DB::table('sales AS s1')
                ->join('sales AS s2', function ($join) {
                    $join->on('s1.business_name', '=', 's2.business_name')
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
                $this->info("Đã xóa $deletedCount bản ghi trùng lặp");
            }

            DB::commit();
            return $totalDeleted;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Lỗi khi xóa trùng: " . $e->getMessage());
            return 0;
        }
    }

    public function handle()
    {
        if (!File::exists($this->importPath)) {
            $this->error("Không tìm thấy thư mục: {$this->importPath}");
            return;
        }

        $this->info("Bắt đầu theo dõi thư mục: {$this->importPath}");

        while (true) {
            try {
                $files = collect(File::files($this->importPath))->filter(function ($file) {
                    $extension = strtolower($file->getExtension());
                    $fileName = $file->getFilename();
                    
                    return in_array($extension, ['xlsx', 'xls']) 
                        && !in_array($fileName, ['desktop.ini', 'thumbs.db'])
                        && !str_starts_with($fileName, '~$'); 
                });

                foreach ($files as $file) {
                    $fileName = $file->getFilename();
                    $filePath = str_replace('\\', '/', $file->getPathname());

                    try {
                        $startTime = Carbon::now();
                        $this->info("------------------------------------------");
                        $this->info("Bắt đầu xử lý file: {$fileName}");
                        $this->info("Thời gian bắt đầu: " . $startTime->format('d/m/Y H:i:s'));

                        if ($this->safeImport($filePath, $fileName)) {
                            $this->info("Kiểm tra dữ liệu trùng lặp...");
                            //$deletedCount = $this->removeDuplicates();

                            $endTime = Carbon::now();
                            $importDuration = $endTime->diffInSeconds($startTime);

                            $this->info("Kết quả xử lý:");
                            $this->info("- File đã import: {$fileName}");
                            // if ($deletedCount > 0) {
                            //     $this->info("- Đã xóa {$deletedCount} bản ghi trùng lặp");
                            // } else {
                            //     $this->info("- Không có dữ liệu trùng lặp");
                            // }
                            $this->info("- Thời gian xử lý: {$importDuration} giây");
                            $this->info("- Hoàn thành lúc: " . $endTime->format('d/m/Y H:i:s'));
                        }

                    } catch (\Exception $e) {
                        $this->error("Lỗi xử lý file {$fileName}:");
                        $this->error("- " . $e->getMessage());
                        
                        \Log::error("Import Error for {$fileName}: " . $e->getMessage());
                        \Log::error($e->getTraceAsString());
                    }
                }

                if ($files->isEmpty()) {
                    $this->info("Đang chờ file mới... (" . Carbon::now()->format('d/m/Y H:i:s') . ")");
                }

                sleep(5); 

            } catch (\Exception $e) {
                $this->error("Lỗi hệ thống: " . $e->getMessage());
                \Log::error("System Error: " . $e->getMessage());
                \Log::error($e->getTraceAsString());
                sleep(10); 
            }
        }
    }

    protected function safeImport($filePath, $fileName)
    {
        $tempPath = null;
        try {
            $tempDir = storage_path('app/temp');
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $fileName);
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . $safeName;

            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \Exception("Không thể đọc file gốc: {$fileName}");
            }

            if (file_put_contents($tempPath, $content) === false) {
                throw new \Exception("Không thể tạo file tạm: {$safeName}");
            }

            Excel::import(new SalesImport, $tempPath);

            if (file_exists($filePath)) {
                if (!@unlink($filePath)) {
                    $this->warn("Không thể xóa file gốc: {$fileName}");
                }
            }
            if (file_exists($tempPath)) {
                if (!@unlink($tempPath)) {
                    $this->warn("Không thể xóa file tạm: {$safeName}");
                }
            }
            $this->info("Đã xóa file sau khi import thành công: {$fileName}");
            return true;

        } catch (\Exception $e) {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            throw $e;
        }
    }
}