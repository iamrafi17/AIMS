<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

abstract class Controller
{
    /**
     * Return the concrete adapter because controller downloads and public URLs
     * are Laravel adapter features, not part of the generic filesystem contract.
     */
    protected function publicDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk;
    }

    /**
     * Normalize paths written by older Windows-hosted versions of the app.
     */
    protected function publicStoragePath(?string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

        return $path !== '' ? $path : null;
    }
}
