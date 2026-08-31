<?php

namespace App\Enums;

enum ScoreFileRenderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting to render'),
            self::Processing => __('Rendering'),
            self::Ready => __('Ready'),
            self::Failed => __('Rendering failed'),
            self::Unsupported => __('Preview not supported'),
        };
    }

    /**
     * Whether the renderer is done with this file, one way or the other.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending && $this !== self::Processing;
    }
}
