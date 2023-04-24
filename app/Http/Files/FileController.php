<?php

namespace DDD\Http\Files;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

// Vendors
use League\Csv\Reader;
use League\Csv\Statement;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Files\File;

class FileController extends Controller
{
    public function index(Organization $organization)
    {
        $files = $organization->files;

        // return FileResource::collection($files);
        return $files;
    }

    public function store(Organization $organization, Request $request)
    {
        $path = $request->file('file')->store('public');

        $file = $organization->files()->create([
            'path' => $path
        ]);

        // return new FileResource($file);
        return $file;
    }

    public function show(Organization $organization, File $file)
    {
        // Setup CSV data
        // $stream = fopen(Storage::path($file->path), 'r');
        // $csv = Reader::createFromStream($stream);
        // $csv->setHeaderOffset(0);
        // $csv->skipEmptyRecords(); // Do we need this?

        try {
            // Setup CSV data
            $stream = fopen(Storage::path($file->path), 'r');
            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0);
            $columns = $csv->getHeader(); // Throws a SyntaxError exception
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'There are duplicate column headers in your CSV',
                'errors' => [
                    'uid' => [
                        'There is a problem with this CSV. Please review our formatting rules.'
                    ]
                ],
            ], 200);
        }

        if (in_array('Unique ID', $columns)) {
            return response()->json([
                // 'data' => [
                    'columns' => $columns,
                    'rows' => $csv,
                // ]
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

        // return [
        //     'headers' => $csv->getHeader(),
        //     'csv' => $csv,
        // ];

        // return new FileResource($file);
    }

    public function update(Organization $organization, File $file, Request $request)
    {
        $file->update($request->all());

        // return new FileResource($file);
        return $file;
    }

    public function destroy(Organization $organization, File $file)
    {
        $file->delete();

        // return new FileResource($file);
        return $file;
    }
}
