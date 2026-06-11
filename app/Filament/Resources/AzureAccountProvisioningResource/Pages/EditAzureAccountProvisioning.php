<?php

namespace App\Filament\Resources\AzureAccountProvisioningResource\Pages;

use App\Filament\Resources\AzureAccountProvisioningResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class EditAzureAccountProvisioning extends EditRecord
{
    protected static string $resource = AzureAccountProvisioningResource::class;

    /**
     * @var array{email: string, password: string}|null
     */
    public ?array $createdCredentials = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('This deletes the user from Microsoft Entra ID first, then removes the local Azure Account record.')
                ->using(fn (): bool => AzureAccountProvisioningResource::deleteAzureAccountFromDirectory($this->getRecord())),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->createdCredentials = session()->pull($this->credentialsSessionKey());

        if ($this->createdCredentials) {
            $this->mountAction('showAzureCredentials');
        }
    }

    public function showAzureCredentialsAction(): Action
    {
        return Action::make('showAzureCredentials')
            ->modalHeading('Azure Account Credentials')
            ->modalDescription('Copy these credentials now. The generated password is shown only once after provisioning.')
            ->modalIcon('heroicon-o-key')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->modalContent(fn (): HtmlString => new HtmlString($this->credentialsModalHtml()));
    }

    private function credentialsSessionKey(): string
    {
        return 'azure_account_credentials_'.$this->getRecord()->getKey();
    }

    private function credentialsModalHtml(): string
    {
        $email = e($this->createdCredentials['email'] ?? '');
        $password = e($this->createdCredentials['password'] ?? '');

        return <<<HTML
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-semibold text-gray-900 dark:text-white">Email</label>
                    <div class="mt-1 break-all rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm font-medium text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">{$email}</div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-900 dark:text-white">Generated Password</label>
                    <div class="mt-1 break-all rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm font-medium text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">{$password}</div>
                </div>
            </div>
        HTML;
    }
}
