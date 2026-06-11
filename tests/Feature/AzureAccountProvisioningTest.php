<?php

namespace Tests\Feature;

use App\Filament\Resources\AzureAccountProvisioningResource;
use App\Filament\Resources\AzureAccountProvisioningResource\Pages\EditAzureAccountProvisioning;
use App\Jobs\ImportMicrosoftUsers;
use App\MicrosoftGraphService;
use App\Models\AzureAccountProvisioning;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AzureAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_use_the_azure_account_resource(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $record = AzureAccountProvisioning::factory()->create();

        $this->actingAs($superAdmin);

        $this->assertTrue(AzureAccountProvisioningResource::canViewAny());
        $this->assertTrue($superAdmin->can('viewAny', AzureAccountProvisioning::class));
        $this->assertTrue($superAdmin->can('create', AzureAccountProvisioning::class));
        $this->assertTrue($superAdmin->can('update', $record));
        $this->assertTrue($superAdmin->can('delete', $record));
        $this->assertTrue(AzureAccountProvisioningResource::canDelete($record));
        $this->assertTrue(AzureAccountProvisioningResource::canDeleteAny());

        $this->actingAs($admin);

        $this->assertFalse(AzureAccountProvisioningResource::canViewAny());
        $this->assertFalse($admin->can('viewAny', AzureAccountProvisioning::class));
        $this->assertFalse($admin->can('create', AzureAccountProvisioning::class));
        $this->assertFalse($admin->can('update', $record));
        $this->assertFalse($admin->can('delete', $record));
        $this->assertFalse(AzureAccountProvisioningResource::canDelete($record));
        $this->assertFalse(AzureAccountProvisioningResource::canDeleteAny());
    }

    public function test_ms_teams_redirect_uri_requires_super_admin(): void
    {
        $department = Department::factory()->create();
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('microsoft-teams.oauth.callback'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('microsoft-teams.oauth.callback'))
            ->assertRedirect(AzureAccountProvisioningResource::getUrl('index', [
                'tenant' => $department,
            ]));
    }

    public function test_microsoft_graph_service_creates_user_and_assigns_license(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'value' => [
                    [
                        'skuId' => '18250162-5d87-4436-a834-d795c15c80f3',
                        'skuPartNumber' => 'M365EDU_A3_STUUSEBNFT',
                        'prepaidUnits' => ['enabled' => 100],
                        'consumedUnits' => 20,
                    ],
                ],
            ]),
            'graph.microsoft.com/v1.0/users' => Http::response([
                'id' => 'azure-user-id',
            ], 201),
            'graph.microsoft.com/v1.0/users/azure-user-id/assignLicense' => Http::response([], 200),
        ]);

        $account = AzureAccountProvisioning::factory()->create([
            'account_type' => 'student',
            'given_name' => 'Juan Carlos',
            'surname' => 'Dela Cruz',
            'user_principal_name' => 'manual@example.edu',
            'mail_nickname' => 'manual',
            'usage_location' => 'US',
            'license_sku_id' => null,
            'license_sku_part_number' => null,
        ]);

        app(MicrosoftGraphService::class)->provisionUser($account);

        $account->refresh();

        $this->assertSame('provisioned', $account->status);
        $this->assertSame('azure-user-id', $account->azure_user_id);
        $this->assertSame('juancarlosdelacruz@spup.edu.ph', $account->user_principal_name);
        $this->assertSame('juancarlosdelacruz', $account->mail_nickname);
        $this->assertSame('PH', $account->usage_location);
        $this->assertSame('18250162-5d87-4436-a834-d795c15c80f3', $account->license_sku_id);
        $this->assertSame('M365EDU_A3_STUUSEBNFT', $account->license_sku_part_number);
        $this->assertNotNull($account->temporary_password);
        $this->assertNotNull($account->provisioned_at);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token'
            && $request['client_id'] === 'client-id'
            && $request['client_secret'] === 'client-secret'
            && $request['grant_type'] === 'client_credentials'
            && $request['scope'] === 'https://graph.microsoft.com/.default');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/users'
            && $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['displayName'] === $account->display_name
            && $request['mailNickname'] === 'juancarlosdelacruz'
            && $request['userPrincipalName'] === 'juancarlosdelacruz@spup.edu.ph'
            && $request['usageLocation'] === 'PH');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/users/azure-user-id/assignLicense'
            && $request['addLicenses'][0]['skuId'] === '18250162-5d87-4436-a834-d795c15c80f3');
    }

    public function test_microsoft_graph_service_assigns_faculty_a3_license_for_faculty_accounts(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'value' => [
                    [
                        'skuId' => '18250162-5d87-4436-a834-d795c15c80f3',
                        'skuPartNumber' => 'M365EDU_A3_STUUSEBNFT',
                        'prepaidUnits' => ['enabled' => 100],
                        'consumedUnits' => 20,
                    ],
                    [
                        'skuId' => '4b590615-0888-425a-a965-b3bf7789848d',
                        'skuPartNumber' => 'M365EDU_A3_FACULTY',
                        'prepaidUnits' => ['enabled' => 50],
                        'consumedUnits' => 10,
                    ],
                ],
            ]),
            'graph.microsoft.com/v1.0/users' => Http::response([
                'id' => 'azure-user-id',
            ], 201),
            'graph.microsoft.com/v1.0/users/azure-user-id/assignLicense' => Http::response([], 200),
        ]);

        $account = AzureAccountProvisioning::factory()->create([
            'account_type' => 'faculty',
            'user_principal_name' => 'faculty.member@spup.edu.ph',
            'mail_nickname' => 'old-nickname',
            'usage_location' => 'US',
            'license_sku_id' => null,
            'license_sku_part_number' => null,
        ]);

        app(MicrosoftGraphService::class)->provisionUser($account);

        $account->refresh();

        $this->assertSame('provisioned', $account->status);
        $this->assertSame('faculty.member@spup.edu.ph', $account->user_principal_name);
        $this->assertSame('faculty.member', $account->mail_nickname);
        $this->assertSame('PH', $account->usage_location);
        $this->assertSame('4b590615-0888-425a-a965-b3bf7789848d', $account->license_sku_id);
        $this->assertSame('M365EDU_A3_FACULTY', $account->license_sku_part_number);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/users/azure-user-id/assignLicense'
            && $request['addLicenses'][0]['skuId'] === '4b590615-0888-425a-a965-b3bf7789848d');
    }

    public function test_student_user_principal_name_is_generated_from_given_name_and_surname(): void
    {
        $this->assertSame(
            'mariateresasantos@spup.edu.ph',
            AzureAccountProvisioningResource::generatedUserPrincipalName('Maria Teresa', 'Santos')
        );

        $this->assertSame(
            'johnpauldelacruz@spup.edu.ph',
            AzureAccountProvisioningResource::generatedUserPrincipalName('John-Paul', 'Dela Cruz')
        );
    }

    public function test_microsoft_graph_service_resets_user_password(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/users/azure-user-id' => Http::response([], 204),
        ]);

        $account = AzureAccountProvisioning::factory()->create([
            'azure_user_id' => 'azure-user-id',
            'user_principal_name' => 'student@spup.edu.ph',
            'temporary_password' => null,
            'status' => 'provisioned',
        ]);

        app(MicrosoftGraphService::class)->resetPassword($account);

        $account->refresh();

        $this->assertSame('password_reset', $account->status);
        $this->assertNotNull($account->temporary_password);

        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://graph.microsoft.com/v1.0/users/azure-user-id'
            && $request['passwordProfile']['forceChangePasswordNextSignIn'] === true
            && filled($request['passwordProfile']['password']));
    }

    public function test_deleting_azure_account_deletes_microsoft_directory_user_first(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/users/azure-user-id' => Http::response([], 204),
        ]);

        $account = AzureAccountProvisioning::factory()->create([
            'azure_user_id' => 'azure-user-id',
            'user_principal_name' => 'student@spup.edu.ph',
            'status' => 'provisioned',
        ]);

        $this->assertTrue(AzureAccountProvisioningResource::deleteAzureAccountFromDirectory($account));

        $this->assertSoftDeleted('azure_account_provisionings', [
            'id' => $account->id,
        ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://graph.microsoft.com/v1.0/users/azure-user-id'
            && $request->hasHeader('Authorization', 'Bearer fake-token'));
    }

    public function test_azure_account_delete_keeps_local_record_when_directory_delete_fails(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/users/azure-user-id' => Http::response([
                'error' => [
                    'message' => 'Insufficient privileges to complete the operation.',
                ],
            ], 403),
        ]);

        $account = AzureAccountProvisioning::factory()->create([
            'azure_user_id' => 'azure-user-id',
            'user_principal_name' => 'student@spup.edu.ph',
            'status' => 'provisioned',
        ]);

        $this->assertFalse(AzureAccountProvisioningResource::deleteAzureAccountFromDirectory($account));

        $account->refresh();

        $this->assertSame('failed', $account->status);
        $this->assertStringContainsString('403', $account->last_error);
    }

    public function test_bulk_azure_account_delete_soft_deletes_records_after_directory_delete(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/users/*' => Http::response([], 204),
        ]);

        $firstAccount = AzureAccountProvisioning::factory()->create([
            'azure_user_id' => 'first-azure-user-id',
            'user_principal_name' => 'first.student@spup.edu.ph',
            'status' => 'provisioned',
        ]);
        $secondAccount = AzureAccountProvisioning::factory()->create([
            'azure_user_id' => 'second-azure-user-id',
            'user_principal_name' => 'second.student@spup.edu.ph',
            'status' => 'provisioned',
        ]);

        $result = AzureAccountProvisioningResource::deleteAzureAccountsFromDirectory(
            AzureAccountProvisioning::query()->whereKey([$firstAccount->id, $secondAccount->id])->get()
        );

        $this->assertSame(['deleted' => 2, 'failed' => 0], $result);
        $this->assertSoftDeleted('azure_account_provisionings', ['id' => $firstAccount->id]);
        $this->assertSoftDeleted('azure_account_provisionings', ['id' => $secondAccount->id]);

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://graph.microsoft.com/v1.0/users/first-azure-user-id');
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://graph.microsoft.com/v1.0/users/second-azure-user-id');
    }

    public function test_microsoft_graph_service_imports_users_with_supported_a3_licenses(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'value' => [
                    [
                        'skuId' => 'student-sku-id',
                        'skuPartNumber' => 'M365EDU_A3_STUUSEBNFT',
                    ],
                    [
                        'skuId' => 'faculty-sku-id',
                        'skuPartNumber' => 'M365EDU_A3_FACULTY',
                    ],
                ],
            ]),
            'graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    [
                        'id' => 'student-azure-id',
                        'displayName' => 'Student User',
                        'givenName' => 'Student',
                        'surname' => 'User',
                        'userPrincipalName' => 'student.user@spup.edu.ph',
                        'mailNickname' => 'student.user',
                        'usageLocation' => null,
                        'assignedLicenses' => [
                            ['skuId' => 'student-sku-id'],
                        ],
                    ],
                    [
                        'id' => 'faculty-azure-id',
                        'displayName' => 'Faculty User',
                        'givenName' => 'Faculty',
                        'surname' => 'User',
                        'userPrincipalName' => 'faculty.user@spup.edu.ph',
                        'mailNickname' => 'faculty.user',
                        'usageLocation' => 'PH',
                        'assignedLicenses' => [
                            ['skuId' => 'faculty-sku-id'],
                        ],
                    ],
                    [
                        'id' => 'unsupported-azure-id',
                        'displayName' => 'Unsupported User',
                        'givenName' => 'Unsupported',
                        'surname' => 'User',
                        'userPrincipalName' => 'unsupported.user@spup.edu.ph',
                        'mailNickname' => 'unsupported.user',
                        'usageLocation' => 'PH',
                        'assignedLicenses' => [
                            ['skuId' => 'other-sku-id'],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(MicrosoftGraphService::class)->importUsers();

        $this->assertSame(['imported' => 2, 'skipped' => 1], $result);

        $student = AzureAccountProvisioning::query()->where('azure_user_id', 'student-azure-id')->firstOrFail();
        $faculty = AzureAccountProvisioning::query()->where('azure_user_id', 'faculty-azure-id')->firstOrFail();

        $this->assertSame('student', $student->account_type);
        $this->assertSame('student.user@spup.edu.ph', $student->user_principal_name);
        $this->assertSame('PH', $student->usage_location);
        $this->assertSame('student-sku-id', $student->license_sku_id);
        $this->assertSame('M365EDU_A3_STUUSEBNFT', $student->license_sku_part_number);
        $this->assertSame('imported', $student->status);

        $this->assertSame('faculty', $faculty->account_type);
        $this->assertSame('faculty-sku-id', $faculty->license_sku_id);
        $this->assertSame('M365EDU_A3_FACULTY', $faculty->license_sku_part_number);

        $this->assertFalse(AzureAccountProvisioning::query()->where('azure_user_id', 'unsupported-azure-id')->exists());
    }

    public function test_microsoft_graph_service_imports_only_selected_columns(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        $existing = AzureAccountProvisioning::factory()->create([
            'azure_user_id' => 'student-azure-id',
            'account_type' => 'student',
            'display_name' => 'Old Name',
            'given_name' => 'Old',
            'surname' => 'Name',
            'user_principal_name' => 'old.user@spup.edu.ph',
            'mail_nickname' => 'old.user',
            'usage_location' => 'PH',
            'license_sku_id' => 'old-sku-id',
            'license_sku_part_number' => 'OLD_SKU',
            'status' => 'provisioned',
        ]);

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'value' => [
                    [
                        'skuId' => 'student-sku-id',
                        'skuPartNumber' => 'M365EDU_A3_STUUSEBNFT',
                    ],
                ],
            ]),
            'graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    [
                        'id' => 'student-azure-id',
                        'displayName' => 'New Name',
                        'givenName' => 'New',
                        'surname' => 'Name',
                        'userPrincipalName' => 'new.user@spup.edu.ph',
                        'mailNickname' => 'new.user',
                        'usageLocation' => 'PH',
                        'assignedLicenses' => [
                            ['skuId' => 'student-sku-id'],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(MicrosoftGraphService::class)->importUsers([
            'display_name',
            'user_principal_name',
        ]);

        $existing->refresh();

        $this->assertSame(['imported' => 1, 'skipped' => 0], $result);
        $this->assertSame('New Name', $existing->display_name);
        $this->assertSame('new.user@spup.edu.ph', $existing->user_principal_name);
        $this->assertSame('Old', $existing->given_name);
        $this->assertSame('Name', $existing->surname);
        $this->assertSame('old.user', $existing->mail_nickname);
        $this->assertSame('old-sku-id', $existing->license_sku_id);
        $this->assertSame('OLD_SKU', $existing->license_sku_part_number);
        $this->assertSame('provisioned', $existing->status);
    }

    public function test_import_microsoft_users_job_runs_import_and_notifies_actor(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $graph = Mockery::mock(MicrosoftGraphService::class);
        $graph->shouldReceive('importUsers')
            ->once()
            ->with([
                'display_name',
                'user_principal_name',
            ])
            ->andReturn([
                'imported' => 3,
                'skipped' => 1,
            ]);

        (new ImportMicrosoftUsers([
            'display_name',
            'user_principal_name',
        ], $superAdmin->id))->handle($graph);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $superAdmin->getMorphClass(),
            'notifiable_id' => $superAdmin->id,
        ]);
    }

    public function test_microsoft_graph_service_fails_before_user_creation_when_automatic_license_is_unavailable(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'value' => [
                    [
                        'skuId' => '18250162-5d87-4436-a834-d795c15c80f3',
                        'skuPartNumber' => 'M365EDU_A3_STUUSEBNFT',
                        'prepaidUnits' => ['enabled' => 20],
                        'consumedUnits' => 20,
                    ],
                ],
            ]),
        ]);

        $account = AzureAccountProvisioning::factory()->create([
            'account_type' => 'student',
            'license_sku_id' => null,
            'license_sku_part_number' => null,
        ]);

        try {
            app(MicrosoftGraphService::class)->provisionUser($account);
            $this->fail('Expected automatic license resolution to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No available Microsoft 365 license seats found for [M365EDU_A3_STUUSEBNFT].', $exception->getMessage());
        }

        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/users');
    }

    public function test_microsoft_graph_service_lists_available_licenses(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'value' => [
                    [
                        'skuId' => 'available-sku-id',
                        'skuPartNumber' => 'STANDARDWOFFPACK_STUDENT',
                        'prepaidUnits' => ['enabled' => 25],
                        'consumedUnits' => 20,
                    ],
                    [
                        'skuId' => 'full-sku-id',
                        'skuPartNumber' => 'STANDARDWOFFPACK_FACULTY',
                        'prepaidUnits' => ['enabled' => 5],
                        'consumedUnits' => 5,
                    ],
                ],
            ]),
        ]);

        $options = app(MicrosoftGraphService::class)->availableLicenseOptions();

        $this->assertSame([
            'available-sku-id' => 'STANDARDWOFFPACK_STUDENT (5 available)',
        ], $options);
    }

    public function test_microsoft_graph_service_returns_no_license_options_when_graph_fails(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', 'client-secret');

        Http::fake([
            'login.microsoftonline.com/tenant-id/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'graph.microsoft.com/v1.0/subscribedSkus' => Http::response([
                'error' => ['message' => 'Forbidden'],
            ], 403),
        ]);

        $this->assertSame([], app(MicrosoftGraphService::class)->availableLicenseOptions());
    }

    public function test_microsoft_graph_service_rejects_secret_id_instead_of_secret_value(): void
    {
        config()->set('services.azure.tenant_id', 'tenant-id');
        config()->set('services.azure.client_id', 'client-id');
        config()->set('services.azure.client_secret', '17eaadc1-77a4-4e7a-9519-b0fe4d6de813');

        $account = AzureAccountProvisioning::factory()->create([
            'account_type' => 'student',
        ]);

        $this->expectExceptionMessage('AZURE_CLIENT_SECRET appears to be the Azure secret ID. Use the client secret value instead.');

        app(MicrosoftGraphService::class)->provisionUser($account);
    }

    public function test_edit_page_opens_one_time_credentials_modal_after_provisioning(): void
    {
        $department = Department::factory()->create();
        $superAdmin = $this->userWithRole('super_admin');
        $account = AzureAccountProvisioning::factory()->create([
            'user_principal_name' => 'student@example.edu',
        ]);

        Filament::setTenant($department, isQuiet: true);

        session()->put('azure_account_credentials_'.$account->getKey(), [
            'email' => 'student@example.edu',
            'password' => 'TemporaryPassword123!',
        ]);

        Livewire::actingAs($superAdmin)
            ->test(EditAzureAccountProvisioning::class, [
                'record' => $account->getKey(),
            ])
            ->assertSet('createdCredentials.email', 'student@example.edu')
            ->assertSet('createdCredentials.password', 'TemporaryPassword123!')
            ->assertSet('mountedActions.0', 'showAzureCredentials')
            ->assertSeeHtml('text-gray-900 dark:text-white')
            ->assertSeeHtml('text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white');

        $this->assertNull(session('azure_account_credentials_'.$account->getKey()));
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::findOrCreate($roleName);
        $user = User::factory()->create([
            'status' => 1,
            'is_deleted' => 0,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
