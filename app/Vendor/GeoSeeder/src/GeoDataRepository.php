<?php

namespace Local\GeoSeeder;

use InvalidArgumentException;

/**
 * Read-only access to the country/city reference data shipped in
 * src/Data/{CODE}.php. Pure data — no Eloquent, no database — so it stays
 * trivial to test and to extend with more countries later.
 */
class GeoDataRepository
{
    protected string $dataPath;

    public function __construct()
    {
        $this->dataPath = __DIR__.'/Data';
    }

    /**
     * ISO2 codes the package ships data for, e.g. ['AE', 'EG', 'KW', 'SA'].
     *
     * @return array<int, string>
     */
    public function supported(): array
    {
        return collect(glob("{$this->dataPath}/*.php"))
            ->map(fn (string $file) => pathinfo($file, PATHINFO_FILENAME))
            ->sort()
            ->values()
            ->all();
    }

    public function has(string $code): bool
    {
        return in_array(strtoupper($code), $this->supported(), true);
    }

    /**
     * @return array{name: string, name_ar: string, iso2: string, iso3: string, phone_code: string, currency: string, currency_symbol: string, flag_emoji: string, timezone: string}
     */
    public function country(string $code): array
    {
        return $this->load($code)['country'];
    }

    /**
     * @return array<int, array{name: string, name_ar: string, latitude: ?float, longitude: ?float}>
     */
    public function cities(string $code): array
    {
        return $this->load($code)['cities'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function load(string $code): array
    {
        $code = strtoupper($code);
        $file = "{$this->dataPath}/{$code}.php";

        if (! is_file($file)) {
            throw new InvalidArgumentException(
                "No geo data for country [{$code}]. Supported: ".implode(', ', $this->supported())
            );
        }

        return require $file;
    }
}
