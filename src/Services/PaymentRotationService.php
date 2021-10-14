<?php

namespace Adscom\LarapackPaymentRotations\Services;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

abstract class PaymentRotationService
{
  protected array $columns = [];
  protected bool $isRotationsSet = false;
  private EloquentCollection $paymentRotations;

  abstract public function __construct();

  public function setCountry(Country $country): void
  {
    $this->setColumn('country_id', $country->id);
    $this->setColumn('group_zone', $country->group_zones ? $country->group_zones[0] : null);
  }

  public function getColumn(string $key): mixed
  {
    return $this->columns[$key] ?? null;
  }

  public function setColumn(string $key, $value): void
  {
    $this->isRotationsSet = false;
    $this->columns[$key] = $value;
  }

  public static function orderBy(Builder $builder, string $column, string $direction = 'desc'): Builder
  {
    if (config('database.default') === 'mysql') {
      $builder->orderBy($column, $direction);
    } else {
      $builder->orderByRaw("{$column} {$direction} NULLS LAST");
    }

    return $builder;
  }

  public function getCheckoutData(
    array $options = []
  ): array {
    $paymentAccounts = $this->getPaymentAccountForEachGateway($options);

    $paymentAccounts = $paymentAccounts
      ->mapWithKeys(
        fn($item, $key) => [$key => $item->checkout_data]
      );

    $gateways = $paymentAccounts->toArray();

    return compact('gateways');
  }

  protected function prioritizeRotations(EloquentCollection $ratios
  ): EloquentCollection {
    return $ratios
      ->sortByDesc('priority')
      ->groupBy('priority');
  }

  protected function getRotationsWithHighestPriority(EloquentCollection $ratios
  ): EloquentCollection {
    return $ratios->first();
  }

  abstract public function getPaymentRotationModelClass(): string;

  abstract public function getPaymentAccountModelClass(): string;

  protected function excludePaymentRotationsById(Builder $builder, array $options): Builder
  {
    $paymentRotationClass = $this->getPaymentRotationModelClass();

    $builder->when($options,
      fn($q) => $q->whereNotIn($paymentRotationClass::getPaymentAccountColumnName(), $options)
    );

    return $builder;
  }

  abstract protected function getPaymentRotationDefaultQuery(Builder $builder, array $options = []): Builder;

  protected function getMatchedPaymentRotationsBuilder(Builder $builder, array $options): Builder
  {
    $modelClass = $this->getPaymentRotationModelClass();

    foreach ($modelClass::getPriorityColumns() as $column) {
      $builder->where(fn($q) => $q
        ->when($this->getColumn($column), fn($q) => $q->where($column, $this->getColumn($column)))
        ->orWhere(fn($q) => $q->whereNull($column)));
    }

    return $builder;
  }

  protected function getOrderedPaymentRotationsBuilder(Builder $builder, array $options): Builder
  {
    $modelClass = $this->getPaymentRotationModelClass();

    foreach ($modelClass::getPriorityColumnsOrdering() as $column => $direction) {
      static::orderBy($builder, $column, $direction);
    }

    return $builder;
  }

  public function getPaymentRotationsBuilder(
    array $options = [],
  ): Builder {
    $paymentRotationClass = $this->getPaymentRotationModelClass();

    $builder = $paymentRotationClass::query();
    $builder = $this->getPaymentRotationDefaultQuery($builder, $options);
    $builder = $this->excludePaymentRotationsById($builder, $options);
    $builder = $this->getMatchedPaymentRotationsBuilder($builder, $options);
    $builder = $this->getOrderedPaymentRotationsBuilder($builder, $options);

    return $builder;
  }

  /**
   * @throws \Exception
   */
  protected function chooseRandomPaymentAccount($paymentAccounts)
  {
    $totalRotationCount = $paymentAccounts->reduce(fn($carry, $item) => $carry + $item->ratio, 0);
    $randomIndex = random_int(0, $totalRotationCount);
    $carry = 0;

    foreach ($paymentAccounts as $paymentAccount) {
      $carry += $paymentAccount->ratio;

      if ($carry >= $randomIndex) {
        return $paymentAccount;
      }
    }
  }

  public function getPaymentAccountForEachGateway(
    array $options = [],
  ): EloquentCollection {

    $paymentAccounts = $this->getPaymentRotations($options);

    $paymentAccounts = $this->prioritizeRotations($paymentAccounts);
    $paymentAccounts = $this->getRotationsWithHighestPriority($paymentAccounts);

    $paymentAccounts = $this->groupPaymentAccounts($paymentAccounts);

    return $paymentAccounts->mapWithKeys(
      fn($item, $key) => [$key => $this->chooseRandomPaymentAccount($item)->paymentAccount]
    );
  }

  abstract protected function groupPaymentAccounts(EloquentCollection $paymentAccounts): EloquentCollection;

  protected function setupPaymentRotations(array $options): void
  {
    $this->paymentRotations = $this->getPaymentRotationsBuilder($options)->get();
  }

  public function getPaymentRotations(array $options = []): EloquentCollection
  {
    if ($this->isRotationsSet) {
      return $this->paymentRotations;
    }

    $this->setupPaymentRotations($options);

    $this->isRotationsSet = true;

    return $this->paymentRotations;
  }
}
