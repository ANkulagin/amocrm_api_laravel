<?php

declare(strict_types=1);

namespace App\Enums;
enum AmoCustomFieldsEnums
{
    case EMAIL;
    case PHONE;
    case AGE_FIELDS_ID;
    case GENDER_FIELDS_ID;
    public function getAmoCustomFields(AmoCustomFieldsEnums $amo)
    {
        switch ($amo) {
            case self::EMAIL:
                return 'EMAIL';
            case self::PHONE:
                return 'PHONE';
            case self::AGE_FIELDS_ID:
                return 634311;
            case self::GENDER_FIELDS_ID:
                return 647239;
        }
    }
}
















//{
//    /**
//     * @var string
//     */
//    public const EMAIL = 'EMAIL';
//
//    /**
//     * @var string
//     */
//    public const PHONE = 'PHONE';
//
//    /**
//     * @var int
//     */
//    public const AGE_FIELDS_ID = 634311;
//
//    /**
//     * @var int
//     */
//    public const GENDER_FIELDS_ID = 647239;
//}
