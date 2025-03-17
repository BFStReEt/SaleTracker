<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

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

                return response()->json(['message' => 'Success']);
            }

            return response()->json(['message' => 'No file uploaded'], 400);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function uploads(Request $request)
    {
        $folderPath = "C:\\Users\\longh\\OneDrive - chinhnhan.vn\\Folder Import";
        
        try {
            if (!File::exists($folderPath)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Directory not found: ' . $folderPath
                ], 404);
            }

            
            $files = File::files($folderPath);
            $importedFiles = 0;
            $errors = [];

            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['xlsx', 'xls'])) {
                    try {
                        
                        Excel::import(new SalesImport, $file->getPathname());
                        
                       
                        File::delete($file->getPathname());
                        
                        $importedFiles++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'file' => $file->getFilename(),
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => $importedFiles . ' files imported successfully',
                'imported_count' => $importedFiles,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing files: ' . $e->getMessage()
            ], 500);
        }
    }

    public function upload_black_list(Request $request)
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
                $file->move(public_path('blacklist'), $fileName);

                return response()->json(['message' => 'Success']);
            }

            return response()->json([
                'status' => 'true',
                'message' => 'No file uploaded'], 400);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}