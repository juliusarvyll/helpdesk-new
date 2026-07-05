<x-filament-panels::page>
    @php($metrics = $this->metrics())
    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section><div class="text-sm">PMS Compliance</div><div class="text-2xl font-bold">{{ $metrics["compliance"] }}%</div></x-filament::section>
        <x-filament::section><div class="text-sm">Overdue PMS</div><div class="text-2xl font-bold">{{ $metrics["overdue"] }}</div></x-filament::section>
        <x-filament::section><div class="text-sm">Assets Not Inspected</div><div class="text-2xl font-bold">{{ $metrics["not_inspected"] }}</div></x-filament::section>
        <x-filament::section><div class="text-sm">Needs Repair</div><div class="text-2xl font-bold">{{ $metrics["needs_repair"] }}</div></x-filament::section>
    </div>
    <form wire:submit="generatePdf" class="space-y-6">
        {{ $this->form }}
        <div class="flex justify-end"><x-filament::button type="submit" icon="heroicon-o-document-arrow-down">Generate PDF</x-filament::button></div>
    </form>
</x-filament-panels::page>
