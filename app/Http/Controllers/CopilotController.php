<?php

namespace App\Http\Controllers;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CopilotController extends Controller {
    public function askCopilot( Request $request ) {
        $question = $request->input( 'question' );

        $response = Http::withHeaders( [
            'Authorization' => 'Bearer ' . config( 'services.copilot.api_key' ),
            'Content-Type'  => 'application/json',
        ] )->post( config( 'services.copilot.base_url' ) . '/chat', [
            'message' => $question,
        ] );

        return response()->json( $response->json() );
    }
}
