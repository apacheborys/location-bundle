# Storage Location Bundle

### Benefits

* save requests to real provider
* own-driven performance control
* opportunity to build own locations, places
* possible to use high precise value for coordinates (storing as float type)

### Install

```bash
composer require apacheborys/location-bundle-common
```

### Usage

First of all you need to setup storage where you will save data about locations. Currently available all database providers what match PSR-6. For example, you can use FilesystemAdapter or ArrayCachePool.

```php
$database = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();
```

After, you need to setup database configuration. If you don't want do it, you can use default configuration by creating class without any arguments. Take attention to `useCompression` flag, it can help to save size of storage. If you will use compression, please be sure what your PSR cache provider able to save binary data.

```php
$dbConfig = new \ApacheBorys\Location\Model\DBConfig();
```

After that you can use Storage Location provider:

```php
$provider = new \ApacheBorys\Location\Location($database, $dbConfig);
```

Please take attention what you need to take care for save data in database what you use. Also in first moment you need to add places what you want to find in feature. Each place in database it's specific Place entity what contain collection of Address entities and additional properties - `Polygons`, `currentLocale` and `objectHash`.

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
$rawGeoCodeJson = json_decode((string)$response->getBody());

$query['format'] = 'geojson';
unset($query['polygon_geojson'], $query['addressdetails']);

$request = new \http\Client\Request('GET', 'https://nominatim.openstreetmap.org/lookup?' . http_build_query($query), $headers);
$response = new \Http\Client\HttpClient($request);
$rawGeoJson = json_decode((string)$response->getBody());

$rawGeoCodeJson['features'][0]['bbox'] = $rawGeoJson['features'][0]['bbox'];
$rawGeoCodeJson['properties']['geocoding']['country_code'] = $rawGeoJson['features'][0]['properties']['address']['country_code'];

$provider->addPlace(mapRawDataToPlace($rawGeoCodeJson));

function mapRawDataToPlace(array $rawData): \ApacheBorys\Location\Model\Place
    {
        $root = $rawData['features'][0];

        $adminLevels = [];
        foreach ($root['properties']['geocoding']['admin'] as $adminLevel => $name) {
            $level = (int)substr($adminLevel, 5);
            if ($level > 5) {
                $level = 5;
            } elseif ($level < 1) {
                $level = 1;
            }

            $adminLevels[$level] = new \Geocoder\Model\AdminLevel($level, $name);
        }

        $polygons = [];
        foreach ($root['geometry']['coordinates'] as $rawPolygon) {
            $tempPolygon = new \Geocoder\Provider\StorageLocation\Model\Polygon();
            foreach ($rawPolygon as $coordinates) {
                $tempPolygon->addCoordinates(new \Geocoder\Model\Coordinates($coordinates[1], $coordinates[0]));
            }
            $polygons[] = $tempPolygon;
        }

        return new \ApacheBorys\Location\Model\Place(
            ['en' => new \Geocoder\Model\Address(
                $rawData['geocoding']['attribution'],
                new \ApacheBorys\Location\Model\AdminLevelCollection($adminLevels),
                new \ApacheBorys\Location\Model\Coordinates($root['coordinates'][1], $root['coordinates'][0]),
                new \ApacheBorys\Location\Model\Bounds($root['bbox'][0], $root['bbox'][1], $root['bbox'][2], $root['bbox'][3]),
                $root['properties']['geocoding']['housenumber'] ?? '',
                $root['properties']['geocoding']['street'] ?? '',
                $root['properties']['geocoding']['postcode'] ?? '',
                $root['properties']['geocoding']['state'] ?? '',
                $root['properties']['geocoding']['city'] ?? '',
                new \ApacheBorys\Location\Model\Country($root['properties']['geocoding']['country'], $root['properties']['geocoding']['country_code']),
                null
            )],
            $polygons
        );
    }
```

After add place above you will receive that place in `reverseQuery` for any coordinate what consisting in Place's polygons. If you will add place with highest admin level - you will receive that new place. That provider every time try to respond places with highest admin level (for `reverseQuery` method).

```php
$address = $provider->reverseQuery(
    new \ApacheBorys\Location\Query\ReverseQuery(new \ApacheBorys\Location\Model\Coordinates(50.4422519, 30.5423135))
);
```

For `geocodeQuery` use it in usual way.

```php
$address = $provider->geocodeQuery(new \Geocoder\Query\GeocodeQuery('Kyiv, Ukraine'));
```

### Working with Database

That bundle have methods for realize database functionality:
* `addPlace` - add Place object, return boolean
* `deletePlace` - delete Place object, return boolean
* `getAllPlaces` - get all existent places in db, return array of `\ApacheBorys\Location\Model\Place`

Take attention what each Place object identified in database according to `objectHash` property. Please use that property as read-only. If you will change that property, database provider will lose relation to that Place in database.

Take attention what each Address object identified in database according:
1. Admin level - admin level name
2. Country code, postal code, locality, subLocality, streetName, streetNumber

If you want to change Place entity you should delete that Place and add new Place with already changed object. Also please take attention what each object in database have time to life value. By default it's 365 days (1 year), you can setup it through passing specific argument in creation `\ApacheBorys\Location\Model\DBConfig`.

### Database providers

You can choose what database provider you want to use. Now available 2 providers:
* `\ApacheBorys\Location\Database\PdoDatabase`
* `\ApacheBorys\Location\Database\Psr6Database`

Also please take attention to `\ApacheBorys\Location\Model\DBConfig`. You can find a lot config values what make possibility to fine tune.

If you want to save storage space you can enable compressing data here `\ApacheBorys\Location\Model\DBConfig::$useCompression`. Please take attention what you can adjust compression level here `\ApacheBorys\Location\Model\DBConfig::$compressionLevel` (1-9 values, default - 5). Please take attention, what compression performing through commands [gzuncompress](https://www.php.net/manual/en/function.gzuncompress.php) and [gzcompress](https://www.php.net/manual/en/function.gzcompress.php). Please take care for migration data from/to compress state by self.

If you don't want use data in databases, you should take care for deletion that by self.

#### PdoDatabase

You can use that provider if you have plan to store your places in sqlite, mysql or postgresql. For constructor, you need to pass `\PDO` object as first argument. Name of tables what will create on first provider call - `\ApacheBorys\Location\Database\PdoDatabase\HelperInterface::queryForCreateTables`.

#### Psr6Database

That provider is more simply and store data about place entity like a pile in cache. Please take care for data's TTL, because usually each cache (PSR-6) provider have standart TTL value. Also, you can adjust it by specify TTL value for `\ApacheBorys\Location\Model\DBConfig::$ttlForRecord`, default value is `\ApacheBorys\Location\Model\DBConfig::TTL_FOR_RECORD`.

For constructor, you need to pass any object what implement `\Psr\Cache\CacheItemPoolInterface` as first argument.

### Testing

Please run `composer test`.