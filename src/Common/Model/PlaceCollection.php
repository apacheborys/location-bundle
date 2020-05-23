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
final class PlaceCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var Place[]
     */
    private $places;

    /**
     * PlaceCollection constructor.
     *
     * @param Place[] $places
     */
    public function __construct(array $places = [])
    {
        $this->validateBeforeAdd($places);
        $this->places = $places;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->places);
    }

    /**
     * @param Place[] $addresses
     */
    private function validateBeforeAdd(array $addresses)
    {
        foreach ($addresses as $address) {
            if (!($address instanceof Place)) {
                throw new \InvalidArgumentException(sprintf('Invalid instance value type. Expected: %s, in fact: %s', Address::class, get_class($address)));
            }
        }
    }

    /**
     * @return Place[]
     */
    public function all(): array
    {
        return $this->places;
    }
}
