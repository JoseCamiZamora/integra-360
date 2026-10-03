<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Models\User;

/**
 * Creates the platform administrator from PLATFORM_ADMIN_* environment
 * variables (config/integra.php). Never overwrites an existing account.
 */
final class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name: ?string, document_type: ?string, document_number: ?string, email: ?string, password: ?string} $admin */
        $admin = config('integra.platform_admin');

        if (blank($admin['name']) || blank($admin['document_number']) || blank($admin['password'])) {
            $this->command->warn('PLATFORM_ADMIN_NAME, PLATFORM_ADMIN_DOCUMENT_NUMBER and PLATFORM_ADMIN_PASSWORD are required: platform administrator not created.');

            return;
        }

        $documentType = IdentityDocumentType::from((string) $admin['document_type']);

        $exists = User::query()
            ->where('document_type', $documentType)
            ->where('document_number', $admin['document_number'])
            ->exists();

        if ($exists) {
            $this->command->info('The platform administrator already exists: left unchanged.');

            return;
        }

        $user = new User([
            'name' => $admin['name'],
            'document_type' => $documentType,
            'document_number' => $admin['document_number'],
            'email' => blank($admin['email']) ? null : $admin['email'],
            'password' => $admin['password'],
            'must_change_password' => true,
        ]);
        $user->is_platform_admin = true;
        $user->save();

        $this->command->info('Platform administrator created. The password must be changed at first sign-in.');
    }
}
