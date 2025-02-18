<?php
// API key của bạn
$apiKey = "AIzaSyDpWYzQHHUshzuZ28ZW-pq6K2tVYfi-aA4";  // Thay bằng API Key thật của bạn

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

// Tạo prompt để gửi đến API Gemini
$prompt = "Dưới đây là nội dung:\n" . $inputContent . "\n\n";
$prompt .= "Trả lời các câu hỏi sau:\n";

foreach ($questions as $index => $question) {
    $prompt .= ($index + 1) . ". " . $question . "\n";
}

// Cấu hình request cho cURL
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=$apiKey";

$data = [
    "contents" => [
        ["parts" => [["text" => $prompt]]]
    ]
];

$jsonData = json_encode($data);

// Khởi tạo cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
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
        continue; // Tiếp tục vòng lặp nếu có lỗi
    }

    // Chuyển đổi JSON về mảng PHP
    $responseData = json_decode($response, true);

    // Kiểm tra nếu dữ liệu hợp lệ
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $isResponseValid = true;
    } else {
        echo "Không có dữ liệu hợp lệ từ API, thử lại...\n";
    }
}

curl_close($ch);

// Kiểm tra nếu đã nhận được kết quả hợp lệ
if ($isResponseValid) {
    $answerText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    echo "=== Câu trả lời từ Gemini ===\n";
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
