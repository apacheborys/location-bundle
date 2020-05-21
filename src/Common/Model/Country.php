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
class Country implements Arrayable
{
    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $code;

    /**
     * @param string $name
     * @param string $code
     */
    public function __construct(string $name = null, string $code = null)
    {
        if (null === $name && null === $code) {
            throw new \InvalidArgumentException('A country must have either a name or a code');
        }

        $this->name = $name;
        $this->code = $code;
    }

    /**
     * @return string|null
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getCode(): string
    {
        return $this->code;
    }


    public function toArray(): array
    {
        return [
            'name' => (string) $this->name,
            'code' => (string) $this->code,
        ];
    }

    public static function fromArray(array $raw): Arrayable
    {
        foreach (['name', 'code'] as $property) {
            if (!isset($raw[$property])) {
                throw new \InvalidArgumentException(sprintf('Key %s not found in input array', $property));
            }
        }

        return new self((string)$raw['name'], (string)$raw['code']);
    }
}
