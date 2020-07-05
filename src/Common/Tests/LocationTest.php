<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Tests;

use ApacheBorys\Location\Model\Address;
use ApacheBorys\Location\Model\AdminLevel;
use ApacheBorys\Location\Model\AdminLevelCollection;
use ApacheBorys\Location\Model\Bounds;
use ApacheBorys\Location\Model\Coordinates;
use ApacheBorys\Location\Model\Country;
use ApacheBorys\Location\Database\Psr6Database;
use ApacheBorys\Location\Model\DBConfig;
use ApacheBorys\Location\Model\Place;
use ApacheBorys\Location\Model\PlaceCollection;
use ApacheBorys\Location\Model\Polygon;
use ApacheBorys\Location\Location;
use ApacheBorys\Location\Query\GeocodeQuery;
use ApacheBorys\Location\Query\ReverseQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class LocationTest extends TestCase
{
    const ELEM_LATITUDE = 'latitude';

    const ELEM_LONGITUDE = 'longitude';

    const ELEM_EXPECTED = 'expected';

    const ELEM_STREET_NUMBER = 'streetNumber';

    const ELEM_STREET_NAME = 'streetName';

    const ELEM_SUB_LOCALITY = 'subLocality';

    const ELEM_LOCALITY = 'locality';

    const ELEM_POSTAL_CODE = 'postalCode';

    private $countCoordFiles = 0;

    /** @var Location */
    private $location;

    public function setUp()
    {
        $cache = new FilesystemAdapter();
        $cache->clear();

        $dataBase = new Psr6Database($cache, new DBConfig());
        $this->location = new Location($dataBase);
        $this->loadJsonCoordinates($this->location);
    }

    /**
     * Test fetch address from nested polygons
     *
     * @dataProvider providerNestedPolygons
     *
     * @param float $lat
     * @param float $lon
     * @param array $expected
     */
    public function testNestedPolygons(float $lat, float $lon, array $expected)
    {
        $result = $this->location->reverseQuery(new ReverseQuery(new Coordinates($lon, $lat), 0, 'en'));
        /** @var Address $address */
        $place = \SplFixedArray::fromArray($result->all())->current();
        $address = $place->getSelectedAddress();

        $this->assertEquals($expected[self::ELEM_STREET_NUMBER], $address->getStreetNumber());
        $this->assertEquals($expected[self::ELEM_STREET_NUMBER], $address->getStreetNumber());
        $this->assertEquals($expected[self::ELEM_SUB_LOCALITY], $address->getSubLocality());
        $this->assertEquals($expected[self::ELEM_LOCALITY], $address->getLocality());
    }

    /**
     * @covers \ApacheBorys\Location\Location::getAllPlaces
     */
    public function testGetAllPlaces()
    {
        $totalCount = 0;
        $page = 0;
        while ($places = $this->location->getAllPlaces($page * 50)->all()) {
            foreach ($places as $place) {
                $this->assertEquals(Place::class, get_class($place));
                ++$totalCount;
            }
            ++$page;
        }
        $this->assertEquals($this->countCoordFiles, $totalCount);
    }

    /**
     * @covers \ApacheBorys\Location\Location::deletePlace
     */
    public function testDeletePlace()
    {
        $places = \SplFixedArray::fromArray($this->location->getAllPlaces()->all());
        $places->rewind();
        $this->location->deletePlace($places->current());

        $totalCount = 0;
        $page = 0;
        while ($places = $this->location->getAllPlaces($page * 50)->all()) {
            $totalCount += count($places);
            ++$page;
        }
        $this->assertEquals($this->countCoordFiles - 1, $totalCount);
    }

    /**
     * Additional geocodeQuery with specific locale
     */
    public function testGeocodeQueryWithLocale()
    {
        $query = new GeocodeQuery('Oberkassel, Düsseldorf', 0, 'de');
        $result = $this->location->geocodeQuery($query);

        // Check Dusseldorf assets in german language
        $this->checkDusseldorfAssetsInGermanLang(current($result->all()));
    }

    public function testReverseQueryWithLocale()
    {
        // Somewhere in Dusseldorf
        $result = $this->location->reverseQuery(new ReverseQuery(new Coordinates(6.761729, 51.231426), 0, 'de'));

        // Check Dusseldorf assets in german language
        $this->checkDusseldorfAssetsInGermanLang(current($result->all()));
    }

    /**
     * Measure distance between Kyevo-Pecherska Lavra and Princess Olga monument in Kyiv, Ukraine
     *
     * @covers \ApacheBorys\Location\Location::distance
     */
    public function testMeasureDistance()
    {
        $distance = $this->location->distance(
            new Coordinates(30.520620, 50.455414, 172.6),
            new Coordinates(30.557294, 50.434596, 190.8)
        );

        $this->assertEquals(3.4799019034641283, $distance);
    }

    /**
     * @covers       \ApacheBorys\Location\Location::findTouchedPlaces()
     *
     * @dataProvider providerNeighborFind
     *
     * @param array $originalPlace
     * @param array $expected
     */
    public function testNeighborFind(array $originalPlace, array $expected)
    {
        $query = new GeocodeQuery('Volodymyrska street', 1);
        $result = $this->location->geocodeQuery($query);

        $this->assertEquals(1, $result->count());
        $this->assertInstanceOf(PlaceCollection::class, $result);

        $place = current($result->all());

        $this->assertEquals($originalPlace, $place->getSelectedAddress()->getAdminLevels()->toArray());

        $result = $this->location->findTouchedPlaces($place);

        foreach ($result->all() as $pairedCoordinates) {
            $placeB = $pairedCoordinates->getPlaceB();
            $maxAdminLevel = $placeB->getMaxAdminLevel();
            $adminLevelName = $placeB->getSelectedAddress()->getAdminLevels()->get($maxAdminLevel)->getName();

            $this->assertArrayHasKey($adminLevelName, $expected);
            $this->assertEquals($expected[$adminLevelName], $pairedCoordinates->getCoordinatesB()->toArray());
        }
    }

    /**
     * @dataProvider providerFindChildPlaces
     *
     * @param array $levelsForCollection
     * @param int   $expected
     */
    public function testFindChildPlaces(array $levelsForCollection, int $expected)
    {
        $collection = new AdminLevelCollection();
        foreach ($levelsForCollection as $rawAdminLevel) {
            $collection->add(new AdminLevel($rawAdminLevel['level'], $rawAdminLevel['name']));
        }

        $result = $this->location->findChildPlaces($collection);
        $this->assertCount($expected, $result);

        /** @var Place $place */
        foreach ($result as $place) {
            $this->assertInstanceOf(Place::class, $place);
            $this->assertTrue($place->getSelectedAddress()->getAdminLevels()->isContainLevels($collection));
        }
    }

    /**
     * @see testNestedPolygons
     * @case 1 Should return first, main layer
     * @case 2 Should return third, last layer
     * @case 3 Should return second layer, with elevated precise of coordinates
     *
     * @return iterable
     */
    public function providerNestedPolygons(): \Iterator
    {
        /* Altstadt, should be return first layer of coordinates, total Dusseldorf's Place */
        yield [
            self::ELEM_LATITUDE => 51.227546,
            self::ELEM_LONGITUDE => 6.784593,
            self::ELEM_EXPECTED => [
                self::ELEM_STREET_NUMBER => '',
                self::ELEM_STREET_NAME => '',
                self::ELEM_SUB_LOCALITY => 'Dusseldorf',
                self::ELEM_LOCALITY => 'North Rhine-Westphalia',
                self::ELEM_POSTAL_CODE => '',
            ],
        ];

        /* BestenPlatz, should be return third layer what nested inside other two layers */
        yield [
            self::ELEM_LATITUDE => 51.2314767,
            self::ELEM_LONGITUDE => 6.7473107,
            self::ELEM_EXPECTED => [
                self::ELEM_STREET_NUMBER => '1',
                self::ELEM_STREET_NAME => 'Belsenplatz',
                self::ELEM_SUB_LOCALITY => 'Dusseldorf',
                self::ELEM_LOCALITY => 'North Rhine-Westphalia',
                self::ELEM_POSTAL_CODE => '40545',
            ],
        ];

        /* LuegPlatz, should be return second layer what nested inside first layer */
        /* Additionally testing with elevated precise for coordinates */
        yield [
            self::ELEM_LATITUDE => 51.2314260099,
            self::ELEM_LONGITUDE => 6.7617290099,
            self::ELEM_EXPECTED => [
                self::ELEM_STREET_NUMBER => '',
                self::ELEM_STREET_NAME => '',
                self::ELEM_SUB_LOCALITY => 'Dusseldorf',
                self::ELEM_LOCALITY => 'North Rhine-Westphalia',
                self::ELEM_POSTAL_CODE => '40545',
            ],
        ];
    }

    public function providerNeighborFind(): \Iterator
    {
        yield [
            'originalPlace' => [
                [
                    'level' => 0,
                    'name' => 'Ukraine',
                ],
                [
                    'level' => 1,
                    'name' => 'Kyiv region',
                ],
                [
                    'level' => 2,
                    'name' => 'Kyiv',
                ],
                [
                    'level' => 3,
                    'name' => 'Shevchenkivskyi district',
                ],
                [
                    'level' => 4,
                    'name' => 'Volodymyrska Street',
                ],
            ],
            'expected' => [
                'Tarasa Shevchenka Boulevard' => [
                    'lon' => 30.512750745,
                    'lat' => 50.443612127,
                    'alt' => 174.9,
                ],
                'Saksahanskoho Street' => [
                    'lon' => 30.509752037,
                    'lat' => 50.435907831,
                    'alt' => 175.5,
                ],
                'Lev Tolstoi Street' => [
                    'lon' => 30.511927319,
                    'lat' => 50.440200897,
                    'alt' => 172.65,
                ],
                'Bohdana Khmelnytskoho Street' => [
                    'lon' => 30.513405206,
                    'lat' => 50.445969332,
                    'alt' => 173.26,
                ],
            ],
        ];
    }

    /**
     * @see testFindChildPlaces
     *
     * @case 1 Empty result
     * @case 2 Not full result for existent Places for Kyiv city
     * @case 3 All Places for Kyiv city what located in database
     *
     * @return \Iterator
     */
    public function providerFindChildPlaces(): \Iterator
    {
        yield [
            'levelsForCollection' => [
                [
                    'level' => 0,
                    'name' => 'Ukraine',
                ],
                [
                    'level' => 1,
                    'name' => 'Kyiv region',
                ],
                [
                    'level' => 2,
                    'name' => 'Kyiv',
                ],
                [
                    'level' => 3,
                    'name' => 'Shevchenkivskyi district',
                ],
                [
                    'level' => 4,
                    'name' => 'Volodymyrska Street',
                ],
            ],
            'expected' => 0,
        ];

        yield [
            'levelsForCollection' => [
                [
                    'level' => 0,
                    'name' => 'Ukraine',
                ],
                [
                    'level' => 1,
                    'name' => 'Kyiv region',
                ],
                [
                    'level' => 2,
                    'name' => 'Kyiv',
                ],
                [
                    'level' => 3,
                    'name' => 'Shevchenkivskyi district',
                ]
            ],
            'expected' => 4,
        ];

        yield [
            'levelsForCollection' => [
                [
                    'level' => 0,
                    'name' => 'Ukraine',
                ],
                [
                    'level' => 1,
                    'name' => 'Kyiv region',
                ],
                [
                    'level' => 2,
                    'name' => 'Kyiv',
                ],
            ],
            'expected' => 5,
        ];
    }

    private function checkDusseldorfAssetsInGermanLang(Place $place)
    {
        $this->assertEquals(51.1243747, $place->getBounds()->getEast(), 'Latitude should be in Dusseldorf', 0.1);
        $this->assertEquals(6.9398848, $place->getBounds()->getNorth(), 'Longitude should be in Dusseldorf', 0.1);
        $address = $place->getSelectedAddress();
        $this->assertEquals('Düsseldorf', $address->getSubLocality());
        $this->assertEquals('Nordrhein-Westfalen', $address->getLocality());
        $this->assertEquals('Deutschland', $address->getCountry()->getName());
    }

    private function loadJsonCoordinates(Location $provider): bool
    {
        $success = true;
        $dirPath = __DIR__.DIRECTORY_SEPARATOR.'json-coordinates'.DIRECTORY_SEPARATOR;

        foreach (scandir($dirPath) as $file) {
            if (!is_file($dirPath.$file) || '.json' !== substr($file, -5)) {
                continue;
            }

            $rawData = json_decode(file_get_contents($dirPath.$file), true);
            if (is_array($rawData)) {
                $provider->addPlace($this->mapRawDataToPlace($rawData));
                ++$this->countCoordFiles;
            } else {
                $success = false;
            }
        }

        return $success;
    }

    private function mapRawDataToPlace(array $rawData): Place
    {
        $root = $rawData['features'][0];

        $polygons = [];
        foreach ($root['geometry']['coordinates'] as $rawPolygon) {
            $tempPolygon = new Polygon();
            foreach ($rawPolygon as $coordinates) {
                if (!isset($coordinates[0]) || !isset($coordinates[1])) {
                    continue;
                }

                $tempPolygon->addCoordinates(
                    new Coordinates(
                        $coordinates[0],
                        $coordinates[1],
                        (!isset($coordinates[2]) || is_null($coordinates[2])) ? 0 : (float) $coordinates[2]
                    )
                );
            }
            $polygons[] = $tempPolygon;
        }

        $addresses = [];
        foreach ($root['properties'] as $locale => $rawAddress) {
            if ('common' === $locale) {
                continue;
            }
            $addresses[$locale] = $this->mapRawDataToAddress($rawAddress, $locale);
        }

        return new Place(
            $addresses,
            $polygons,
            Place::DEFAULT_LOCALE,
            $root['properties']['common']['postcode'],
            null,
            $rawData['geocoding']['attribution'],
            new Bounds(
                $root['properties']['common']['bbox'][0],
                $root['properties']['common']['bbox'][1],
                $root['properties']['common']['bbox'][2],
                $root['properties']['common']['bbox'][3]
            )
        );
    }

    private function mapRawDataToAddress(array $rawData, string $locale): Address
    {
        $adminLevels = [];
        foreach ($rawData['geocoding']['admin'] as $adminLevel => $name) {
            $level = (int) substr($adminLevel, 5);
            $adminLevels[$level] = new AdminLevel($level, $name);
        }

        return new Address(
            $locale,
            new AdminLevelCollection($adminLevels),
            $rawData['geocoding']['housenumber'] ?? '',
            $rawData['geocoding']['street'] ?? '',
            $rawData['geocoding']['state'] ?? '',
            $rawData['geocoding']['city'] ?? '',
            new Country($rawData['geocoding']['country'], $rawData['geocoding']['country_code'])
        );
    }
}
