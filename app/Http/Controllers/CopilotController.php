<?php

namespace App\Http\Controllers;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CopilotController extends Controller
{
    public function index(Request $request)
    {
        // Lấy prompt từ request đầu vào
        $prompt = $request->input('prompt');

        // Gửi request đến API Copilot
        $response = Http::withHeaders([
            'X-API-KEY' => env('COPILOT_API_KEY'),
            'Content-Type' => 'application/json'
        ])->post(env('COPILOT_API_URL'), [
            'prompt' => $prompt,
            
        ]);

        // Trả về dữ liệu phản hồi
        return response()->json($response->json());
    }
}
