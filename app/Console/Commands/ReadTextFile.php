<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Services\OpenAIService;

class ReadTextFile extends Command
{
    protected $signature = 'read:txt {filename}';
    protected $description = 'Đọc một file TXT trong thư mục và gửi nội dung đến AI để phân tích.';

    public function handle(OpenAIService $openAIService)
    {
        $filename = $this->argument('filename');
        $filePath = "txt-files/$filename";

        if (!Storage::exists($filePath)) {
            $this->error("File $filename không tồn tại!");
            return;
        }

        // Đọc nội dung file
        $content = Storage::get($filePath);
        $this->info("📄 Nội dung file:\n$content");

        // Gửi nội dung đến AI để phân tích
        $response = $openAIService->chat([
            ['role' => 'system', 'content' => 'Bạn là một AI chuyên xử lý văn bản.'],
            ['role' => 'user', 'content' => "Phân tích nội dung sau:\n$content"]
        ]);

        // Lưu kết quả vào file JSON
        $resultFile = 'processed_results/' . pathinfo($filename, PATHINFO_FILENAME) . '.json';
        Storage::put($resultFile, json_encode(['original' => $content, 'ai_response' => $response], JSON_PRETTY_PRINT));

        $this->info("✅ AI đã phân tích xong! Kết quả được lưu tại: $resultFile");
    }
}
