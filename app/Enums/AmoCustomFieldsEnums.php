<?php

declare(strict_types=1);

namespace App\Enums;
enum AmoCustomFieldsEnums
{
    case EMAIL;
    case PHONE;
    case AGE_FIELDS_ID;
    case GENDER_FIELDS_ID;

    public function getValue(): mixed
    {
        return match ($this) {
            self::EMAIL => 'EMAIL',
            self::PHONE => 'PHONE',
            self::AGE_FIELDS_ID => 634311,
            self::GENDER_FIELDS_ID => 647239,
        };
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
