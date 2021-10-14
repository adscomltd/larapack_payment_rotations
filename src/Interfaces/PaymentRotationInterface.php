<?php

namespace Adscom\LarapackPaymentRotations\Interfaces;

interface PaymentRotationInterface
{
  public static function getPaymentAccountColumnName(): string;

  public function getPriorityAttribute(): int;
}
