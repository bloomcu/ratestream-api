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
        // Setup CSV
        $csvFile = Storage::get($file->path);
        $csv = Reader::createFromString($csvFile);
        $csv->setHeaderOffset(0);
        
        // Separate header from rows
        $csv = collect($csv);
        $csvHeader = collect($csv[1]);
        $csvRows = collect($csv)->slice(1);
        
        // Validate header contains the "Unique ID" columns
        if (!$csvHeader->has('Unique ID')) {
            // TODO: Create an exception class for this
            return response()->json([
                'message' => 'There is a problem with this CSV file.',
                'errors' => [
                    'uid' => [
                        'The "Unique ID" column is missing in your CSV file.'
                    ]
                ],
            ], 200);
        }

        // Validate all rows have a "Unique ID" column
        foreach($csvRows as $row) {
            if (!$row['Unique ID']) {
                // TODO: Create an exception class for this
                return response()->json([
                    'message' => 'There is a problem with this CSV file.',
                    'errors' => [
                        'uid' => [
                            'The "Unique ID" is missing on one or more rows.'
                        ]
                    ],
                ], 200);
            }
        }

        // Remove "Unique ID" header
        $csvHeader->forget('Unique ID');

        // Collect columns
        $columns = collect();
        foreach($csvHeader as $key => $value) {
            $columns->push([
                'uid' => $key,
                'name' => $value
            ]);
        }

        // Collect rows
        $rows = collect();
        foreach($csvRows as $row) {
            $uid = $row['Unique ID'];
            unset($row['Unique ID']);

            $rows->push([
                'uid' => $uid,
                'data' => $row
            ]);
        }

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
        ], 200);
    }
}