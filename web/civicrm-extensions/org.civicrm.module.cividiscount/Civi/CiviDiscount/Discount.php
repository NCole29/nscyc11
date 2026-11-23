<?php

namespace Civi\CiviDiscount;

class Discount {

  private array $options = [];

  private string $currency;

  private array $discount;

  public function __construct(array $discount, $currency) {
    $this->discount = $discount;
    $this->currency = $currency;
  }

  public function addPriceOption($option): void {
    $option = new PriceOption($option, $this->currency);
    $option->addDiscount($this->discount);
    $this->options[] = $option->applyDiscounts();
  }

  public function getTotal() {
    $total = 0;
    foreach ($this->options as $option) {
      if (!empty($option['discount_applied'])) {
        $total += $option['discount_applied'];
      }
    }
    return $total;
  }

  public function getOptions() {
    return $this->options;
  }

  public function getCode(): string {
    return $this->discount['code'];
  }

}
