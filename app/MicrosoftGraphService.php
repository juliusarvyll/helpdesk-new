<?php

namespace App;

use App\Models\AzureAccountProvisioning;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MicrosoftGraphService
{
    private const GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const LICENSE_SKU_PART_NUMBERS = [
        'student' => 'M365EDU_A3_STUUSEBNFT',
        'faculty' => 'M365EDU_A3_FACULTY',
    ];

    private ?string $accessToken = null;

    /**
     * @return array<string, string>
     */
    public function availableLicenseOptions(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            return collect($this->graphGet('/subscribedSkus')->throw()->json('value', []))
                ->filter(fn (array $sku): bool => (int) ($sku['prepaidUnits']['enabled'] ?? 0) > (int) ($sku['consumedUnits'] ?? 0))
                ->mapWithKeys(function (array $sku): array {
                    $available = (int) ($sku['prepaidUnits']['enabled'] ?? 0) - (int) ($sku['consumedUnits'] ?? 0);
                    $label = sprintf('%s (%d available)', $sku['skuPartNumber'], $available);

                    return [$sku['skuId'] => $label];
                })
                ->all();
        } catch (Throwable $throwable) {
            Log::warning('Unable to load Azure license options.', [
                'message' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    public function provisionUser(AzureAccountProvisioning $account): AzureAccountProvisioning
    {
        $this->assertConfigured();

        $license = $this->licenseForAccountType($account->account_type);
        $userPrincipalName = $this->userPrincipalName($account);
        $mailNickname = $this->mailNickname($userPrincipalName);
        $temporaryPassword = Str::password(16);
        $createdUser = $this->graph()
            ->post('/users', [
                'accountEnabled' => true,
                'displayName' => $account->display_name,
                'givenName' => $account->given_name,
                'surname' => $account->surname,
                'mailNickname' => $mailNickname,
                'userPrincipalName' => $userPrincipalName,
                'usageLocation' => 'PH',
                'passwordProfile' => [
                    'forceChangePasswordNextSignIn' => true,
                    'password' => $temporaryPassword,
                ],
            ])
            ->throw()
            ->json();

        $account->forceFill([
            'azure_user_id' => Arr::get($createdUser, 'id'),
            'user_principal_name' => $userPrincipalName,
            'mail_nickname' => $mailNickname,
            'usage_location' => 'PH',
            'license_sku_id' => $license['sku_id'],
            'license_sku_part_number' => $license['sku_part_number'],
            'temporary_password' => $temporaryPassword,
            'status' => 'user_created',
            'last_error' => null,
        ])->save();

        $this->graph()
            ->post("/users/{$account->azure_user_id}/assignLicense", [
                'addLicenses' => [
                    [
                        'disabledPlans' => [],
                        'skuId' => $license['sku_id'],
                    ],
                ],
                'removeLicenses' => [],
            ])
            ->throw();

        $account->forceFill([
            'status' => 'provisioned',
            'last_error' => null,
            'provisioned_at' => now(),
        ])->save();

        return $account;
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    /**
     * @param  array<int, string>  $columns
     * @return array{imported: int, skipped: int}
     */
    public function importUsers(array $columns = []): array
    {
        $this->assertConfigured();

        $columns = $this->normalizedImportColumns($columns);
        $licenseSkuIds = $this->licenseSkuIdsByAccountType();
        $imported = 0;
        $skipped = 0;

        Log::info('Microsoft users import started.', [
            'columns' => $columns,
        ]);

        foreach ($this->microsoftUsers() as $microsoftUser) {
            $accountType = $this->accountTypeForMicrosoftUser($microsoftUser, $licenseSkuIds);

            if (! $accountType) {
                $skipped++;

                continue;
            }

            AzureAccountProvisioning::query()->updateOrCreate(
                ['azure_user_id' => $microsoftUser['id']],
                $this->importPayloadForMicrosoftUser($microsoftUser, $accountType, $licenseSkuIds, $columns),
            );

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    public function resetPassword(AzureAccountProvisioning $account): AzureAccountProvisioning
    {
        $this->assertConfigured();

        $temporaryPassword = Str::password(16);

        $this->graph()
            ->patch('/users/'.$this->graphUserIdentifier($account), [
                'passwordProfile' => [
                    'forceChangePasswordNextSignIn' => true,
                    'password' => $temporaryPassword,
                ],
            ])
            ->throw();

        $account->forceFill([
            'temporary_password' => $temporaryPassword,
            'status' => 'password_reset',
            'last_error' => null,
        ])->save();

        return $account;
    }

    public function deleteUser(AzureAccountProvisioning $account): AzureAccountProvisioning
    {
        $this->assertConfigured();

        if (blank($account->azure_user_id) && blank($account->user_principal_name)) {
            return $account;
        }

        $response = $this->graph()
            ->delete('/users/'.$this->graphUserIdentifier($account));

        if ($response->notFound()) {
            return $account;
        }

        $response->throw();

        return $account;
    }

    public function expectedLicenseSkuPartNumber(string $accountType): ?string
    {
        return self::LICENSE_SKU_PART_NUMBERS[$accountType] ?? null;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.azure.tenant_id'))
            && filled(config('services.azure.client_id'))
            && filled(config('services.azure.client_secret'));
    }

    private function graph(): PendingRequest
    {
        return $this->graphRequest()
            ->baseUrl(self::GRAPH_BASE_URL);
    }

    private function graphRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->timeout(20)
            ->connectTimeout(5)
            ->retry([200, 500, 1000], throw: false);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function graphGet(string $url, array $query = []): Response
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $this->graphRequest()->get($url);
        }

        return $this->graph()
            ->get($url, $query);
    }

    private function graphUserIdentifier(AzureAccountProvisioning $account): string
    {
        return rawurlencode($account->azure_user_id ?: $account->user_principal_name);
    }

    private function accessToken(): string
    {
        $this->assertConfigured();

        if ($this->accessToken) {
            return $this->accessToken;
        }

        $this->accessToken = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->post(sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', config('services.azure.tenant_id')), [
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ])
            ->throw()
            ->json('access_token');

        return $this->accessToken;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Azure app registration is not configured.');
        }

        if (Str::isUuid((string) config('services.azure.client_secret'))) {
            throw new RuntimeException('AZURE_CLIENT_SECRET appears to be the Azure secret ID. Use the client secret value instead.');
        }
    }

    private function userPrincipalName(AzureAccountProvisioning $account): string
    {
        if ($account->account_type !== 'student' && filled($account->user_principal_name)) {
            return $account->user_principal_name;
        }

        $localPart = Str::of($account->given_name.' '.$account->surname)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();

        return "{$localPart}@spup.edu.ph";
    }

    private function mailNickname(string $userPrincipalName): string
    {
        return Str::of($userPrincipalName)
            ->before('@')
            ->lower()
            ->toString();
    }

    /**
     * @return array<string, string>
     */
    public static function importableColumns(): array
    {
        return [
            'account_type' => 'Account Type',
            'display_name' => 'Display Name',
            'given_name' => 'Given Name',
            'surname' => 'Surname',
            'user_principal_name' => 'User Principal Name',
            'mail_nickname' => 'Mail Nickname',
            'usage_location' => 'Usage Location',
            'license' => 'A3 License',
            'status' => 'Status',
            'provisioned_at' => 'Provisioned At',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defaultImportColumns(): array
    {
        return array_keys(static::importableColumns());
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function normalizedImportColumns(array $columns): array
    {
        $columns = array_values(array_intersect($columns ?: static::defaultImportColumns(), array_keys(static::importableColumns())));

        return $columns ?: static::defaultImportColumns();
    }

    /**
     * @param  array<string, mixed>  $microsoftUser
     * @param  array<string, string>  $licenseSkuIds
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function importPayloadForMicrosoftUser(array $microsoftUser, string $accountType, array $licenseSkuIds, array $columns): array
    {
        $payload = [];

        foreach ($columns as $column) {
            match ($column) {
                'account_type' => $payload['account_type'] = $accountType,
                'display_name' => $payload['display_name'] = $microsoftUser['displayName'] ?? $microsoftUser['userPrincipalName'],
                'given_name' => $payload['given_name'] = $microsoftUser['givenName'] ?? '',
                'surname' => $payload['surname'] = $microsoftUser['surname'] ?? '',
                'user_principal_name' => $payload['user_principal_name'] = $microsoftUser['userPrincipalName'],
                'mail_nickname' => $payload['mail_nickname'] = $microsoftUser['mailNickname'] ?? $this->mailNickname($microsoftUser['userPrincipalName']),
                'usage_location' => $payload['usage_location'] = ($microsoftUser['usageLocation'] ?? null) ?: 'PH',
                'license' => $payload = [
                    ...$payload,
                    'license_sku_id' => $licenseSkuIds[$accountType],
                    'license_sku_part_number' => $this->expectedLicenseSkuPartNumber($accountType),
                ],
                'status' => $payload['status'] = 'imported',
                'provisioned_at' => $payload['provisioned_at'] = now(),
                default => null,
            };
        }

        $payload['last_error'] = null;

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function licenseSkuIdsByAccountType(): array
    {
        $expectedPartNumbers = array_flip(self::LICENSE_SKU_PART_NUMBERS);

        return collect($this->graphGet('/subscribedSkus')->throw()->json('value', []))
            ->filter(fn (array $sku): bool => isset($expectedPartNumbers[$sku['skuPartNumber'] ?? null]))
            ->mapWithKeys(fn (array $sku): array => [
                $expectedPartNumbers[$sku['skuPartNumber']] => $sku['skuId'],
            ])
            ->all();
    }

    /**
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function microsoftUsers(): LazyCollection
    {
        return LazyCollection::make(function (): \Generator {
            $url = '/users';
            $query = [
                '$select' => 'id,displayName,givenName,surname,userPrincipalName,mailNickname,usageLocation,assignedLicenses,accountEnabled',
                '$top' => 999,
            ];
            $page = 0;
            $totalLoaded = 0;
            $seenNextLinks = [];

            do {
                $response = $this->graphGet($url, $query)->throw()->json();
                $page++;
                $pageUsers = $response['value'] ?? [];
                $totalLoaded += count($pageUsers);
                $url = $response['@odata.nextLink'] ?? null;
                $query = [];

                Log::info('Microsoft users import page loaded.', [
                    'page' => $page,
                    'users_loaded' => count($pageUsers),
                    'total_loaded' => $totalLoaded,
                    'has_next_page' => filled($url),
                ]);

                if ($url && isset($seenNextLinks[$url])) {
                    throw new RuntimeException('Microsoft Graph returned a repeated users pagination link.');
                }

                if ($url) {
                    $seenNextLinks[$url] = true;
                }

                foreach ($pageUsers as $user) {
                    yield $user;
                }
            } while ($url);
        });
    }

    /**
     * @param  array<string, mixed>  $microsoftUser
     * @param  array<string, string>  $licenseSkuIds
     */
    private function accountTypeForMicrosoftUser(array $microsoftUser, array $licenseSkuIds): ?string
    {
        $assignedSkuIds = collect($microsoftUser['assignedLicenses'] ?? [])
            ->pluck('skuId')
            ->all();

        foreach ($licenseSkuIds as $accountType => $skuId) {
            if (in_array($skuId, $assignedSkuIds, true)) {
                return $accountType;
            }
        }

        return null;
    }

    /**
     * @return array{sku_id: string, sku_part_number: string}
     */
    private function licenseForAccountType(string $accountType): array
    {
        $skuPartNumber = $this->expectedLicenseSkuPartNumber($accountType);

        if (! $skuPartNumber) {
            throw new RuntimeException("Unsupported Azure account type [{$accountType}].");
        }

        $sku = collect($this->graphGet('/subscribedSkus')->throw()->json('value', []))
            ->first(function (array $sku) use ($skuPartNumber): bool {
                $available = (int) ($sku['prepaidUnits']['enabled'] ?? 0) - (int) ($sku['consumedUnits'] ?? 0);

                return ($sku['skuPartNumber'] ?? null) === $skuPartNumber && $available > 0;
            });

        if (! $sku) {
            throw new RuntimeException("No available Microsoft 365 license seats found for [{$skuPartNumber}].");
        }

        return [
            'sku_id' => $sku['skuId'],
            'sku_part_number' => $sku['skuPartNumber'],
        ];
    }
}
