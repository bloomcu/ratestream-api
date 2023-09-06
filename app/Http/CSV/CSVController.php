<?php

namespace DDD\Http\CSV;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

// Vendors
use League\Csv\Reader;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Base\Files\File;

class CSVController extends Controller
{
    public function show(Organization $organization, File $file)
    {
        // Setup CSV from file
        $csvFile = Storage::get($file->path);
        $csv = Reader::createFromString($csvFile);

        // Check for duplicate headers
        $header = $csv->fetchOne(0);
        if (collect($header)->duplicates()->count()) {
            // TODO: Create an exception class for this
            return response()->json([
                'message' => 'There is a problem with this CSV file.',
                'errors' => [
                    'uid' => ['The CSV contains duplicate columns using the same Unique ID.']
                ],
            ], 200);
        }
        
        // Setup header and rows
        // Header: Each column uid as key and column name as value
        // Rows: Each cell with column uid as key and cell content as value
        $csv->setHeaderOffset(0);
        $csv = collect($csv);
        $csvHeader = collect($csv[1]);
        $csvRows = collect($csv)->slice(1);

        // Validate header contains the top left "Unique ID" column
        if (!$csvHeader->has('Unique ID')) {
            // TODO: Create an exception class for this
            return response()->json([
                'message' => 'There is a problem with this CSV file.',
                'errors' => [
                    'uid' => ['The first value in cell A1 does not contain the term "Unique ID".']
                ],
            ], 200);
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

        // Validate all columns have a "Unique ID"
        foreach($columns as $column) {
            if (!$column['uid']) {
                // TODO: Create an exception class for this
                return response()->json([
                    'message' => 'There is a problem with this CSV file.',
                    'errors' => [
                        'uid' => ['The "Unique ID" is missing on one or more columns.']
                    ],
                ], 200);
            }
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

        // Validate all rows have a "Unique ID" column
        foreach($rows as $row) {
            if (!$row['uid']) {
                // TODO: Create an exception class for this
                return response()->json([
                    'message' => 'There is a problem with this CSV file.',
                    'errors' => [
                        'uid' => ['The "Unique ID" is missing on one or more rows.']
                    ],
                ], 200);
            }
        }

        // Check for duplicate rows
        if ($rows->duplicates('uid')->count()) {
            // TODO: Create an exception class for this
            return response()->json([
                'message' => 'There is a problem with this CSV file.',
                'errors' => [
                    'uid' => ['The CSV contains duplicate rows using the same Unique ID.']
                ],
            ], 200);
        }

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
        ], 200);
    }
}