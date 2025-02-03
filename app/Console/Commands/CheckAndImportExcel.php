<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SalesImport;

class CheckAndImportExcel extends Command
{
    protected $signature = 'check:import-excel';
    protected $description = 'Check if the Excel file has been updated and import data if necessary';

    public function handle()
    {
        $directory = public_path('data');
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
                Excel::import(new SalesImport, $filePath);

                cache(['last_imported_time_' . $fileName => $lastModified]);

                File::delete($filePath);

                $this->info("Data imported successfully from file {$fileName}. File has been deleted.");
            } else {
                $this->info("No changes detected in file {$fileName}.");
            }
        }
    }
}