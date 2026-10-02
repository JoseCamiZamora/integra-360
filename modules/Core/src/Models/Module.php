<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Product catalogue entry. `code` matches the key in config/modules.php for
 * modules that already exist.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property bool $is_licensable
 * @property bool $is_available
 */
#[Fillable(['code', 'name', 'is_licensable', 'is_available'])]
class Module extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_licensable' => 'boolean',
            'is_available' => 'boolean',
        ];
    }
}
