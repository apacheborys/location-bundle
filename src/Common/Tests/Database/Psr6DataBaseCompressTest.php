<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Tests\Database;

use Cache\Adapter\PHPArray\ArrayCachePool;
use ApacheBorys\Location\Database\Psr6Database;
use ApacheBorys\Location\Model\DBConfig;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class Psr6DataBaseCompressTest extends StorageLocationProviderIntegrationDbTest
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $dbConfig = new DBConfig();
        $dbConfig->setUseCompression(true);
        $dbConfig->setCompressionLevel(1);

        $this->dataBase = new Psr6Database(new ArrayCachePool(), $dbConfig);
    }
}
