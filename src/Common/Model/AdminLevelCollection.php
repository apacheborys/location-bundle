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
final class AdminLevelCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var AdminLevel[]
     */
    private $adminLevels;

    /**
     * AdminLevelCollection constructor.
     *
     * @param AdminLevel[] $adminLevels
     */
    public function __construct(array $adminLevels = [])
    {
        $this->adminLevels = [];

        foreach ($adminLevels as $adminLevel) {
            $this->validateBeforeAdd($adminLevel);
            $this->adminLevels[$adminLevel->getLevel()] = $adminLevel;
        }

        if (!ksort($this->adminLevels, SORT_NUMERIC)) {
            throw new \InvalidArgumentException('Error during sort admin levels');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * @param int $level
     *
     * @return bool
     */
    public function has(int $level): bool
    {
        return isset($this->adminLevels[$level]);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->adminLevels);
    }

    /**
     * @param AdminLevel $adminLevel
     */
    private function validateBeforeAdd(AdminLevel $adminLevel)
    {
        if ($adminLevel->getLevel() < 0) {
            throw new \OutOfBoundsException(sprintf(
                'Admin level %s have a wrong level %s',
                $adminLevel->getName(),
                $adminLevel->getLevel()
            ));
        }

        if ($this->has($adminLevel->getLevel())) {
            throw new \InvalidArgumentException(sprintf(
                'Admin collection already have %s level. Collection shouldn\'t have two AdminLevel with same levels',
                $adminLevel->getLevel()
            ));
        }
    }

    /**
     * @return AdminLevel[]
     */
    public function all(): array
    {
        return $this->adminLevels;
    }
}
