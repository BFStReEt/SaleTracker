<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Blacklist_import;
use Carbon\Carbon;

class CheckAndImportBlacklist extends Command
{
    protected $signature = 'check:import-blacklist';
    protected $description = 'Check and import blacklist from Excel files in public/blacklist directory';

    public function handle()
    {
        $directory = public_path('blacklist');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        while (true) {
            $files = File::files($directory);

            foreach ($files as $file) {
                $fileName = $file->getFilename();
                $filePath = $file->getPathname();

                
                if (!in_array($file->getExtension(), ['xlsx', 'xls'])) {
                    $this->info("File {$fileName} không phải file Excel. Bỏ qua...");
                    continue;
                }

                $lastModified = $file->getMTime();
                $lastImported = cache('blacklist_imported_' . $fileName);

                if (!$lastImported || $lastModified > $lastImported) {
                    $startTime = Carbon::now();
                    $this->info("Bắt đầu import file {$fileName} lúc: " . $startTime->toDateTimeString());

                    try {
                        Excel::import(new Blacklist_import, $filePath);
                        cache(['blacklist_imported_' . $fileName => $lastModified]);
                        File::delete($filePath);

                        $endTime = Carbon::now();
                        $importDuration = $endTime->diffInSeconds($startTime);

                        $this->info("Import thành công file {$fileName}. File đã được xóa.");
                        $this->info("Hoàn thành trong {$importDuration} giây lúc: " . $endTime->toDateTimeString());

                    } catch (\Exception $e) {
                        $this->error("Import thất bại file {$fileName}: " . $e->getMessage());
                    }
                } else {
                    $this->info("Không có thay đổi trong file {$fileName}.");
                }
            }

            sleep(5); 
        }
    }
}