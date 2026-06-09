<x-filament-panels::page>
    <form wire:submit="generatePdf" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-document-arrow-down">
                Generate PDF
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
