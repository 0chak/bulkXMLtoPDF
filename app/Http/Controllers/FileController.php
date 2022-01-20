<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use XslZ\XslZ;

use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Zip;


class FileController extends Controller
{

    public function processFiles(Request $r){

        $r->validate([
            'file' => 'required|mimes:zip|max:204800'
        ]);

        // dd( storage_path('app/public'));

        // Storage::deleteDirectory( storage_path('app/temp') );
        // Storage::deleteDirectory( storage_path('app/public') );

        if($r->file()) {
            $origname = $r->file->getClientOriginalName();

            $fileName = rand(0, 10000) . $origname;
            $filePath = $r->file('file')->storeAS('uploads', $fileName);

            $foldername = substr($fileName, 0, -4);

            $zip = Zip::open(storage_path('app/' . $filePath));
            $zip->extract(storage_path('app/temp/' . $foldername));

            $files = Storage::disk('local')->allFiles('/temp/' . $foldername);

            foreach ($files as $file) {
                if(strpos($file, '__MACOSX') !== false){
                    continue;
                }
                if(strpos($file, '.xml')){
                    $this->convertXML($file);
                }
            }

            $zip2 = Zip::create(storage_path('app/public/' . $fileName)); // create zip
            $zip2->add(storage_path('app/temp/' . $foldername), true);
            $zip2->close();

            // $is_valid = Zip::check(storage_path('app/public/' . $fileName));

            // dd($zip2);
            // sleep(10);

            return response()->download(storage_path('app/public/' . $fileName), $origname);
        }

        $xslX = new XslZ;

        $converted = $xslX->transform(
            resource_path('AssoStyle.xsl'),
            resource_path('test.xml')
        );

        // echo $converted;
        // exit;

        $pdf = \App::make('dompdf.wrapper')->setPaper('a4');
        $pdf->loadHTML($converted);
        return $pdf->stream();

    }


    private function convertXML($file){

        try {
            $xslX = new XslZ;

            $converted = $xslX->transform(
                resource_path('AssoStyle.xsl'),
                storage_path('app/' . $file)
            );

            $pdf = \App::make('dompdf.wrapper')->setPaper('a4');
            $pdf->loadHTML($converted);

            // dd(storage_path('app/' . 'converted' . substr($file, 4, -3). 'pdf'));
            $pdf->setPaper('a4', 'portrait');
            // $pdf->render();
            Storage::put( substr($file, 0, -3) . 'pdf', $pdf->output());
            Storage::delete( $file );

        } catch (\Throwable $th) {
            dump('Error converting ' . $file );
            throw($th);
        }

    }
}
