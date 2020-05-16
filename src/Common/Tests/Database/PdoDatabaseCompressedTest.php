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

use ApacheBorys\Location\Database\PdoDatabase;
use ApacheBorys\Location\Model\DBConfig;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class PdoDatabaseCompressedTest extends StorageLocationProviderIntegrationDbTest
{
    use PdoDatabaseTrait;

    private $dbFileName;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        $this->dbFileName = $this->generateTempDbFile();

        $dbConfig = new DBConfig();
        $dbConfig->setUseCompression(true);
        $dbConfig->setCompressionLevel(1);

        parent::__construct($name, $data, $dataName);
        $this->dataBase = new PdoDatabase($this->generatePdo($this->dbFileName), $dbConfig);
    }

    public function __destruct()
    {
        if (is_file($this->dbFileName)) {
            unlink($this->dbFileName);
        }
    }
}
