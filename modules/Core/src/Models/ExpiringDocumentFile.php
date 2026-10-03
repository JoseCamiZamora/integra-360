<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file (PDF or image) of an expiring document, on the default disk under
 * companies/{company}/documents. Never public: downloaded through
 * DownloadDocumentFile, which authorizes and redirects to a temporary URL.
 *
 * @property string $id
 * @property string $company_id
 * @property string $expiring_document_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExpiringDocument|null $document  null once the document is deleted
 */
#[Fillable(['path', 'original_name', 'mime_type', 'size'])]
class ExpiringDocumentFile extends Model
{
    use BelongsToCompany, HasUlids;

    /**
     * @return BelongsTo<ExpiringDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ExpiringDocument::class, 'expiring_document_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
