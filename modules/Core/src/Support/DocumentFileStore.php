<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\ExpiringDocumentFile;
use Throwable;

/**
 * Validates and stores the files of an expiring document.
 *
 * - The type is checked by content (finfo), not by the extension or the
 *   MIME type sent by the browser: a renamed file is rejected.
 * - Stored on the default disk (S3 in production), never public, under
 *   companies/{company}/documents/{document}/ with a random name.
 */
final class DocumentFileStore
{
    /**
     * Validation rules for the "files" input (also used by the screens).
     *
     * @return array<string, list<string>>
     */
    public static function rules(int $alreadyAttached = 0): array
    {
        $max = max(0, self::maxFiles() - $alreadyAttached);

        return [
            'files' => ['array', 'max:'.$max],
            'files.*' => self::fileRules(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            'mimetypes:'.implode(',', self::mimeTypes()),
            'max:'.self::maxFileKb(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function mimeTypes(): array
    {
        /** @var list<string> $types */
        $types = config('integra.documents.mime_types');

        return $types;
    }

    public static function maxFileKb(): int
    {
        return (int) config('integra.documents.max_file_kb');
    }

    public static function maxFiles(): int
    {
        return (int) config('integra.documents.max_files');
    }

    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function validate(array $files, int $alreadyAttached = 0): void
    {
        Validator::make(['files' => $files], self::rules($alreadyAttached), [], [
            'files' => __('core::documents.fields.files'),
            'files.*' => __('core::documents.fields.file'),
        ])->validate();
    }

    /**
     * Stores already validated files. If anything fails, the files written by
     * this call are removed before rethrowing (the caller's transaction rolls
     * back the rows).
     *
     * @param  list<UploadedFile>  $files
     */
    public function store(ExpiringDocument $document, array $files): void
    {
        $disk = Storage::disk();
        $directory = 'companies/'.$document->company_id.'/documents/'.$document->getKey();
        $written = [];

        try {
            foreach ($files as $file) {
                $mime = (string) $file->getMimeType();
                $name = Str::ulid()->toBase32().'.'.($file->guessExtension() ?? 'bin');
                $path = $disk->putFileAs($directory, $file, $name);

                if ($path === false) {
                    throw new \RuntimeException('The document file could not be stored.');
                }

                $written[] = $path;

                $document->files()->create([
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime_type' => $mime,
                    'size' => (int) $file->getSize(),
                ]);
            }
        } catch (Throwable $exception) {
            $disk->delete($written);

            throw $exception;
        }
    }

    /**
     * Short-lived signed URL, only after the policy allowed the download.
     */
    public function temporaryUrl(ExpiringDocumentFile $file): string
    {
        return Storage::disk()->temporaryUrl(
            $file->path,
            now()->addMinutes((int) config('integra.documents.temporary_url_minutes')),
            ['ResponseContentDisposition' => 'inline; filename="'.self::safeFilename($file->original_name).'"'],
        );
    }

    /**
     * ASCII letters, digits, dots, hyphens and underscores only: the name ends
     * up in a response header.
     */
    private static function safeFilename(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($name));
    }
}
