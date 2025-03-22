<?php

namespace App\Imports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\ToModel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalesImport implements ToModel
{
    public function model(array $row)
    {
        if ($row[0] === 'Thời gian bắt đầu' || empty($row[0])) {
            return null;
        }

        $startTime = $this->convertExcelTimeToString($row[0]);
        $endTime = $this->convertExcelTimeToString($row[1]);

        $businessName = $this->getValue($row[2]);

        preg_match('/\d{10}/', $businessName, $matches);

        $phoneNumber = $matches[0] ?? null;

        $userName = $phoneNumber;

        $customerName = $this->getValue($row[3] ?? null);
        $item = $this->getValue($row[4] ?? null);

        $existingSale = Sale::where('business_name', $businessName)
        ->where('customer_name', $customerName)
        ->where('item', $item)
        ->orderBy('id', 'desc')->first();
       

        if ($existingSale) {
            $existingSale->update([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'business_name' => $businessName,
                'user_name' => $userName,
                'customer_name' => $this->getValue($row[3] ?? null), 
                'item' => $this->getValue($row[4] ?? null), 
                'quantity' => $this->getValue($row[5] ?? null),
                'price' => $this->formatPrice($row[6] ?? null),
                'sales_result' => $this->getValue($row[7] ?? null),
            ]);
            return $existingSale;
        }
        else{
            return new Sale([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'business_name' => $businessName,
                'user_name' => $userName,
                'customer_name' => $this->getValue($row[3] ?? null), 
                'item' => $this->getValue($row[4] ?? null), 
                'quantity' => $this->getValue($row[5] ?? null),
                'price' => $this->formatPrice($row[6] ?? null),
                'sales_result' => $this->getValue($row[7] ?? null),
            ]);
        }
    }

    private function formatPrice($price)
    {
        if (empty($price)) {
            return null;
        }

        $price = str_replace([',', ' '], '', $price);
        
        return number_format((float)$price, 0, '.', ',');
    }

    private function getValue($value)
    {
        return empty($value) ? null : $value;
    }

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
            Log::error("Lỗi chuyển đổi thời gian Excel: " . $e->getMessage() . " Giá trị: " . $excelTime);
            return null;
        }
    }
}