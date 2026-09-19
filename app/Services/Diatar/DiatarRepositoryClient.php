<?php

namespace App\Services\Diatar;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DiatarRepositoryClient
{
    /**
     * @return array{revision: string, files: list<array{path: string, checksum: string, order: int}>}
     */
    public function tree(): array
    {
        $repository = (string) config('diatar.repository');
        $branch = (string) config('diatar.branch');

        $commit = $this->request()
            ->get("/repos/{$repository}/commits/{$branch}")
            ->throw()
            ->json();
        $revision = (string) data_get($commit, 'sha');

        if ($revision === '') {
            throw new \RuntimeException('The Diatár repository returned no revision.');
        }

        $tree = $this->request()
            ->get("/repos/{$repository}/git/trees/{$revision}", ['recursive' => 1])
            ->throw()
            ->json();

        if (data_get($tree, 'truncated') === true) {
            throw new \RuntimeException('The Diatár repository tree is truncated.');
        }

        $files = collect(data_get($tree, 'tree', []))
            ->filter(fn (array $entry): bool => ($entry['type'] ?? null) === 'blob')
            ->filter(fn (array $entry): bool => str_ends_with(mb_strtolower((string) ($entry['path'] ?? '')), '.dtx'))
            ->sortBy('path', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (array $entry, int $index): array => [
                'path' => (string) $entry['path'],
                'checksum' => (string) ($entry['sha'] ?? ''),
                'order' => $index + 1,
            ])
            ->all();

        return ['revision' => $revision, 'files' => $files];
    }

    public function contents(string $path, string $revision): string
    {
        $repository = (string) config('diatar.repository');
        $encodedPath = collect(explode('/', $path))->map(rawurlencode(...))->implode('/');
        $url = rtrim((string) config('diatar.raw_url'), '/')
            ."/{$repository}/{$revision}/{$encodedPath}";

        return $this->request(baseUrl: false)->get($url)->throw()->body();
    }

    private function request(bool $baseUrl = true): PendingRequest
    {
        $request = Http::acceptJson()
            ->withUserAgent('Cantores-Diatar-Catalog')
            ->connectTimeout((int) config('diatar.connect_timeout'))
            ->timeout((int) config('diatar.timeout'))
            ->retry(
                (int) config('diatar.retry_attempts'),
                (int) config('diatar.retry_delay_ms'),
            );

        if ($baseUrl) {
            $request->baseUrl(rtrim((string) config('diatar.api_url'), '/'));
        }

        $token = config('diatar.token');
        if (is_string($token) && $token !== '') {
            $request->withToken($token);
        }

        return $request;
    }
}
