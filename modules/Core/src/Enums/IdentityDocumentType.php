<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Colombian identity documents (users and people). Not to be confused with
 * the DocumentType model: the configurable catalog of expiring documents.
 */
enum IdentityDocumentType: string
{
    case CC = 'CC';
    case CE = 'CE';
    case PPT = 'PPT';
    case PA = 'PA';
    case TI = 'TI';

    public function label(): string
    {
        return __('core::enums.identity_document_type.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value.' · '.$case->label();
        }

        return $options;
    }
}
