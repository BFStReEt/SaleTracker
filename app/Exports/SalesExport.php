<?php
// filepath: /c:/xampp/htdocs/SalesTracker/app/Exports/SalesExport.php
namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesExport implements FromCollection, WithHeadings
{
    protected $salesQuery;

    public function __construct($salesQuery)
    {
        $this->salesQuery = $salesQuery;
    }

    public function collection()
    {
        return $this->salesQuery->get([
            'start_time', 'end_time', 'business_name', 'customer_name', 'item', 'quantity', 'price', 'sales_result'
        ]);
    }

    public function headings(): array
    {
        return [
            'Thời gian bắt đầu',
            'Thời gian kết thúc',
            'Tên kinh doanh',
            'Tên khách hàng',
            'Mặt hàng',
            'Số lượng',
            'Giá',
            'Kết quả bán hàng'
        ];
    }
}