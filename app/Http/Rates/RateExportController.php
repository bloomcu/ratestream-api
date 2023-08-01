<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;

class RateExportController extends Controller
{
    public function export(Organization $organization)
    {
        $columns = $organization->columns()->orderBy('order')->get();
        $rows = $organization->rates;

        // Setup CSV file
        $fileName = $organization->slug . '-rates.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        // Setup first row of column uids
        $columnUids = array('Unique ID');
        foreach ($columns as $column) {
            array_push($columnUids, $column->uid);
        }

        // Setup first row of column uids
        $columnNames = array('');
        foreach ($columns as $column) {
            array_push($columnNames, $column->name);
        }

        // Generate CSV
        $callback = function() use($columnUids, $columnNames, $columns, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columnUids);
            fputcsv($file, $columnNames);

            // Output rows and their corresponding columns
            foreach ($rows as $row) {
                $line = array();

                // Add row uid
                array_push($line, $row->uid);

                // Add row data per current column
                foreach ($columns as $column) {
                    array_push($line, $row->data[$column->uid]);
                }

                fputcsv($file, $line);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
