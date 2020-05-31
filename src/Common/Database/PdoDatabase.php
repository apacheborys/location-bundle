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
use ApacheBorys\Location\Model\AdminLevelCollection;
use ApacheBorys\Location\Model\Bounds;
use ApacheBorys\Location\Model\Coordinates;
use ApacheBorys\Location\Model\Country;
use ApacheBorys\Location\Database\PdoDatabase\Constants;
use ApacheBorys\Location\Database\PdoDatabase\HelperInterface;
use ApacheBorys\Location\Database\PdoDatabase\HelperLocator;
use ApacheBorys\Location\Model\DBConfig;
use ApacheBorys\Location\Model\Place;
use ApacheBorys\Location\Model\Polygon;
use Psr\Log\InvalidArgumentException;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class PdoDatabase extends AbstractDatabase implements DataBaseInterface
{
    /**
     * @var DBConfig
     */
    protected $dbConfig;

    /**
     * @var \PDO
     */
    protected $databaseProvider;

    /**
     * By that keys we will store hashes (references) to fetch real object
     * first key - locale
     * second key - admin level
     * third key - compiled key from Address of current locale
     * value - hash of object
     *
     * @var string[][][]
     */
    protected $actualKeys = [];

    /**
     * By that keys we will store real Place objects
     *
     * @var bool[]
     */
    protected $objectsHashes = [];

    /**
     * @var HelperInterface
     */
    private $helper;

    public function __construct($databaseProvider, DBConfig $dbConfig)
    {
        if (!($databaseProvider instanceof \PDO)) {
            throw new InvalidArgumentException('Cache provider should be instance of '.\PDO::class);
        }

        parent::__construct($databaseProvider, $dbConfig);

        $locator = new HelperLocator($databaseProvider, $dbConfig);
        $this->helper = $locator->getHelper();

        $this->checkExistTables();

        $this->getExistHashKeys();
        $this->getActualKeys();
        $this->getExistAdminLevels();
    }

    public function add(Place $place): bool
    {
        $place->setObjectHash('');
        $place->setObjectHash(spl_object_hash($place));

        return $this->insertPlace($place);
    }

    public function update(Place $place): bool
    {
        $this->delete($place);
        $this->insertPlace($place);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $searchKey, int $page = 0, int $maxResults = 30, string $locale = '', int $filterAdminLevel = -1): array
    {
        if ($maxResults > $this->dbConfig->getMaxPlacesInOneResponse()) {
            $maxResults = $this->dbConfig->getMaxPlacesInOneResponse();
        }

        if ('' === $locale) {
            $locale = $this->dbConfig->getDefaultLocale();
        }

        $result = [];

        foreach ($this->makeSearch($searchKey, $page, $maxResults, $locale, $filterAdminLevel) as $key) {
            $adminLevel = $this->findAdminLevelForKey($locale, $key);

            $result[] = $this->getPlace($this->actualKeys[$locale][$adminLevel][$key]);
        }

        return $result;
    }

    public function delete(Place $place): bool
    {
        if ($this->deletePlace($place->getObjectHash())) {
            foreach ($this->actualKeys as $locale => $localeKeys) {
                $search = array_search($place->getObjectHash(), $localeKeys[$place->getMaxAdminLevel()]);
                if (is_string($search)) {
                    unset($this->actualKeys[$locale][$place->getMaxAdminLevel()][$search]);
                }
            }
            unset($this->objectsHashes[$place->getObjectHash()]);

            return true;
        }

        return false;
    }

    public function getAllPlaces(int $offset = 0, int $limit = 50): array
    {
        $result = [];

        $stmt = $this->databaseProvider->prepare($this->helper->queryGetAllPlaces());
        $stmt->bindValue(':offset', $offset);
        $stmt->bindValue(':limit', $limit);
        $stmt->execute();

        $rawPlaces = $stmt->fetchAll();
        foreach ($rawPlaces as $rawPlace) {
            $result[] = $this->getPlace($rawPlace[Constants::OBJECT_HASH]);
        }

        return $result;
    }

    public function updateExistAdminLevels(): bool
    {
        return true;
    }

    private function insertPlace(Place $place): bool
    {
        $stmtPlace = $this->databaseProvider->prepare($this->helper->queryInsertPlace());
        foreach (Constants::FIELDS_FOR_PLACE as $field => $ref) {
            if (Constants::COMPRESSED_DATA === $field) {
                continue;
            }
            $stmtPlace->bindValue(':'.$field, $this->getValueForDb($place, $ref, Place::DEFAULT_LOCALE));
        }

        if ($this->dbConfig->isUseCompression()) {
            $stmtPlace->bindValue(
                ':compressed_data',
                gzcompress(
                    json_encode($place->toArray()),
                    $this->dbConfig->getCompressionLevel()
                )
            );
        } else {
            $stmtPlace->bindValue(':compressed_data', null);

            list($stmtAddress, $stmtAdminLevel) = $this->prepareAddressesForInsert($place);
            $stmtPolygon = $this->preparePolygonsForInsert($place);
        }

        $stmtSearchKeyForAddress = $this->prepareSearchKeysForInsert($place);

        $this->databaseProvider->beginTransaction();

        try {
            $stmtPlace->execute();

            if (!$this->dbConfig->isUseCompression()) {
                foreach ($stmtAddress as $address) {
                    $address->execute();
                }

                foreach ($stmtAdminLevel as $collection) {
                    foreach ($collection as $stmt) {
                        $stmt->execute();
                    }
                }

                foreach ($stmtPolygon as $collection) {
                    foreach ($collection as $coordinate) {
                        $coordinate->execute();
                    }
                }
            }

            foreach ($stmtSearchKeyForAddress as $stmtSearchKey) {
                $stmtSearchKey->execute();
            }

            $this->databaseProvider->commit();
        } catch (\Exception $e) {
            $this->databaseProvider->rollBack();

            throw new $e();
        }

        return true;
    }

    /**
     * @param Place $place
     *
     * @return \PDOStatement[]
     */
    private function preparePolygonsForInsert(Place $place): array
    {
        $stmt = [];

        foreach ($place->getPolygons() as $polygonNumber => $polygon) {
            foreach ($polygon->getCoordinates() as $coordNumber => $coordinate) {
                $tempStmt = $this->databaseProvider->prepare($this->helper->queryInsertPolygon());

                $tempStmt->bindValue(':'.Constants::OBJECT_HASH, $place->getObjectHash());
                $tempStmt->bindValue(':'.Constants::POLYGON_NUMBER, $polygonNumber);
                $tempStmt->bindValue(':'.Constants::POINT_NUMBER, $coordNumber);
                $tempStmt->bindValue(':'.Constants::LATITUDE, $coordinate->getLatitude());
                $tempStmt->bindValue(':'.Constants::LONGITUDE, $coordinate->getLongitude());
                $tempStmt->bindValue(':'.Constants::ALTITUDE, $coordinate->getAltitude());

                $stmt[$polygonNumber][$coordNumber] = $tempStmt;
            }
        }

        return $stmt;
    }

    /**
     * @param Place $place
     *
     * @return \PDOStatement[][]
     */
    private function prepareAddressesForInsert(Place $place): array
    {
        $stmtAddress = $stmtAdminLevel = [];

        foreach ($place->getAvailableAddresses() as $locale => $address) {
            $stmtAddress[$locale] = $this->databaseProvider->prepare($this->helper->queryInsertAddress());

            foreach (Constants::FIELDS_FOR_ADDRESS as $field => $ref) {
                if (Constants::LOCALE === $field) {
                    $stmtAddress[$locale]->bindValue(':'.Constants::LOCALE, $locale);
                } else {
                    $stmtAddress[$locale]->bindValue(':'.$field, $this->getValueForDb($place, $ref, $locale));
                }
            }

            $stmtAdminLevel[$locale] = $this->prepareAdminLevelsForInsert($place, $address, $locale);
        }

        return [$stmtAddress, $stmtAdminLevel];
    }

    private function prepareSearchKeysForInsert(Place $place): array
    {
        $stmtSearchKeyForAddress = [];

        foreach ($place->getAvailableAddresses() as $locale => $address) {
            $stmtSearchKeyForAddress[$locale] = $this->prepareSearchKeyForInsert($place, $address, $locale);

            $this->actualKeys[$locale][$place->getMaxAdminLevel()][$this->compileKey($address, true, false)] = $place->getObjectHash();
        }

        return $stmtSearchKeyForAddress;
    }

    /**
     * @param Place   $place
     * @param Address $address
     * @param string  $locale
     *
     * @return \PDOStatement[]
     */
    private function prepareAdminLevelsForInsert(Place $place, Address $address, string $locale): array
    {
        $stmtAdminLevel = [];

        foreach ($address->getAdminLevels()->all() as $key => $adminLevel) {
            $stmtAdminLevel[$adminLevel->getLevel()] = $this->databaseProvider->prepare(
                $this->helper->queryInsertAdminLevel()
            );

            foreach (Constants::FIELDS_FOR_ADMIN_LEVEL as $field => $ref) {
                if (Constants::LOCALE === $field) {
                    $stmtAdminLevel[$adminLevel->getLevel()]->bindValue(':'.Constants::LOCALE, $locale);
                } else {
                    $stmtAdminLevel[$adminLevel->getLevel()]->bindValue(
                        ':'.$field,
                        $this->getValueForDb($place, $ref, $locale, $adminLevel->getLevel())
                    );
                }
            }
        }

        return $stmtAdminLevel;
    }

    /**
     * @param Place   $place
     * @param Address $address
     * @param string  $locale
     *
     * @return \PDOStatement
     */
    private function prepareSearchKeyForInsert(Place $place, Address $address, string $locale): \PDOStatement
    {
        $tempStmt = $this->databaseProvider->prepare($this->helper->queryInsertSearchKey());
        $tempStmt->bindValue(':'.Constants::OBJECT_HASH, $place->getObjectHash());
        $tempStmt->bindValue(':'.Constants::LOCALE, $locale);
        $tempStmt->bindValue(':'.Constants::LEVEL, $place->getMaxAdminLevel());
        $tempStmt->bindValue(':'.Constants::SEARCH_TEXT, $this->compileKey($address, true, false));

        return $tempStmt;
    }

    private function getValueForDb(Place $place, string $field, string $locale, ...$indexes)
    {
        $result = null;

        list($class, $propertyName) = explode('::', $field);

        switch ($class) {
            case Place::class:
                $result = $this->getObjectValueThroughReflection($place, $propertyName);

                break;
            case Address::class:
                $oldLocale = $place->getSelectedLocale();
                $place->selectLocale($locale);

                $result = $this->getObjectValueThroughReflection($place->getSelectedAddress(), $propertyName);

                $place->selectLocale($oldLocale);

                break;
            case Bounds::class:
                $result = $this->getObjectValueThroughReflection(
                    $place->getBounds(),
                    $propertyName
                );

                break;
            case Country::class:
                $oldLocale = $place->getSelectedLocale();
                $place->selectLocale($locale);

                $result = $this->getObjectValueThroughReflection(
                    $place->getSelectedAddress()->getCountry(),
                    $propertyName
                );

                $place->selectLocale($oldLocale);

                break;
            case AdminLevel::class:
                $oldLocale = $place->getSelectedLocale();
                $place->selectLocale($locale);

                $result = $this->getObjectValueThroughReflection(
                    $place->getSelectedAddress()->getAdminLevels()->get($indexes[0]),
                    $propertyName
                );

                $place->selectLocale($oldLocale);

                break;
            default:
                $result = null;

                break;
        }

        return $result;
    }

    private function getObjectValueThroughReflection($object, string $propertyName)
    {
        $reflection = new \ReflectionObject($object);
        $p = $reflection->getProperty($propertyName);
        $p->setAccessible(true);

        return $p->getValue($object);
    }

    private function getPlace(string $objectHash): Place
    {
        $tempStmt = $this->databaseProvider->prepare($this->helper->querySelectSpecificPlace());
        $tempStmt->bindValue(':'.Constants::OBJECT_HASH, $objectHash);
        $tempStmt->execute();
        $placeFromDb = $tempStmt->fetch(\PDO::FETCH_ASSOC);

        if ($this->dbConfig->isUseCompression()) {
            if (is_array($placeFromDb) && isset($placeFromDb[Constants::COMPRESSED_DATA])) {
                $resultPlace = Place::createFromArray(
                    json_decode(gzuncompress($placeFromDb[Constants::COMPRESSED_DATA]), true)
                );
            } else {
                throw new \Exception('Try to load non existent Place with hash '.$objectHash);
            }
        } else {
            $resultPlace = new Place(
                $this->fetchAddressesForPlace($objectHash),
                $this->fetchPolygonsForPlace($objectHash),
                $placeFromDb[Constants::LOCALE],
                $placeFromDb[Constants::POSTAL_CODE],
                $placeFromDb[Constants::TIMEZONE],
                $placeFromDb[Constants::PROVIDED_BY],
                new Bounds(
                    (float) $placeFromDb[Constants::BOUNDS_WEST],
                    (float) $placeFromDb[Constants::BOUNDS_EAST],
                    (float) $placeFromDb[Constants::BOUNDS_NORTH],
                    (float) $placeFromDb[Constants::BOUNDS_SOUTH]
                )
            );
            $resultPlace->setObjectHash($objectHash);
        }

        return $resultPlace;
    }

    /**
     * @param string $objectHash
     *
     * @return Address[]
     */
    private function fetchAddressesForPlace(string $objectHash): array
    {
        $resultAddresses = [];

        $stmtAddress = $this->databaseProvider->prepare($this->helper->querySelectAddresses());
        $stmtAddress->bindValue(':'.Constants::OBJECT_HASH, $objectHash);
        $stmtAddress->execute();

        foreach ($stmtAddress->fetchAll() as $rawAddress) {
            $resultAddresses[$rawAddress[Constants::LOCALE]] = new Address(
                $rawAddress[Constants::LOCALE],
                new AdminLevelCollection(
                    $this->fetchAdminLevelsForAddress(
                        $objectHash,
                        $rawAddress[Constants::LOCALE]
                    )
                ),
                $rawAddress[Constants::STREET_NUMBER],
                $rawAddress[Constants::STREET_NAME],
                $rawAddress[Constants::LOCALITY],
                $rawAddress[Constants::SUB_LOCALITY],
                new Country($rawAddress[Constants::COUNTRY_NAME], $rawAddress[Constants::COUNTRY_CODE])
            );
        }

        return $resultAddresses;
    }

    /**
     * @param string $objectHash
     *
     * @return Polygon[]
     */
    private function fetchPolygonsForPlace(string $objectHash): array
    {
        $polygonPoints = [];
        $page = 0;
        do {
            $stmtPolygon = $this->databaseProvider->prepare($this->helper->querySelectPolygonPoints());
            $stmtPolygon->bindValue(':'.Constants::OBJECT_HASH, $objectHash);
            $stmtPolygon->bindValue(':offset', ($page * 1000));
            $stmtPolygon->execute();

            $rawPolygons = $stmtPolygon->fetchAll();

            foreach ($rawPolygons as $rawPolygon) {
                $pln = (int) $rawPolygon[Constants::POLYGON_NUMBER];
                $pnn = (int) $rawPolygon[Constants::POINT_NUMBER];
                $polygonPoints[$pln][$pnn] = new Coordinates(
                    (float) $rawPolygon[Constants::LONGITUDE],
                    (float) $rawPolygon[Constants::LATITUDE],
                    (float) $rawPolygon[Constants::ALTITUDE]
                );
            }

            ++$page;
        } while (count($rawPolygons) > 0);

        $polygons = [];
        foreach ($polygonPoints as $polygonNumber => $polygonPointCollection) {
            $polygons[$polygonNumber] = new Polygon($polygonPointCollection);
        }

        return $polygons;
    }

    /**
     * @param string $objectHash
     * @param string $locale
     *
     * @return AdminLevel[]
     */
    private function fetchAdminLevelsForAddress(string $objectHash, string $locale): array
    {
        $stmtAdminLevel = $this->databaseProvider->prepare($this->helper->querySelectAdminLevel());
        $stmtAdminLevel->bindValue(':'.Constants::OBJECT_HASH, $objectHash);
        $stmtAdminLevel->bindValue(':'.Constants::LOCALE, $locale);
        $stmtAdminLevel->execute();

        $levels = [];
        foreach ($stmtAdminLevel->fetchAll() as $rawLevel) {
            $levels[] = new AdminLevel(
                (int) $rawLevel[Constants::LEVEL],
                $rawLevel[Constants::NAME]
            );
        }

        return $levels;
    }

    private function deletePlace(string $objectHash): bool
    {
        $stmtPlace = $this->databaseProvider->prepare($this->helper->queryDelete('place'));
        $stmtPlace->bindValue(':'.Constants::OBJECT_HASH, $objectHash);

        $stmtAddress = $this->databaseProvider->prepare($this->helper->queryDelete('address'));
        $stmtAddress->bindValue(':'.Constants::OBJECT_HASH, $objectHash);

        $stmtActualKeys = $this->databaseProvider->prepare($this->helper->queryDelete('actual_keys'));
        $stmtActualKeys->bindValue(':'.Constants::OBJECT_HASH, $objectHash);

        $stmtAdminLevel = $this->databaseProvider->prepare($this->helper->queryDelete('admin_level'));
        $stmtAdminLevel->bindValue(':'.Constants::OBJECT_HASH, $objectHash);

        $stmtPolygon = $this->databaseProvider->prepare($this->helper->queryDelete('polygon'));
        $stmtPolygon->bindValue(':'.Constants::OBJECT_HASH, $objectHash);

        $this->databaseProvider->beginTransaction();

        try {
            $stmtAddress->execute();
            $stmtActualKeys->execute();
            $stmtAdminLevel->execute();
            $stmtPolygon->execute();
            $stmtPlace->execute();

            $this->databaseProvider->commit();
        } catch (\Exception $e) {
            $this->databaseProvider->rollBack();

            throw new $e();
        }

        return true;
    }

    private function getExistHashKeys(): bool
    {
        $page = 0;
        do {
            $stmt = $this->databaseProvider->prepare($this->helper->queryGetAllPlaces());
            $stmt->bindValue(':offset', ($page * 1000));
            $stmt->bindValue(':limit', 1000);
            $stmt->execute();

            $rawPlaces = $stmt->fetchAll();
            foreach ($rawPlaces as $rawPlace) {
                $this->objectsHashes[$rawPlace['object_hash']] = true;
            }

            ++$page;
        } while (count($rawPlaces) > 0);

        return true;
    }

    private function getActualKeys(): bool
    {
        $page = 0;
        do {
            $stmt = $this->databaseProvider->prepare($this->helper->queryGetAllActualKeys());
            $stmt->bindValue(':offset', ($page * 1000));
            $stmt->bindValue(':limit', 1000);
            $stmt->execute();

            $rawActKeys = $stmt->fetchAll();
            foreach ($rawActKeys as $rawActKey) {
                $this->actualKeys[$rawActKey[Constants::LOCALE]][$rawActKey[Constants::LEVEL]][$rawActKey[Constants::SEARCH_TEXT]] = $rawActKey[Constants::OBJECT_HASH];
            }

            ++$page;
        } while (count($rawActKeys) > 0);

        return true;
    }

    private function getExistAdminLevels(): bool
    {
        $stmt = $this->databaseProvider->prepare($this->helper->queryGetAllAdminLevels());
        $stmt->execute();

        foreach ($stmt->fetchAll() as $rawLevel) {
            $this->existAdminLevels[$rawLevel[Constants::LEVEL]] = true;
        }

        return true;
    }

    private function checkExistTables()
    {
        foreach ($this->helper->queryForCreateTables() as $query) {
            $this->databaseProvider->query($query);
        }
    }
}
