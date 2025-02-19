<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// API key của bạn
$apiKey = $_ENV['API_KEY'];  // Lấy API Key từ biến môi trường

// Đọc file dữ liệu
$filePath = __DIR__ . "/data/input.txt";
$questionsFilePath = __DIR__ . "/data/questions.txt";
$answersDir = __DIR__ . "/data/answers"; // Thư mục chứa câu trả lời

// Tạo thư mục nếu chưa có
if (!is_dir($answersDir)) {
    mkdir($answersDir, 0777, true);
}

if (!file_exists($filePath) || !file_exists($questionsFilePath)) {
    die("File input hoặc file câu hỏi không tồn tại.");
}

// Lấy nội dung của file
$inputContent = file_get_contents($filePath);
$questions = file($questionsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Tạo prompt để gửi đến API Copilot
$prompt = "Dưới đây là nội dung:\n" . $inputContent . "\n\n";
$prompt .= "Trả lời các câu hỏi sau:\n";

foreach ($questions as $index => $question) {
    $prompt .= ($index + 1) . ". " . $question . "\n";
}

// Cấu hình request cho cURL
$url = "https://api.copilot.com/v1/answers";

$data = [
    "prompt" => $prompt,
    "max_tokens" => 1500,
    "temperature" => 0.7
];

$jsonData = json_encode($data);

// Khởi tạo cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey // Đảm bảo API key được gửi đúng cách
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

// Biến cờ để kiểm tra kết quả
$isResponseValid = false;
$maxRetries = 5; // Giới hạn số lần thử
$retryCount = 0;
$response = null;

while (!$isResponseValid && $retryCount < $maxRetries) {
    // Thực hiện request
    $response = curl_exec($ch);
    $retryCount++;

    // Kiểm tra lỗi
    if (!$response) {
        echo "Lỗi khi gọi API, thử lại...\n";
        echo "cURL Error: " . curl_error($ch) . "\n"; // Thêm thông tin lỗi cURL
        continue; // Tiếp tục vòng lặp nếu có lỗi
    }

    // Chuyển đổi JSON về mảng PHP
    $responseData = json_decode($response, true);

    // Kiểm tra nếu dữ liệu hợp lệ
    if (isset($responseData['answers'][0]['text'])) {
        $isResponseValid = true;
    } else {
        echo "Không có dữ liệu hợp lệ từ API, thử lại...\n";
        echo "Response: " . $response . "\n"; // Thêm thông tin phản hồi từ API

        // Kiểm tra lỗi hạn mức
        if (isset($responseData['error']['code']) && $responseData['error']['code'] == 'insufficient_quota') {
            echo "Bạn đã vượt quá hạn mức sử dụng API. Vui lòng kiểm tra kế hoạch và chi tiết thanh toán của bạn.\n";
            break; // Thoát khỏi vòng lặp nếu vượt quá hạn mức
        }

        // Thêm thông tin chi tiết lỗi
        if (isset($responseData['error'])) {
            echo "Error Type: " . $responseData['error']['type'] . "\n";
            echo "Error Message: " . $responseData['error']['message'] . "\n";
        }
    }
}

curl_close($ch);

// Kiểm tra nếu đã nhận được kết quả hợp lệ
if ($isResponseValid) {
    $answerText = $responseData['answers'][0]['text'];
    echo "=== Câu trả lời từ Copilot ===\n";
    echo $answerText . "\n";

    // Xác định số thứ tự file mới
    $count = 1;
    while (file_exists("$answersDir/answer$count.txt")) {
        $count++;
    }

    // Tạo file mới và lưu câu trả lời
    $answerFile = "$answersDir/answer$count.txt";
    file_put_contents($answerFile, $answerText);

    echo "✅ Câu trả lời đã được lưu vào: $answerFile\n";
} else {
    echo "Không thể lấy dữ liệu hợp lệ từ API sau $maxRetries lần thử.\n";
}
?>
