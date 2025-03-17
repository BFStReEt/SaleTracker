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

    protected function removeDuplicates()
    {
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
            $this->error("Directory not found: {$this->importPath}");
            return;
        }

        while (true) {
             $files = collect(File::files($this->importPath))->filter(function ($file) {
                $extension = strtolower($file->getExtension());
                $fileName = $file->getFilename();
                
                return in_array($extension, ['xlsx', 'xls']) 
                    && !in_array($fileName, ['desktop.ini', 'thumbs.db'])
                    && !str_starts_with($fileName, '~$'); 
            });


            foreach ($files as $file) {
                $fileName = $file->getFilename();
                $filePath = $file->getPathname();

                if (!in_array($file->getExtension(), ['xlsx', 'xls'])) {
                    $this->info("File {$fileName} is not an Excel file. Skipping...");
                    continue;
                }

                $lastModified = $file->getMTime();
                $lastImported = cache('last_imported_time_' . $fileName);

                if (!$lastImported || $lastModified > $lastImported) {
                    $startTime = Carbon::now(); 
                    $this->info("Import started for {$fileName} at: " . $startTime->toDateTimeString());

                    try {
                        Excel::import(new SalesImport, $filePath);
                        
                        $this->info("Kiểm tra và xóa dữ liệu trùng lặp...");
                        $deletedCount = $this->removeDuplicates();
                        
                        cache(['last_imported_time_' . $fileName => $lastModified]);
                        File::delete($filePath);

                        $endTime = Carbon::now(); 
                        $importDuration = $endTime->diffInSeconds($startTime); 

                        $this->info("Data imported successfully from file {$fileName}. File has been deleted.");
                        if ($deletedCount > 0) {
                            $this->info("Đã xóa tổng cộng {$deletedCount} bản ghi trùng lặp");
                        } else {
                            $this->info("Không phát hiện dữ liệu trùng lặp");
                        }
                        $this->info("Import completed in {$importDuration} seconds at: " . $endTime->toDateTimeString());

                    } catch (\Exception $e) {
                        $this->error("Import failed for {$fileName}: " . $e->getMessage());
                        $this->error("Error details: " . $e->getTraceAsString());
                    }
                } else {
                    $this->info("No changes detected in file {$fileName}.");
                }
            }

            $this->info("Waiting for new files...");
            sleep(5);
        }
    }
}