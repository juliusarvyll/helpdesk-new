<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="px-3 py-2">Completed</th>
                <th class="px-3 py-2">Inspector</th>
                <th class="px-3 py-2">Result</th>
                <th class="px-3 py-2">Remarks</th>
                <th class="px-3 py-2">Repair Ticket</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($checks as $check)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="px-3 py-2">{{ $check->completed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $check->inspector?->name ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $check->status->label() }}</td>
                    <td class="px-3 py-2">{{ $check->remarks ?: '-' }}</td>
                    <td class="px-3 py-2">{{ $check->ticket_id ? '#'.$check->ticket_id : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="px-3 py-6 text-center text-gray-500" colspan="5">No PMS inspections recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
