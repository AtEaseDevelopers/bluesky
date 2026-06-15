<?php

namespace App\Http\Controllers\Member;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function download(Request $request, $folder, $id, $filename)
    {
        $path = "$folder/$id/$filename";
        if (Storage::disk('local')->exists($path)) {
            $mime = Storage::disk('local')->mimeType($path);
            $file = Storage::disk('local')->get($path);
            return response($file)->header('Content-Type', $mime);
        } else {
            abort(404, 'File not found');
        }
    }

    public function downloadAndUpdateStatus(Request $request, $folder, $id, $filename)
    {
        $path = "$folder/$id/$filename";
        if (Storage::disk('local')->exists($path)) {

            if ($folder == Order::$path) {
                $order = Order::find($id);
                if ($order->status == Order::$status['processing']) {
                    $order->update(
                        [
                        'status' => Order::$status['completed'],
                        ]
                    );
                }
            }

            $mime = Storage::disk('local')->mimeType($path);
            $file = Storage::disk('local')->get($path);
            
            // Set the Content-Disposition header to force download with the specified filename
            $headers = [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
        
            return response($file, 200, $headers);
        } else {
            abort(404, 'File not found');
        }
    }
}
