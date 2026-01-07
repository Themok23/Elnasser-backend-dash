<?php

namespace App\Enums;

enum SupportTicketInquiryType
{
    public const GENERAL = 'general';
    public const TECHNICAL = 'technical';
    public const ORDER = 'order';
    public const PAYMENT = 'payment';
    public const DELIVERY = 'delivery';
    public const STORE = 'store';
    public const COMPLAINT = 'complaint';
    public const SUGGESTION = 'suggestion';

    public static function values(): array
    {
        return [
            self::GENERAL,
            self::TECHNICAL,
            self::ORDER,
            self::PAYMENT,
            self::DELIVERY,
            self::STORE,
            self::COMPLAINT,
            self::SUGGESTION,
        ];
    }

    public static function labels(): array
    {
        return [
            self::GENERAL => 'General',
            self::TECHNICAL => 'Technical',
            self::ORDER => 'Order',
            self::PAYMENT => 'Payment',
            self::DELIVERY => 'Delivery',
            self::STORE => 'Store/Branch',
            self::COMPLAINT => 'Complaint',
            self::SUGGESTION => 'Suggestion',
        ];
    }
}


