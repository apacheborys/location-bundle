<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Query;

use ApacheBorys\Location\Model\Coordinates;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class ReverseQuery
{
    const DEFAULT_LIMIT = 30;

    /**
     * @var Coordinates
     */
    private $coordinate;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var string
     */
    private $locale;

    public function __construct(Coordinates $coordinate, int $limit = 0, string $locale = '')
    {
        $this->coordinate = $coordinate;
        $this->limit = $limit === 0 ? self::DEFAULT_LIMIT : $limit;
        $this->locale = $locale;
    }

    /**
     * @return Coordinates
     */
    public function getCoordinates(): Coordinates
    {
        return $this->coordinate;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
}
