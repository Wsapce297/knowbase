<?php

namespace App\Enums;

/**
 * The stages a document goes through after it is uploaded.
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    /**
     * True once the job has finished, whether it succeeded or not.
     */
    public function isFinished(): bool
    {
        return $this === self::Ready || $this === self::Failed;
    }
}
