<?php

namespace adrianciacob\Siruta\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SirutaService
{
    private function table(): string
    {
        return config('siruta.table', 'siruta');
    }

    /**
     * Normalize Romanian names.
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
     * Normalize county names.
     */
    private function normalizeCounty(string $name): string
    {
        return trim(
            preg_replace(
                '/^(judetul|municipiul)\s+/',
                '',
                $this->normalize($name)
            )
        );
    }

    /**
     * Get all counties.
     */
    public function getCounties(): Collection
    {
        return DB::table($this->table())
            ->where('level', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve county name to SIRUTA ID.
     */
    public function resolveCounty(string $name): ?string
    {
        $search = $this->normalizeCounty($name);

        $matches = $this->getCounties()
            ->filter(
                fn($county) =>
                    $this->normalizeCounty($county->name) === $search
            );

        if ($matches->count() > 1) {
            throw new RuntimeException(
                "Ambiguous county name: {$name}"
            );
        }

        return $matches->first()?->siruta;
    }

    /**
     * Get record by SIRUTA ID.
     */
    public function getById(string|int $id): ?object
    {
        return DB::table($this->table())
            ->where('siruta', (string) $id)
            ->first();
    }

    /**
     * Get UATs by county.
     */
    public function getUatsByCounty(string $county): Collection
    {
        $countyId = $this->resolveCounty($county);

        if (!$countyId) {
            return collect();
        }

        $countyRecord = $this->getById($countyId);

        return DB::table($this->table())
            ->where('county_code', $countyRecord->county_code)
            ->where('level', 2)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get administrative units, including Bucharest sectors.
     */
    public function getAdministrativeUnitsByCounty(
        string $county
    ): Collection {
        $countyId = $this->resolveCounty($county);

        if (!$countyId) {
            return collect();
        }

        $countyRecord = $this->getById($countyId);

        return DB::table($this->table())
            ->where('county_code', $countyRecord->county_code)
            ->where(function ($query) {
                $query->where('level', 2)
                    ->orWhere(function ($query) {
                        $query->where('level', 3)
                            ->where('type', 6);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get localities belonging to a UAT.
     */
    public function getLocalitiesByUat(
        string|int $uatId
    ): Collection {
        return DB::table($this->table())
            ->where('parent_id', (string) $uatId)
            ->where('level', 3)
            ->where('type', '!=', 6)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all records belonging to a county.
     */
    public function getLocalitiesByCounty(
        string $county
    ): Collection {
        $countyId = $this->resolveCounty($county);

        if (!$countyId) {
            return collect();
        }

        $countyRecord = $this->getById($countyId);

        return DB::table($this->table())
            ->where('county_code', $countyRecord->county_code)
            ->where('level', '>', 1)
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find exact locality matches.
     */
    public function find(
        string $name,
        ?string $county = null,
        ?int $level = null
    ): Collection {
        $query = DB::table($this->table())
            ->where('normalized_name', $this->normalize($name));

        if ($county !== null) {
            $countyId = $this->resolveCounty($county);

            if (!$countyId) {
                return collect();
            }

            $countyRecord = $this->getById($countyId);

            $query->where(
                'county_code',
                $countyRecord->county_code
            );
        }

        if ($level !== null) {
            $query->where('level', $level);
        }

        return $query
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve locality name to SIRUTA ID.
     */
    public function resolve(
        string $name,
        ?string $county = null,
        ?int $level = null
    ): ?string {
        $matches = $this->find($name, $county, $level);

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() > 1) {
            throw new RuntimeException(
                "Multiple SIRUTA matches for '{$name}'. " .
                'Specify county or administrative level.'
            );
        }

        return $matches->first()->siruta;
    }

    /**
     * Partial locality search.
     */
    public function search(
        string $query,
        ?string $county = null,
        ?int $level = null,
        int $limit = 20
    ): Collection {
        $search = $this->normalize($query);

        if ($search === '') {
            return collect();
        }

        $builder = DB::table($this->table())
            ->where(
                'normalized_name',
                'like',
                '%' . $search . '%'
            );

        if ($county !== null) {
            $countyId = $this->resolveCounty($county);

            if (!$countyId) {
                return collect();
            }

            $countyRecord = $this->getById($countyId);

            $builder->where(
                'county_code',
                $countyRecord->county_code
            );
        }

        if ($level !== null) {
            $builder->where('level', $level);
        }

        return $builder
            ->orderByRaw(
                'CASE WHEN normalized_name = ? THEN 0 ELSE 1 END',
                [$search]
            )
            ->orderBy('name')
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    /**
     * Get parent administrative unit.
     */
    public function getParent(string|int $id): ?object
    {
        $item = $this->getById($id);

        if (!$item || (int) $item->level === 1) {
            return null;
        }

        return $this->getById($item->parent_id);
    }

    /**
     * Get complete administrative hierarchy.
     *
     * County -> UAT -> Locality
     */
    public function getHierarchy(string|int $id): array
    {
        $item = $this->getById($id);

        if (!$item) {
            return [];
        }

        $hierarchy = [$item];

        $parent = $this->getParent($id);

        while ($parent) {
            array_unshift($hierarchy, $parent);

            if ((int) $parent->level === 1) {
                break;
            }

            $parent = $this->getParent($parent->siruta);
        }

        return $hierarchy;
    }
}
