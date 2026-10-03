<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

/**
 * Shows the validation errors of Core actions in Filament screens. Actions
 * validate on their own (they are the source of truth), with their own
 * keys ("plate", "limit"...): those keys are moved under the form's state
 * path so they appear next to their fields, and errors that belong to no
 * field (license limit, read-only...) are shown as a notification.
 */
final class ActionErrors
{
    /**
     * For resource pages (create and edit), whose form lives in "data".
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  list<string>  $fields  names of the form fields
     * @return T
     *
     * @throws ValidationException
     * @throws Halt
     */
    public static function onPage(Closure $callback, array $fields): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $general = array_diff_key($errors, array_flip($fields));

            if ($general !== []) {
                self::notify($general);
            }

            $mapped = [];

            foreach (array_intersect_key($errors, array_flip($fields)) as $key => $messages) {
                $mapped['data.'.$key] = $messages;
            }

            if ($mapped === []) {
                throw new Halt;
            }

            throw ValidationException::withMessages($mapped);
        }
    }

    /**
     * For modal actions: the errors are shown as a notification and the
     * modal stays open.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws Halt
     */
    public static function inModal(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            self::notify($exception->errors());

            throw new Halt;
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function notify(array $errors): void
    {
        Notification::make()
            ->danger()
            ->title(__('core::module.errors.not_saved'))
            ->body(implode(' ', array_merge(...array_values($errors))))
            ->persistent()
            ->send();
    }
}
