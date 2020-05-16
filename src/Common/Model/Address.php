<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Model;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class Address
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @var AdminLevelCollection
     */
    private $adminLevels;

    /**
     * @var string|int|null
     */
    private $streetNumber;

    /**
     * @var string|null
     */
    private $streetName;

    /**
     * @var string|null
     */
    private $subLocality;

    /**
     * @var string|null
     */
    private $locality;

    /**
     * @var Country|null
     */
    private $country;

    public function __construct(
        string $locale,
        AdminLevelCollection $adminLevels,
        string $streetNumber = null,
        string $streetName = null,
        string $locality = null,
        string $subLocality = null,
        Country $country = null
    ) {
        $this->locale = $locale;
        $this->adminLevels = $adminLevels;
        $this->streetNumber = $streetNumber;
        $this->streetName = $streetName;
        $this->locality = $locality;
        $this->subLocality = $subLocality;
        $this->country = $country;
    }
}
