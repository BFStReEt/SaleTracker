<?php

namespace App\Imports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\ToModel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SalesImport implements ToModel
{
    public function model(array $row)
    {
        if ($row[0] === 'Thời gian bắt đầu') {
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
        ]);
    }

    private function getValue($value)
    {
        return empty($value) ? null : $value;
    }

    private function convertExcelTimeToString($excelTime)
    {
        if (!is_numeric($excelTime)) {
            return null;
        }

        $dateTime = Date::excelToDateTimeObject($excelTime);

        return $dateTime->format('H:i:s');
    }
}