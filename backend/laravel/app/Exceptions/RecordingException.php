<?php

namespace App\Exceptions;

use App\Models\Track;
use RuntimeException;

/**
 * Domain error for live track recording. `reason` is a stable machine code the
 * controller maps to an HTTP status; the message is Indonesian UI text.
 */
class RecordingException extends RuntimeException
{
    public const ACTIVE_EXISTS  = 'active_exists';
    public const NOT_ACTIVE     = 'not_active';
    public const LIMIT_REACHED  = 'limit_reached';

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?Track $track = null,
    ) {
        parent::__construct($message);
    }

    public static function activeExists(Track $track): self
    {
        return new self(self::ACTIVE_EXISTS, 'Masih ada rekaman yang berjalan. Lanjutkan atau selesaikan dulu.', $track);
    }

    public static function notActive(Track $track): self
    {
        return new self(self::NOT_ACTIVE, 'Track ini sudah selesai atau tidak sedang direkam.', $track);
    }

    public static function limitReached(Track $track): self
    {
        return new self(self::LIMIT_REACHED, 'Batas jumlah titik track tercapai. Perekaman dihentikan.', $track);
    }
}
