<?php

namespace adrianciacob\Siruta\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class SirutaImportCommand extends Command
{
    protected $signature = 'siruta:import
        {--url= : Direct CSV download URL}';

    protected $description = 'Import the official INS SIRUTA dataset';

    public function handle(): int
    {
        $stream = null;

        try {
            $url = $this->option('url')
                ?: config('siruta.url');

            if (!$url) {
                $url = $this->discoverLatestDataset();
            }

            $this->info("Downloading official INS dataset from: $url");

            $response = Http::timeout(
                config('siruta.timeout', 180)
            )
                ->retry(
                    config('siruta.retries', 2),
                    1000
                )
                ->withOptions([
                    'allow_redirects' => true,
                ])
                ->get($url);

            $response->throw();

            $content = $response->body();

            if ($content === '') {
                throw new RuntimeException('Empty CSV response.');
            }

            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding(
                    $content,
                    'UTF-8',
                    'Windows-1250'
                );
            }

            $stream = fopen('php://temp', 'w+');

            fwrite($stream, $content);

            rewind($stream);

            $headers = $this->readHeaders($stream);

            $this->validateHeaders($headers);

            $table = config('siruta.table', 'siruta');

            if (!Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Table {$table} does not exist. Run php artisan migrate."
                );
            }

            $stats = $this->importRecords(
                $stream,
                $headers,
                $table
            );

            fclose($stream);

            $this->table(
                ['Type', 'Imported'],
                [
                    ['Counties', $stats['counties']],
                    ['UATs', $stats['uats']],
                    ['Bucharest sectors', $stats['sectors']],
                    ['Localities', $stats['localities']],
                    ['TOTAL', $stats['total']],
                ]
            );

            $this->info('SIRUTA import completed.');

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            report($exception);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Discover latest SIRUTA dataset from official INS CKAN catalog.
     */
    private function discoverLatestDataset(): string
    {
        $this->info('Searching official INS catalog...');

        $response = Http::timeout(
            config('siruta.timeout', 180)
        )
            ->retry(2, 1000)
            ->acceptJson()
            ->get(
                config('siruta.ckan_api_url'),
                [
                    'q' => 'siruta',
                    'rows' => 100,
                    'sort' => 'metadata_modified desc',
                ]
            );

        $response->throw();

        $payload = $response->json();

        if (
            !($payload['success'] ?? false) ||
            !isset($payload['result']['results'])
        ) {
            throw new RuntimeException(
                'Invalid CKAN API response.'
            );
        }

        $organization = strtolower(
            config('siruta.organization')
        );

        $datasets = collect(
            $payload['result']['results']
        )
            ->filter(function ($dataset) use ($organization) {
                $publisher = strtolower(
                    $dataset['organization']['name'] ?? ''
                );

                $name = strtolower(
                    $dataset['name'] ?? ''
                );

                return $publisher === $organization
                    && preg_match('/siruta.*20\d{2}/', $name);
            })
            ->sortByDesc(function ($dataset) {
                preg_match(
                    '/20\d{2}/',
                    $dataset['name'] ?? '',
                    $matches
                );

                return [
                    (int) ($matches[0] ?? 0),
                    $dataset['metadata_modified'] ?? '',
                ];
            })
            ->values();

        if ($datasets->isEmpty()) {
            throw new RuntimeException(
                'No INS SIRUTA dataset was found.'
            );
        }

        foreach ($datasets as $dataset) {
            $resource = collect(
                $dataset['resources'] ?? []
            )
                ->filter(function ($resource) {
                    $format = strtolower(
                        $resource['format'] ?? ''
                    );

                    $url = strtolower(
                        $resource['url'] ?? ''
                    );

                    return $format === 'csv'
                        || str_ends_with($url, '.csv');
                })
                ->first();

            if (!empty($resource['url'])) {
                $this->line(
                    'Selected: ' .
                    ($dataset['title'] ?? $dataset['name'])
                );

                return $resource['url'];
            }
        }

        throw new RuntimeException(
            'No CSV resource found in INS datasets.'
        );
    }

