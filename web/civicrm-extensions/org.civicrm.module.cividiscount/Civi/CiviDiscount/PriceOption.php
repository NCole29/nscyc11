<?php

namespace Civi\CiviDiscount;

class PriceOption {

  private array $option;

  private array $discounts = [];

  private string $currency;

  public function __construct(array $option, string $currency) {
    $this->option = $option;
    $this->currency = $currency;
  }

  public function addDiscount(array $discount): void {
    $this->discounts[] = $discount;
  }

  public function applyDiscounts(): array {
    $option = $this->option;
    // Make sure we calculate the discount from the original amount, not a previously discounted one.
    $originalLabel = $option['original_label'] = $option['original_label'] ?? $this->option['label'];
    $originalAmount = $option['original_amount'] = (float) ($option['original_amount'] ?? $this->option['amount']);
    foreach ($this->discounts as $discount) {
      [$amount, $label]
        = \CRM_CiviDiscount_DiscountCalculator::calculateDiscountAmount($discount, $originalAmount, $originalLabel, $this->currency);
      $discountAmount = $originalAmount - $amount;
      if (!empty($option['discount_applied']) && $option['discount_applied'] >= $discountAmount) {
        continue;
      }
      if ($discountAmount > ($option['discount_applied'] ?? 0)) {
        $option['amount'] = $amount;
        $option['label'] = $label;
        $option['discount_applied'] = $discountAmount;
        $option['discount_code'] = $discount['code'];
        $option['discount_description'] = $discount['description'];

        if (array_key_exists('tax_amount', $option)) {
          // Re-calculate VAT/Sales TAX on discounted amount.
          $recalculateTaxAmount = \CRM_Contribute_BAO_Contribution_Utils::calculateTaxAmount($amount, $this->option['tax_rate']);
          if (!empty($recalculateTaxAmount)) {
            $option['tax_amount'] = round($recalculateTaxAmount['tax_amount'], 2);
          }
        }
      }
    }
    return $option;
  }

}
