<?php

namespace Modules\Inventory\Services;

use App\Helpers\Utils;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Dashboard\Models\City;
use Modules\Dashboard\Services\CityService;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Repositories\SupplierRepository;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;
use Modules\User\Services\UserService;

/**
 * @extends BaseService<Supplier, SupplierRepository>
 */
class SupplierService extends BaseService
{
    public function __construct(
        SupplierRepository $repository
    ) {
        parent::__construct($repository);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>|Collection<int, Supplier>
     */
    public function getSuppliers(array $filters = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $this->repository->filtered($filters);

        if (! in_array($filters['sort_by'] ?? null, ['name', 'city', 'phone', 'is_active'], true)) {
            $this->repository->orderBy(
                User::query()
                    ->select('last_name')
                    ->whereColumn('users.id', 'suppliers.user_id')
            )->orderBy(
                User::query()
                    ->select('first_name')
                    ->whereColumn('users.id', 'suppliers.user_id')
            )->orderBy('suppliers.id');
        }

        $this->repository->withRelations();

        return $paginate ? $this->repository->paginate() : $this->repository->all();
    }

    public function create(array $data): Supplier
    {
        $data = $this->interpretData($data);
        $userService = resolve(UserService::class);
        $user = $userService->create($data);
        $data['user_id'] = $user->id;
        $data['slug'] ??= Utils::generateUniqueSlug('supplier '.$user->fullName(), Supplier::class);

        return parent::create($data);
    }

    /**
     * A supplier is a person (a user) plus supplier details, and the edit form
     * carries both: save them together or not at all.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int|Model $model, array $data): bool
    {
        if ($model instanceof Supplier === false) {
            $model = $this->findOrFail($model);
        }

        return DB::transaction(function () use ($model, $data): bool {
            resolve(UserService::class)->update($model->user, $this->interpretData($data));

            return parent::update($model, $data);
        });
    }

    protected function interpretData(array $data): array
    {
        $cityService = resolve(CityService::class);
        $data['type'] = UserType::Supplier->value;
        if (! empty($data['city'])) {
            /** @var City $city */
            $city = $cityService->findByKey($data['city']);
            $data['city_id'] = $city->id;
        }

        return parent::interpretData($data);
    }
}
