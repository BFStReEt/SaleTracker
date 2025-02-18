<?php
function chatWithGemini($message) {
    $apiKey = "AIzaSyDpWYzQHHUshzuZ28ZW-pq6K2tVYfi-aA4"; // Thay thế bằng API key của bạn
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateText?key=$apiKey";

    $data = [
        "prompt" => ["text" => $message],
        "temperature" => 0.7,
    ];

    $headers = [
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($chAIzaSyDpWYzQHHUshzuZ28ZW-pq6K2tVYfi-aA4);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['candidates'][0]['output'] ?? "Lỗi: Không nhận được phản hồi từ AI.";
}
?>
