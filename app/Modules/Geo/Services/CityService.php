<?php

namespace App\Modules\Geo\Services;

use App\Modules\Core\Services\BaseService;
use App\Modules\Geo\Models\Country;
use App\Modules\Geo\Repositories\CityRepository;
use Illuminate\Database\Eloquent\Model;

class CityService extends BaseService
{
    public function __construct(CityRepository $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $data): Model
    {
        return parent::create($this->resolveCountryId($data));
    }

    public function update(int|string $id, array $data): Model
    {
        return parent::update($id, $this->resolveCountryId($data));
    }

    /**
     * The API always deals in public uuids, including for `country_id` —
     * translate it to the real internal id right before it touches the
     * database.
     */
    protected function resolveCountryId(array $data): array
    {
        if (isset($data['country_id'])) {
            $data['country_id'] = Country::query()->where('uuid', $data['country_id'])->value('id');
        }

        return $data;
    }
}
