<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Database;

use ApacheBorys\Location\Model\Address;
use ApacheBorys\Location\Model\AdminLevel;
use ApacheBorys\Location\Model\DBConfig;
use ApacheBorys\Location\Model\Place;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
abstract class AbstractDatabase
{
    /**
     * @var bool[]
     */
    protected $existAdminLevels;

    /**
     * @var DBConfig
     */
    protected $dbConfig;

    protected $databaseProvider;

    public function __construct($databaseProvider, DBConfig $dbConfig)
    {
        $this->databaseProvider = $databaseProvider;
        $this->dbConfig = $dbConfig;
    }

    /**
     * Compile keys for all available Address objects in Place object
     *
     * @param Place $place
     * @param bool  $useLevels
     * @param bool  $usePrefix
     * @param bool  $useAddress
     *
     * @return string[]
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function compileKeys(
        Place $place,
        bool $useLevels = true,
        bool $usePrefix = true,
        bool $useAddress = true
    ): array {
        $result = [];
        foreach ($place->getAvailableAddresses() as $locale => $address) {
            $result[$locale] = $this->compileKey($address, $useLevels, $usePrefix, $useAddress);
        }

        return $result;
    }

    /**
     * Compile key name for Place entity
     *
     * @param Address $address
     * @param bool    $useLevels
     * @param bool    $usePrefix
     * @param bool    $useAddress
     *
     * @return string
     *
     * @example 'geocoder.storage-provider.level-0-ukraine-ua.level-1-kyiv-.ua.01000.kyiv.nezalezhnosti sq.3'
     *              ^           ^                                                       - content of @see DBConfig::GLOBAL_PREFIX array
     *                                           ^                                      - max level for that Place object
     *                                              ^    ^    ^     ^              ^    - compiled Place's fields
     * @example 'geocoder.storage-provider.ua.01000.kyiv.nezalezhnosti sq.3'
     *              ^           ^                                               - content of @see DBConfig::GLOBAL_PREFIX array
     *                                     ^    ^    ^              ^     ^     - compiled Place's fields
     * @example 'ua.01000.kyiv.nezalezhnosti sq.3'
     *            ^    ^     ^              ^   ^                               - compiled Place's fields
     */
    public function compileKey(
        Address $address,
        bool $useLevels = true,
        bool $usePrefix = true,
        bool $useAddress = true
    ): string {
        return implode(
            $this->dbConfig->getGlueForSections(),
            array_merge(
                $usePrefix ? $this->dbConfig->getGlobalPrefix() : [],
                $useLevels ? $this->compileLevelsForKey($address) : [],
                $useAddress ? $this->compileAddressForKey($address) : []
            )
        );
    }

    /**
     * Levels compiler for forming identifier for Address entity in @see compileKey
     *
     * @return string[]
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function compileLevelsForKey(Address $address): array
    {
        $levels = [];

        /** @var AdminLevel $level */
        foreach ($address->getAdminLevels() as $level) {
            $levels[$level->getLevel()] = implode($this->dbConfig->getGlueForLevel(), [
                $this->dbConfig->getPrefixLevel(),
                $level->getLevel(),
                $this->normalizeStringForKeyName($level->getName()),
            ]);

            if (!isset($this->existAdminLevels[$level->getLevel()])) {
                $this->existAdminLevels[$level->getLevel()] = true;
                ksort($this->existAdminLevels);
                $this->updateExistAdminLevels();
            }
        }

        ksort($levels);

        return $levels;
    }

    /**
     * Address compiler for forming identifier for Address entity in @see compileKey
     *
     * @param Address $address
     *
     * @return string[]
     */
    protected function compileAddressForKey(Address $address): array
    {
        return [
            $this->normalizeStringForKeyName($address->getCountry()->getCode()),
            $this->normalizeStringForKeyName($address->getLocality()),
            $this->normalizeStringForKeyName($address->getSubLocality()),
            $this->normalizeStringForKeyName($address->getStreetName()),
            $this->normalizeStringForKeyName($address->getStreetNumber()),
        ];
    }

    /**
     * @param string $rawString
     *
     * @return string
     */
    public function normalizeStringForKeyName(string $rawString)
    {
        return rawurlencode(
            mb_strtolower(
                trim($rawString)
            )
        );
    }

    /**
     * @return DBConfig
     */
    public function getDbConfig(): DBConfig
    {
        return $this->dbConfig;
    }

    /**
     * @return int[]
     */
    public function getAdminLevels(): array
    {
        return array_keys($this->existAdminLevels);
    }

    /**
     * Search in each key, needed phrase @see get
     * Returning all keys what appropriate for phrase
     *
     * @param string $phrase
     * @param int    $page
     * @param int    $maxResults
     * @param string $locale
     * @param int    $filterAdminLevel
     *
     * @return string[]
     */
    protected function makeSearch(string $phrase, int $page, int $maxResults, string $locale, int $filterAdminLevel = -1): array
    {
        $result = [];

        foreach ($this->actualKeys[$locale] as $adminLevel => $actualKeys) {
            if ($filterAdminLevel > -1 && $filterAdminLevel !== $adminLevel) {
                continue;
            }

            foreach ($actualKeys as $actualKey => $objectHash) {
                $grade = $this->evaluateHitPhrase($phrase, $actualKey);
                if ($grade > 0) {
                    $result[$actualKey] = $grade;
                }
            }
        }
        arsort($result);

        if (count($result) > ($page * $maxResults)) {
            $result = array_slice($result, ($page * $maxResults), $maxResults);
        } else {
            $result = [];
        }

        return array_keys($result);
    }

    /**
     * Evaluate original regarding to phrase. Less mark value is better. @see makeSearch
     *
     * @param string $phrase
     * @param string $original
     *
     * @return int
     */
    protected function evaluateHitPhrase(string $phrase, string $original): int
    {
        $prefix = implode($this->dbConfig->getGlueForSections(), $this->dbConfig->getGlobalPrefix());

        $phrase = rawurldecode($phrase);
        if (0 === strpos($phrase, $prefix)) {
            $phrase = substr($phrase, strlen($prefix) + 1);
        }

        $original = substr($original, strlen($prefix) + 1);

        $result = 0;
        foreach ([',', ' ', '.'] as $delimiter) {
            foreach (explode($delimiter, $phrase) as $symbols) {
                if (empty($symbols)) {
                    continue;
                }
                $result += substr_count($original, $symbols);
            }
        }

        return $result;
    }

    /**
     * @param string $locale
     * @param string $key
     *
     * @return int
     */
    protected function findAdminLevelForKey(string $locale, string $key): int
    {
        foreach ($this->existAdminLevels as $existAdminLevel => $value) {
            if (isset($this->actualKeys[$locale][$existAdminLevel][$key])) {
                return $existAdminLevel;
            }
        }

        throw new \UnexpectedValueException('Can\'t find admin level for key '.$key);
    }
}
