<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * What a document type applies to. Also the morph alias of the documentable model.
 */
enum DocumentAppliesTo: string
{
    case Person = 'person';
    case Vehicle = 'vehicle';

    public function label(): string
    {
        return __('core::enums.document_applies_to.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
