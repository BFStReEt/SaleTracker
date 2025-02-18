<?php
$filePath = __DIR__ . "/data/input.txt";
$questionsFilePath = __DIR__ . "/data/questions.txt";

// Đọc nội dung của input.txt
if (!file_exists($filePath) || !file_exists($questionsFilePath)) {
    die("Một trong hai tệp không tồn tại.");
}

$inputContent = file_get_contents($filePath);
$questions = file($questionsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$answers = [];

foreach ($questions as $question) {
    $pattern = '/' . preg_quote($question, '/') . '.*?([\.\?\!])/i';
    if (preg_match($pattern, $inputContent, $matches)) {
        $answers[$question] = trim($matches[0]);
    } else {
        $answers[$question] = "Không tìm thấy câu trả lời.";
    }
}

// Xuất kết quả
foreach ($answers as $q => $a) {
    echo "Câu hỏi: $q\n";
    echo "Trả lời: $a\n\n";
}
?>