<?php

namespace Findologic\Plentymarkets\Data;

class Units
{
    /**
     * Unit id => Unit text value in plentymarkets systems
     *
     * @var array
     */
    protected static $units = array (
        1 => 'C62',
        2 => 'KGM',
        3 => 'GRM',
        4 => 'MGM',
        5 => 'LTR',
        6 => 'DPC',
        7 => 'OP',
        8 => 'BL',
        9 => 'DI',
        10 => 'BG',
        11 => 'ST',
        12 => 'D64',
        13 => 'PD',
        14 => 'QR',
        15 => 'BX',
        16 => 'CL',
        17 => 'CH',
        18 => 'TN',
        19 => 'CA',
        20 => 'DZN',
        21 => 'BJ',
        22 => 'CS',
        23 => 'Z3',
        24 => 'BO',
        25 => 'OZA',
        26 => 'JR',
        27 => 'CG',
        28 => 'CT',
        29 => 'KT',
        30 => 'AA',
        31 => 'MTR',
        32 => 'MLT',
        33 => 'MMT',
        34 => 'PR',
        35 => 'PA',
        36 => 'PK',
        37 => 'D97',
        38 => 'MTK',
        39 => 'CMK',
        40 => 'MMK',
        41 => 'SCM',
        42 => 'SMM',
        43 => 'RO',
        44 => 'SA',
        45 => 'SET',
        46 => 'RL',
        47 => 'EA',
        48 => 'TU',
        49 => 'OZ',
        50 => 'WE',
    );

    /**
     * Map unit id to actual value
     *
     * @codeCoverageIgnore
     * @param int $id
     * @return mixed|bool
     */
    public static function getUnitValue($id)
    {
        if (isset(static::$units[$id])) {
            return static::$units[$id];
        }

        return '';
    }
}