<?php

namespace App\Imports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\ToModel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class SalesImport implements ToModel
{
    public function model(array $row)
    {
        if ($row[0] === 'Thời gian bắt đầu' || empty($row[0])) {
            return null;
        }
        $startTime = $this->convertExcelTimeToString($row[0]);
        $endTime = $this->convertExcelTimeToString($row[1]);

        return new Sale([
            'start_time' => $startTime, 
            'end_time' => $endTime,
            'business_name' => $this->getValue($row[2]),
            'user_name' => $this->getValue($row[3]),
            'customer_name' => $this->getValue($row[4]),
            'item' => $this->getValue($row[5]),
            'quantity' => $this->getValue($row[6]),
            'price' => $this->getValue($row[7]),
            'sales_result' => $this->getValue($row[8]),
            'suggestions' => $this->getValue($row[9]),
        ]);
    }

    private function getValue($value)
    {
        return empty($value) ? null : $value;
    }

    // private function convertExcelTimeToString($excelTime)
    // {
    //     if (empty($excelTime)) {
    //         return null;
    //     }

    //     if (is_numeric($excelTime)) {
    //         try {
    //             $dateTime = Date::excelToDateTimeObject($excelTime);
    //             return $dateTime->format('Y-m-d H:i:s');
    //         } catch (\Exception $e) {
    //             \Log::error("Lỗi chuyển đổi thời gian Excel (dạng số): " . $e->getMessage() . " Giá trị: " . $excelTime);
    //             return null;
    //         }
    //     } else {
    //         try {
    //             $dateTime = Carbon::parse($excelTime);
    //             return $dateTime->format('Y-m-d H:i:s'); 
    //         } catch (\Exception $e) {
    //             \Log::error("Lỗi chuyển đổi thời gian Excel (dạng chuỗi): " . $e->getMessage() . " Giá trị: " . $excelTime);
    //             return null;
    //         }
    //     }
    // }

    private function convertExcelTimeToString($excelTime)
    {
        if (empty($excelTime)) {
            return null;
        }

        try {
            if (is_numeric($excelTime)) {
                $dateTime = Date::excelToDateTimeObject($excelTime);
                return $dateTime->format('Y-m-d H:i:s');
            }

            $dateTime = Carbon::createFromFormat('d/m/Y h:i:s A', $excelTime); 
            return $dateTime->format('Y-m-d H:i:s');

        } catch (\Exception $e) {
            \Log::error("Lỗi chuyển đổi thời gian Excel: " . $e->getMessage() . " Giá trị: " . $excelTime);
            return null;
        }
    }
}