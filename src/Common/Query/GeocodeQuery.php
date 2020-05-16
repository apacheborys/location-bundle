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

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class GeocodeQuery
{
    const DEFAULT_LIMIT = 30;

    /**
     * @var string
     */
    private $text;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var string
     */
    private $locale;

    public function __construct(string $text, int $limit = 0, string $locale = '')
    {
        $this->text = $text;
        $this->limit = $limit === 0 ? self::DEFAULT_LIMIT : $limit;
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
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
