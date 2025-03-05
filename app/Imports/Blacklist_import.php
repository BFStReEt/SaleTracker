<?php

namespace App\Imports;

use App\Models\Blacklist;
use Maatwebsite\Excel\Concerns\ToModel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalesImport implements ToModel
{
    public function model(array $row)
    {
        if ($row[0] === 'Tên khách hàng bỏ ra khỏi báo cáo' || empty($row[0])) {
            return null;
        }

        $existingRecord = BusinessReason::where('business_name', $row[0])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existingRecord) {
            $existingRecord->update([
                'business_name' => $row[0],
                'reason' => $row[1] ?? null,
            ]);
            return $existingRecord;
        }

        return new BusinessReason([
            'business_name' => $row[0],
            'reason' => $row[1] ?? null,
        ]);
  }
}