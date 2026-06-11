<?php

namespace App\Models;

use Database\Factories\AzureAccountProvisioningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AzureAccountProvisioning extends Model
{
    /** @use HasFactory<AzureAccountProvisioningFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_type',
        'display_name',
        'given_name',
        'surname',
        'user_principal_name',
        'mail_nickname',
        'usage_location',
        'license_sku_id',
        'license_sku_part_number',
        'azure_user_id',
        'temporary_password',
        'status',
        'last_error',
        'provisioned_at',
    ];

    protected $attributes = [
        'usage_location' => 'PH',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
            'temporary_password' => 'encrypted',
        ];
    }
}
