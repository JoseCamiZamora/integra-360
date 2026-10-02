<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A user's membership in one company (company_user). Company-scoped: the
 * users of the active company are the users with a membership here.
 *
 * @property string $id
 * @property string $company_id
 * @property int $user_id
 * @property string|null $branch_id
 * @property bool $is_active
 * @property Carbon|null $joined_at
 */
#[Fillable(['company_id', 'user_id', 'branch_id', 'is_active', 'joined_at'])]
class Membership extends Pivot
{
    use BelongsToCompany, HasUlids, LogsActivity;

    public $incrementing = false;

    protected $table = 'company_user';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'branch_id', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
