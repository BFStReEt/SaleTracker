<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
//
class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls', 
            ], [
                'file.required' => 'Select a file to upload.',
                'file.mimes' => 'The file must be a file of type: xlsx, xls.',
            ]);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = $file->getClientOriginalName();
                $file->move(public_path('data'), $fileName);

                $nows = now()->timestamp;
                $now = date('d-m-Y, g:i:s A', $nows);
                DB::table('adminlogs')->insert([
                'admin_id' => Auth::guard('admin')->user()->id,
                'time' => $now,
                'ip' => $request->ip() ?? null,
                'action' => 'upload file',
                'cat' => 'admin',
                ]);

                return response()->json(['message' => 'Success']);
            }

            return response()->json(['message' => 'No file uploaded'], 400);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}