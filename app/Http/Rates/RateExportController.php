<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;
use DDD\App\Traits\ResolvesRateGroup;

// Models
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;

class RateExportController extends Controller
{
    use ResolvesRateGroup;

    public function export(Organization $organization, Request $request)
    {
        $rateGroupId = $this->resolveRateGroupId($organization, $request->input('rate_group_id'));
        $columns = Column::where('organization_id', $organization->id)
            ->where('rate_group_id', $rateGroupId)
            ->orderBy('order')
            ->get();
        $rows = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $rateGroupId)
            ->get();

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
                    // Does row has data for this column
                    if (array_key_exists($column->uid, $row->data)) {
                        array_push($line, $row->data[$column->uid]);
                        continue;
                    }

                    array_push($line, '');
                }

                fputcsv($file, $line);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
