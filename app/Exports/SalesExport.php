<?php
namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class SalesExport implements FromCollection, WithHeadings
{
    protected $salesQuery;

    public function __construct($salesQuery)
    {
        $this->salesQuery = $salesQuery;
    }

    public function collection()
    {
        $sales = $this->salesQuery->get([
            'start_time', 'end_time', 'business_name', 'customer_name', 'item', 'quantity', 'price', 'sales_result'
        ]);

        $formattedSales = $sales->map(function ($sale) {
            return [
                'start_time' => Carbon::parse($sale->start_time)->format('d-m-Y H:i:s'),
                'end_time' => Carbon::parse($sale->end_time)->format('d-m-Y H:i:s'),
                'business_name' => $sale->business_name,
                'customer_name' => $sale->customer_name,
                'item' => $sale->item,
                'quantity' => $sale->quantity,
                'price' => $sale->price,
                'sales_result' => $sale->sales_result,
            ];
        });

        return $formattedSales;
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