    /**
     * Read CSV headers.
     */
    private function readHeaders($stream): array
    {
        $headers = fgetcsv(
            $stream,
            null,
            ';',
            '"',
            ''
        );

        if (!$headers) {
            throw new RuntimeException('Invalid CSV headers.');
        }

        return array_map(
            fn($header) => strtoupper(
                trim(
                    preg_replace(
                        '/^\xEF\xBB\xBF/',
                        '',
                        (string) $header
                    )
                )
            ),
            $headers
        );
    }

    /**
     * Validate mandatory INS columns.
     */
    private function validateHeaders(array $headers): void
    {
        $required = [
            'SIRUTA',
            'DENLOC',
            'CODP',
            'JUD',
            'SIRSUP',
            'TIP',
            'NIV',
            'MED',
        ];

        $missing = array_diff($required, $headers);

        if ($missing) {
            throw new RuntimeException(
                'Missing CSV columns: ' .
                implode(', ', $missing)
            );
        }
    }

    /**
     * Import records inside a database transaction.
     */
    private function importRecords(
        $stream,
        array $headers,
        string $table
    ): array {
        $stats = [
            'total' => 0,
            'counties' => 0,
            'uats' => 0,
            'sectors' => 0,
            'localities' => 0,
        ];

        $batchSize = max(
            1,
            (int) config('siruta.batch_size', 500)
        );

        DB::transaction(function () use ($stream, $headers, $table, $batchSize, &$stats) {
            DB::table($table)->delete();

            $batch = [];

            while (
                ($row = fgetcsv(
                    $stream,
                    null,
                    ';',
                    '"',
                    ''
                )) !== false
            ) {
                if (count($row) !== count($headers)) {
                    throw new RuntimeException(
                        'Invalid CSV row at record ' .
                        ($stats['total'] + 1)
                    );
                }

                $data = array_combine($headers, $row);

                $record = $this->mapRecord($data);

                $batch[] = $record;

                $stats['total']++;

                match (true) {
                    $record['level'] === 1 =>
                        $stats['counties']++,

                    $record['level'] === 2 =>
                        $stats['uats']++,

                    $record['level'] === 3 &&
                    $record['type'] === 6 =>
                        $stats['sectors']++,

                    default =>
                        $stats['localities']++,
                };

                if (count($batch) >= $batchSize) {
                    DB::table($table)->insert($batch);

                    $batch = [];
                }
            }

            if ($batch) {
                DB::table($table)->insert($batch);
            }

            if ($stats['total'] === 0) {
                throw new RuntimeException(
                    'No records found in CSV.'
                );
            }

        }, 3);

        return $stats;
    }

    /**
     * Map original INS CSV columns to database columns.
     */
    private function mapRecord(array $data): array
    {
        $id = trim($data['SIRUTA']);

        $name = trim($data['DENLOC']);

        $parent = trim($data['SIRSUP']);

        if (
            $id === '' ||
            $name === '' ||
            $parent === '' ||
            !ctype_digit($id)
        ) {
            throw new RuntimeException(
                'Invalid SIRUTA record.'
            );
        }

        $level = (int) $data['NIV'];

        $type = (int) $data['TIP'];

        if (!in_array($level, [1, 2, 3], true)) {
            throw new RuntimeException(
                "Invalid hierarchy level for {$id}."
            );
        }

        return [
            'siruta' => $id,
            'name' => $name,
            'normalized_name' => $this->normalize($name),

            'postal_code' => $this->nullable($data['CODP']),

            'county_code' => trim($data['JUD']),

            'parent_id' => $parent,

            'type' => $type,
            'level' => $level,
            'environment' => (int) $data['MED'],

            'region' => $this->nullable($data['REGIUNE'] ?? null),
            'fsj' => $this->nullable($data['FSJ'] ?? null),
            'nuts' => $this->nullable($data['NUTS'] ?? null),
            'lau' => $this->nullable($data['LAU'] ?? null),

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Normalize Romanian text.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        $value = strtr($value, [
            'ă' => 'a',
            'â' => 'a',
            'î' => 'i',
            'ș' => 's',
            'ş' => 's',
            'ț' => 't',
            'ţ' => 't',
        ]);

        $value = Str::ascii($value);

        return trim(
            preg_replace('/[^a-z0-9]+/', ' ', $value)
        );
    }

    /**
     * Convert empty strings to null.
     */
    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
