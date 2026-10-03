<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ExpiringDocumentFactory;
use Modules\Core\Enums\DocumentStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A document with (usually) an expiry date, of a person or a vehicle.
 *
 * - Renewing never overwrites: a new record becomes the current one and the
 *   previous one is kept with is_current = false (RenewExpiringDocument).
 * - Never deleted physically (legal retention of 5 years): soft deletes only.
 * - The status is calculated from the dates (DocumentStatus), never stored.
 *
 * @property string $id
 * @property string $company_id
 * @property string $documentable_type
 * @property string $documentable_id
 * @property string $document_type_id
 * @property string|null $number
 * @property string|null $issuer
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property bool $is_current
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read DocumentType $documentType
 */
#[Fillable(['document_type_id', 'number', 'issuer', 'issued_at', 'expires_at', 'notes'])]
class ExpiringDocument extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ExpiringDocumentFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_current' => true,
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return HasMany<ExpiringDocumentFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ExpiringDocumentFile::class);
    }

    public function status(?CarbonInterface $now = null): DocumentStatus
    {
        return DocumentStatus::evaluate($this->expires_at, $this->documentType->warning_days, $now);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Only dates and the current mark: numbers and notes may hold personal
     * data and stay out of the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['document_type_id', 'documentable_type', 'documentable_id', 'issued_at', 'expires_at', 'is_current'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'is_current' => 'boolean',
        ];
    }

    protected static function newFactory(): ExpiringDocumentFactory
    {
        return ExpiringDocumentFactory::new();
    }
}
