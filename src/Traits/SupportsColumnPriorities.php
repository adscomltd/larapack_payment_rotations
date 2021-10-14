<?php

namespace Adscom\LarapackPaymentRotations\Traits;

trait SupportsColumnPriorities
{
  /*
 * Must return array of priority columns
 * Ex. ['country_id', 'group_zone', 'affiliate_id']
 */
  abstract public static function getPriorityColumns(): array;

  /*
   * Must return ordering for priority columns
   * Ex: ['country' => 'desc']
   */
  public static function getPriorityColumnsOrdering(): array
  {
    return collect(static::getPriorityColumns())
      ->mapWithKeys(
        fn($column) => [$column => 'desc']
      )->toArray();
  }
}
