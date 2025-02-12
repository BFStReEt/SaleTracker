<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SalesImport;
use Carbon\Carbon; 

class CheckAndImportExcel extends Command
{
    protected $signature = 'check:import-excel';
    protected $description = 'Check if the Excel file has been updated and import data if necessary';

    public function handle()
    {
        $directory = public_path('data');

        while (true) {
            $files = File::files($directory);

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
                        cache(['last_imported_time_' . $fileName => $lastModified]);
                        File::delete($filePath);

                        $endTime = Carbon::now(); 
                        $importDuration = $endTime->diffInSeconds($startTime); 

                        $this->info("Data imported successfully from file {$fileName}. File has been deleted.");
                        $this->info("Import completed in {$importDuration} seconds at: " . $endTime->toDateTimeString());

                    } catch (\Exception $e) {
                        $this->error("Import failed for {$fileName}: " . $e->getMessage()); // Use 
                    }


                } else {
                    $this->info("No changes detected in file {$fileName}.");
                }
            }

            sleep(5);
        }
    }
}