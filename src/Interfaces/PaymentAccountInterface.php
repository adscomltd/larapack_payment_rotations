<?php

namespace Adscom\LarapackPaymentRotations\Interfaces;

interface PaymentAccountInterface
{
  public function getCheckoutDataAttribute(): array;
}
