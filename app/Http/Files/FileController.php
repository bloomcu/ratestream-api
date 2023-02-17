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
        $stream = fopen(Storage::path($file->path), 'r');
        $csv = Reader::createFromStream($stream);
        $csv->setHeaderOffset(0);

        // return new FileResource($file);
        return [
            'headers' => $csv->getHeader(),
            'csv' => $csv,
        ];
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
