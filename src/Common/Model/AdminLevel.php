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
class AdminLevel implements Arrayable
{
    /**
     * @var int
     */
    private $level;

    /**
     * @var string
     */
    private $name;

    public function __construct(int $level, string $name)
    {
        $this->level = $level;
        $this->name = $name;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'name' => $this->name,
        ];
    }

    public static function fromArray(array $raw): Arrayable
    {
        foreach (['level', 'name'] as $property) {
            if (!isset($raw[$property])) {
                throw new \InvalidArgumentException(sprintf('Key %s not found in input array', $property));
            }
        }

        return new self((int)$raw['level'], (string)$raw['name']);
    }
}
