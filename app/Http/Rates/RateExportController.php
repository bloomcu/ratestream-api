<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
// use DDD\Domain\Rates\Rate;

// // Requests
// use DDD\Http\Rates\Requests\RateStoreRequest;
// use DDD\Http\Rates\Requests\RateUpdateRequest;

// // Resources
// use DDD\Http\Rates\Resources\RateResource;
// use DDD\Http\Columns\Resources\ColumnResource;

class RateExportController extends Controller
{
    public function export(Organization $organization)
    {
        $columns = $organization->columns;
        $rows = $organization->rates;

        // Setup CSV file
        $fileName = $organization->slug . '-pages.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        // // Setup columns
        // $columns = array(
        //     'Title',
        //     'Parents',
        //     'Url',
        //     'Category',
        //     'Wordcount',
        // );

        // Generate CSV
        $callback = function() use($columns, $rows) {
            $file = fopen('php://output', 'w');

            foreach ($columns as $column) {
                $row['UID'] = str_replace(chr(194), '', $column->uid);
                $row['Name'] = str_replace(chr(194), '', $column->name);
                // $row['Url'] = str_replace(chr(194), '', $page->url);
                // $row['Category'] = str_replace(chr(194), '', $page->category ? $page->category->title : 'Uncategorized');
                // $row['Wordcount'] = str_replace(chr(194), '', $page->wordcount);

                fputcsv($file, array(
                    $row['UID'],
                    $row['Name'],
                    // $row['Url'],
                    // $row['Category'],
                    // $row['Wordcount'],
                ));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
