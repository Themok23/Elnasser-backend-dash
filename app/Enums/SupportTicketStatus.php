<?php

namespace App\Enums;

enum SupportTicketStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';

    public static function values(): array
    {
        return [
            self::OPEN,
            self::IN_PROGRESS,
            self::RESOLVED,
            self::CLOSED,
        ];
    }

    public static function labels(): array
    {
        return [
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In progress',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        ];
    }
}


