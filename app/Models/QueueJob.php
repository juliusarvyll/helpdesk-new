<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    public $timestamps = false;

    protected $table = 'jobs';

    protected $guarded = [];

    protected function payloadData(): Attribute
    {
        return Attribute::get(fn (): array => json_decode($this->payload, true) ?: []);
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->payload_data['displayName'] ?? 'Unknown job');
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => $this->reserved_at ? 'processing' : 'queued');
    }

    protected function availableAtDate(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->available_at ? CarbonImmutable::createFromTimestamp($this->available_at) : null);
    }

    protected function createdAtDate(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->created_at ? CarbonImmutable::createFromTimestamp($this->created_at) : null);
    }

    protected function reservedAtDate(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->reserved_at ? CarbonImmutable::createFromTimestamp($this->reserved_at) : null);
    }
}
