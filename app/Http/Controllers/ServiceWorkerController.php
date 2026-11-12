<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Serves the service worker, resources/js/sw.js, after two constants: VERSION, a short hash of the Vite manifest, the
 * worker and the offline page's view, and PRECACHE, the offline page and every built file. It is a route rather than a
 * file in public/ because the version has to change with every build, and the Docker image only takes public/build
 * from the asset build.
 */
class ServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $manifest = File::get(public_path('build/manifest.json'));
        $worker = File::get(resource_path('js/sw.js'));
        $version = substr(hash('sha256', $manifest.$worker.File::get(resource_path('views/offline.blade.php'))), 0, 12);

        /** @var array<string, array{file: string, css?: list<string>, assets?: list<string>}> $chunks */
        $chunks = json_decode($manifest, true);

        $precache = collect($chunks)
            ->flatMap(fn (array $chunk) => [$chunk['file'], ...($chunk['css'] ?? []), ...($chunk['assets'] ?? [])])
            ->map(fn (string $path) => '/build/'.$path)
            ->prepend('/offline')
            ->unique()
            ->values();

        return response(
            'const VERSION = '.json_encode($version).";\n"
                .'const PRECACHE = '.$precache->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).";\n\n"
                .$worker,
            headers: ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'no-cache'],
        );
    }
}
