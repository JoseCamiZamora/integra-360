<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Models\DocumentType;
use Modules\Core\Support\DocumentFileStore;

/**
 * Form fields of an expiring document, shared by every screen that
 * registers, renews or corrects one. Validation is repeated by the actions
 * (by content for files).
 */
final class DocumentFields
{
    /**
     * @param  DocumentType|null  $type  the type being renewed or corrected;
     *                                   null = the one selected in "document_type_id"
     * @return list<Component>
     */
    public static function data(?DocumentType $type = null): array
    {
        $requiresExpiry = function (Get $get) use ($type): bool {
            $selected = $type ?? DocumentType::query()->find($get('document_type_id'));

            return ! $selected instanceof DocumentType || $selected->requires_expiry;
        };

        return [
            TextInput::make('number')
                ->label(__('core::documents.fields.number'))
                ->maxLength(60)
                ->extraInputAttributes(['class' => 'font-mono']),
            TextInput::make('issuer')
                ->label(__('core::documents.fields.issuer'))
                ->maxLength(120),
            DatePicker::make('issued_at')
                ->label(__('core::documents.fields.issued_at'))
                ->native(false)
                ->maxDate(now()),
            DatePicker::make('expires_at')
                ->label(__('core::documents.fields.expires_at'))
                ->helperText(__('core::documents.help.expires_at'))
                ->native(false)
                ->required($requiresExpiry)
                ->visible($requiresExpiry)
                ->afterOrEqual('issued_at'),
            Textarea::make('notes')
                ->label(__('core::documents.fields.notes'))
                ->maxLength(2000)
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * PDF or photos, up to 4. On a phone the picker offers the camera, so
     * the document can be photographed directly. Files are not stored by
     * Filament: the action validates their content and stores them.
     */
    public static function files(int $alreadyAttached = 0): FileUpload
    {
        return FileUpload::make('files')
            ->label(__('core::documents.fields.files'))
            ->helperText(__('core::documents.help.files', ['max' => DocumentFileStore::maxFiles(), 'mb' => (int) (DocumentFileStore::maxFileKb() / 1024)]))
            ->multiple()
            ->maxFiles(max(0, DocumentFileStore::maxFiles() - $alreadyAttached))
            ->maxSize(DocumentFileStore::maxFileKb())
            ->acceptedFileTypes(DocumentFileStore::mimeTypes())
            ->storeFiles(false)
            ->previewable(false)
            ->columnSpanFull();
    }

    /**
     * The uploaded files of the form data, for the actions.
     *
     * @param  array<string, mixed>  $data
     * @return list<TemporaryUploadedFile>
     */
    public static function uploaded(array $data): array
    {
        return array_values(array_filter(
            (array) ($data['files'] ?? []),
            fn (mixed $file): bool => $file instanceof TemporaryUploadedFile,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withoutFiles(array $data): array
    {
        unset($data['files'], $data['document_type_id']);

        return $data;
    }
}
