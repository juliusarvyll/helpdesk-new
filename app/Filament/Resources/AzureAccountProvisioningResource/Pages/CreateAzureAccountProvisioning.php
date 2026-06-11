<?php

namespace App\Filament\Resources\AzureAccountProvisioningResource\Pages;

use App\Filament\Resources\AzureAccountProvisioningResource;
use App\MicrosoftGraphService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateAzureAccountProvisioning extends CreateRecord
{
    protected static string $resource = AzureAccountProvisioningResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data['usage_location'] = 'PH';

        if (($data['account_type'] ?? null) === 'student') {
            $data['user_principal_name'] = AzureAccountProvisioningResource::generatedUserPrincipalName(
                $data['given_name'] ?? null,
                $data['surname'] ?? null,
            );
        }

        $data['mail_nickname'] = str($data['user_principal_name'])
            ->before('@')
            ->lower()
            ->toString();

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        try {
            app(MicrosoftGraphService::class)->provisionUser($this->record);

            session()->flash($this->credentialsSessionKey(), [
                'email' => $this->record->user_principal_name,
                'password' => $this->record->temporary_password,
            ]);
        } catch (Throwable $throwable) {
            $this->record->forceFill([
                'status' => 'failed',
                'last_error' => $throwable->getMessage(),
            ])->save();

            Notification::make()
                ->title('Azure provisioning failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    private function credentialsSessionKey(): string
    {
        return 'azure_account_credentials_'.$this->record->getKey();
    }
}
