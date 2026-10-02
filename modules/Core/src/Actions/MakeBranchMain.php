<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Branch;

/**
 * Moves the "main branch" mark to $branch (one per company). Runs inside the
 * branch's company context: the previous main branch is found by scope.
 */
final class MakeBranchMain
{
    public function handle(Branch $branch): void
    {
        if ($branch->is_main) {
            return;
        }

        DB::transaction(function () use ($branch): void {
            Branch::query()->where('is_main', true)->get()->each(function (Branch $current): void {
                $current->is_main = false;
                $current->save();
            });

            $branch->is_main = true;
            $branch->is_active = true;
            $branch->save();
        });
    }
}
