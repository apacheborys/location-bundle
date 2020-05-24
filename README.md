# Storage Location Bundle

[![Build Status](https://travis-ci.com/apacheborys/location-bundle.svg?branch=master)](https://img.shields.io/travis/apacheborys/location-bundle.svg?style=flat-square)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

With that bundle, you will able to build own server of geo data. Manipulation functions:

* [add, delete and update places](#working-with-database)
* [find place what include specific coordinates point](#find-place-what-include-specific-coordinates-point)
* [find place by text phrase](#find-place-by-text)

### Benefits

* save requests to real provider of geo data (if you will use it as cache)
* own-driven performance control
* opportunity to build own locations, places
* possible to use high precise value for coordinates (storing as float type)
* turnkey solutions for working with places

### Install

```bash
composer require apacheborys/location-bundle
```

### Usage

First of all you need setup storage, where you will save data about locations. Available database providers [here](#database-providers). For example, you can use FilesystemAdapter.

```php
$database = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();
```

After, you need setup database configuration. If you don't want do it, you can use default configuration by creating class without any arguments. Take attention to `useCompression` flag, it can help to save size of storage. If you will use compression, please be sure what your database able to save binary data.

```php
$dbConfig = new \ApacheBorys\Location\Model\DBConfig();
```

After that you can use Location bundle:

```php
$locationBundle = new \ApacheBorys\Location\Location($database, $dbConfig);
```

Please take attention what you need to take care for save data in database what you use. Also, in first moment you need to add places what you want, to find that future. Each place in database is specific Place entity, what contain collection of Address entities and properties - `Polygons`, `Bounds`, `timezone`, `providedBy`, `currentLocale` and `objectHash`. Bottom you can see example how you can save information. It just example, don't use it in production please.

```php
$headers = [
    'Accept-language' => 'en'
];

/* query for Kiev city, Ukraine */
$query = [
    'format' => 'geocodejson',
    'osm_ids' => 'R421866',
    'polygon_geojson' => 1,
    'addressdetails' => 1,
];

$request = new \http\Client\Request('GET', 'https://nominatim.openstreetmap.org/lookup?' . http_build_query($query), $headers);
/** @var \Psr\Http\Message\ResponseInterface $response */
$response = new \Http\Client\HttpClient($request);
$rawGeoCodeJson = json_decode((string) $response->getBody());

$query['format'] = 'geojson';
unset($query['polygon_geojson'], $query['addressdetails']);

$request = new \http\Client\Request('GET', 'https://nominatim.openstreetmap.org/lookup?' . http_build_query($query), $headers);
$response = new \Http\Client\HttpClient($request);
$rawGeoJson = json_decode((string) $response->getBody());

$rawGeoCodeJson['features'][0]['properties']['common']['bbox'] = $rawGeoJson['features'][0]['bbox'];
$rawGeoCodeJson['features'][0]['properties']['common']['postcode'] = $rawGeoJson['features'][0]['properties']['address']['postcode'];
$rawGeoCodeJson['features'][0]['properties']['geocoding']['country_code'] = $rawGeoJson['features'][0]['properties']['address']['country_code'];

$locationBundle->addPlace(mapRawDataToPlace($rawGeoCodeJson));

private function mapRawDataToPlace(array $rawData): \ApacheBorys\Location\Model\Place
{
    $root = $rawData['features'][0];

    $polygons = [];
    foreach ($root['geometry']['coordinates'] as $rawPolygon) {
        $tempPolygon = new \ApacheBorys\Location\Model\Polygon();
        foreach ($rawPolygon as $coordinates) {
            $tempPolygon->addCoordinates(new \ApacheBorys\Location\Model\Coordinates($coordinates[0], $coordinates[1]));
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

    return new \ApacheBorys\Location\Model\Place(
        $addresses,
        $polygons,
        \ApacheBorys\Location\Model\Place::DEFAULT_LOCALE,
        $root['properties']['common']['postcode'],
        null,
        $rawData['geocoding']['attribution'],
        new \ApacheBorys\Location\Model\Bounds(
            $root['properties']['common']['bbox'][0],
            $root['properties']['common']['bbox'][1],
            $root['properties']['common']['bbox'][2],
            $root['properties']['common']['bbox'][3]
        )
    );
}

private function mapRawDataToAddress(array $rawData, string $locale): \ApacheBorys\Location\Model\Address
{
    $adminLevels = [];
    foreach ($rawData['geocoding']['admin'] as $adminLevel => $name) {
        $level = (int) substr($adminLevel, 5);
        $adminLevels[$level] = new \ApacheBorys\Location\Model\AdminLevel($level, $name);
    }

    return new \ApacheBorys\Location\Model\Address(
        $locale,
        new \ApacheBorys\Location\Model\AdminLevelCollection($adminLevels),
        $rawData['geocoding']['housenumber'] ?? '',
        $rawData['geocoding']['street'] ?? '',
        $rawData['geocoding']['state'] ?? '',
        $rawData['geocoding']['city'] ?? '',
        new \ApacheBorys\Location\Model\Country($rawData['geocoding']['country'], $rawData['geocoding']['country_code'])
    );
}
```

After add place above, you will receive that place in `reverseQuery` for any coordinate what consisting in Place's polygons. If you will add place with highest admin level - you will receive that new place. That provider every time try to respond places with highest admin level (for `reverseQuery` method).

### Find place what include specific coordinates point

```php
$address = $locationBundle->reverseQuery(
    new \ApacheBorys\Location\Query\ReverseQuery(new \ApacheBorys\Location\Model\Coordinates(50.4422519, 30.5423135))
);
```

### Find place by text

For `geocodeQuery` use any text what you want to find.

```php
$address = $locationBundle->geocodeQuery(new \ApacheBorys\Location\Query\GeocodeQuery('Kyiv, Ukraine'));
```

### Working with Database

That bundle has methods for realize database functionality:
* `addPlace` - add Place object, return boolean
* `deletePlace` - delete Place object, return boolean
* `getAllPlaces` - get all existent places in database, return array of `\ApacheBorys\Location\Model\Place`. Please take attention for pagination.

Take attention what each Place object identified in database according to `objectHash` property. Please use that property as read-only. If you will change that property, database provider will lose relation to that Place in database.

Take attention what each Address object identified in database according:
1. Admin level - admin level name
2. Locality, subLocality, streetName, streetNumber

If you want to change Place entity, you should delete that Place and add new Place with an already changed object. Also, please take attention what each object in database have time to life value (for PSR-6). By default, it's 365 days (1 year), you can setup it through passing specific argument in creation `\ApacheBorys\Location\Model\DBConfig`.

### Database providers

You can choose what database provider you want to use. Now available 2 providers:
* `\ApacheBorys\Location\Database\PdoDatabase`
* `\ApacheBorys\Location\Database\Psr6Database`

Also, please take attention to `\ApacheBorys\Location\Model\DBConfig`. You can find a lot config values what make possibility to fine tune.

If you want to save storage space you can enable compressing data here `\ApacheBorys\Location\Model\DBConfig::$useCompression`. Please take attention what you can adjust compression level here `\ApacheBorys\Location\Model\DBConfig::$compressionLevel` (1-9 values, default - 5). Please take attention, what compression performing through commands [gzuncompress](https://www.php.net/manual/en/function.gzuncompress.php) and [gzcompress](https://www.php.net/manual/en/function.gzcompress.php). Please take care for migration data from/to compress state by self.

If you don't want use data in databases, you should take care for deletion that by self.

#### PdoDatabase

You can use that provider if you are plan to store your places in sqlite, mysql or postgresql. For constructor, you need to pass `\PDO` object as first argument. Name of tables what will create on first provider call - `\ApacheBorys\Location\Database\PdoDatabase\HelperInterface::queryForCreateTables`.

#### Psr6Database

That provider is more simple and store data about place entity like a pile in cache. Please take care for data's TTL, because usually each cache (PSR-6) provider have standard TTL value. Also, you can adjust it by specify TTL value for `\ApacheBorys\Location\Model\DBConfig::$ttlForRecord`, default value is `\ApacheBorys\Location\Model\DBConfig::TTL_FOR_RECORD`.

For constructor, you need to pass any object what implement `\Psr\Cache\CacheItemPoolInterface` as first argument.

### Testing

Please run `composer test`.

### Warning

Please take attention what each geo data have owner and you should use it only in legitimate goals.
