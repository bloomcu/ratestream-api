<?php

namespace DDD\Http\CSV;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

// Vendors
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\SyntaxError;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Base\Files\File;

class CSVController extends Controller
{
    public function show(Organization $organization, File $file)
    {
        // Setup CSV data
        $csvFile = Storage::get($file->path);
        
        $csv = Reader::createFromString($csvFile);
        $csv->setHeaderOffset(0);
        $columns = $csv->getHeader();

        if (in_array('Unique ID', $columns)) {
            return response()->json([
                'data' => [
                    'columns' => $columns,
                    'rows' => $csv,
                ]
            ], 200);
        } else {
            return response()->json([
                'message' => 'There is a problem with this CSV file.',
                'errors' => [
                    'uid' => [
                        'The "Unique ID" column is missing in your CSV file.'
                    ]
                ],
                'columns' => $columns,
                'rows' => $csv,
            ], 200);
        }
    }
}
