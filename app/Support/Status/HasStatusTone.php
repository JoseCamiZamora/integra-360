<?php

declare(strict_types=1);

namespace App\Support\Status;

/**
 * Implemented by any status (usually a backed enum) that can be rendered
 * with <x-status-badge :status="..." />.
 */
interface HasStatusTone
{
    public function statusTone(): StatusTone;

    /**
     * Visible, translated label (e.g. "Apto con novedad").
     */
    public function statusLabel(): string;
}
