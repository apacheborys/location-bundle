<?php


declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Traits;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
trait ValidateCoordinatesTrait
{
    public static function assertLongitude(float $longitude): bool
    {
        return is_int(preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $longitude));
    }

    public static function assertLatitude(float $latitude): bool
    {
        return is_int(preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/', $latitude));
    }
}
