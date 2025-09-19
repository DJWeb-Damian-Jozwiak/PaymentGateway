<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\Contracts\Arrayable;
use DjWeb\Payments\ValueObjects\Country;

final class AddressData extends BaseDto implements Arrayable
{
    public readonly Country $country;

    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $postalCode,
        string $country,
        public readonly ?string $stateProvince = null,
    ) {
        $countryObj = new Country($country);

        if ($countryObj->requiresStateProvince() && ($stateProvince === null || $stateProvince === '')) {
            throw new \InvalidArgumentException("State/Province is required for {$countryObj->code}");
        }
        $this->country = $countryObj;
    }
}
