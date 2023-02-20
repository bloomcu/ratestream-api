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
        $stream = fopen(Storage::path($file->path), 'r');
        $csv = Reader::createFromStream($stream);
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        if (in_array('Unique ID', $headers)) {
            return response()->json([
                // 'data' => [
                //     'headers' => $csv->getHeader(),
                //     'csv' => $csv,
                // ]
                'headers' => $csv->getHeader(),
                'csv' => $csv,
            ], 200);
        } else {
            return response()->json([
                'message' => 'There is a problem with this CSV file.',
                'errors' => [
                    'uid' => [
                        'The "Unique UD" column is missing in your CSV file.'
                    ]
                ],
                'headers' => $csv->getHeader(),
                'csv' => $csv,
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
