<?php

namespace Adscom\LarapackPaymentRotations\Interfaces;

interface SupportsColumnPrioritiesInterface
{
  public static function getPriorityColumnsOrdering(): array;

  public static function getPriorityColumns(): array;
}
