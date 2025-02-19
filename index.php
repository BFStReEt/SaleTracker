<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['API_KEY'];

$filePath = __DIR__ . "/data/input.txt";
$questionsFilePath = __DIR__ . "/data/questions.txt";
$answersDir = __DIR__ . "/data/answers";

if (!is_dir($answersDir)) {
    mkdir($answersDir, 0777, true);
}

if (!file_exists($filePath) || !file_exists($questionsFilePath)) {
    die("File input hoặc file câu hỏi không tồn tại.");
}

$inputContent = file_get_contents($filePath);
$questions = file($questionsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$prompt = "Dưới đây là nội dung:\n" . $inputContent . "\n\n";
$prompt .= "Trả lời các câu hỏi sau:\n";

foreach ($questions as $index => $question) {
    $prompt .= ($index + 1) . ". " . $question . "\n";
}

$url = "https://api.copilot.com/v1/answers";

$data = [
    "prompt" => $prompt,
    "max_tokens" => 1500,
    "temperature" => 0.7
];

$jsonData = json_encode($data);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

$isResponseValid = false;
$maxRetries = 5;
$retryCount = 0;
$response = null;

while (!$isResponseValid && $retryCount < $maxRetries) {
    $response = curl_exec($ch);
    $retryCount++;

    if (!$response) {
        echo "Lỗi khi gọi API, thử lại...\n";
        echo "cURL Error: " . curl_error($ch) . "\n";
        continue;
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['answers'][0]['text'])) {
        $isResponseValid = true;
    } else {
        echo "Không có dữ liệu hợp lệ từ API, thử lại...\n";
        echo "Response: " . $response . "\n";

        if (isset($responseData['error']['code']) && $responseData['error']['code'] == 'insufficient_quota') {
            echo "Bạn đã vượt quá hạn mức sử dụng API. Vui lòng kiểm tra kế hoạch và chi tiết thanh toán của bạn.\n";
            break;
        }

        if (isset($responseData['error'])) {
            echo "Error Type: " . $responseData['error']['type'] . "\n";
            echo "Error Message: " . $responseData['error']['message'] . "\n";
        }
    }
}

curl_close($ch);

if ($isResponseValid) {
    $answerText = $responseData['answers'][0]['text'];
    echo "=== Câu trả lời từ Copilot ===\n";
    echo $answerText . "\n";

    $count = 1;
    while (file_exists("$answersDir/answer$count.txt")) {
        $count++;
    }

    $answerFile = "$answersDir/answer$count.txt";
    file_put_contents($answerFile, $answerText);

    echo "✅ Câu trả lời đã được lưu vào: $answerFile\n";
} else {
    echo "Không thể lấy dữ liệu hợp lệ từ API sau $maxRetries lần thử.\n";
}
?>
