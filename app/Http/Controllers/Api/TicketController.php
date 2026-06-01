<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketCreated;
use App\TicketStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,normal,high,critical',
            'client_id' => 'required|exists:users,id',
            'issue_id' => 'required|exists:issue_list,id',
            'asset_id' => 'nullable|string',
            'asset_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = User::findOrFail($request->client_id);

        $ticket = Ticket::create([
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority,
            'issue_id' => $request->issue_id,
            'client_id' => $client->id,
            'department_id' => $client->department_id,
            'status' => TicketStatus::Active,
            'asset_id' => $request->asset_id,
            'asset_name' => $request->asset_name,
            'created_by' => $client->id,
            'created_ticket' => $client->name,
        ]);

        $technicalUsers = User::role(['super_admin', 'admin', 'technical_support'])
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get();

        foreach ($technicalUsers as $user) {
            $user->notify(new NewTicketCreated($ticket));
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => [
                'ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority,
                'created_at' => $ticket->created_at,
            ],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority,
                'issue' => $ticket->issue?->issue,
                'department' => $ticket->department?->name,
                'asset_id' => $ticket->asset_id,
                'asset_name' => $ticket->asset_name,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ],
        ]);
    }
}
