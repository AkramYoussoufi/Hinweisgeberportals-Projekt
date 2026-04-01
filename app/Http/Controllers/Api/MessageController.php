<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request, string $referenceNumber)
    {
        $report = Report::where('reference_number', $referenceNumber)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        $messages = Message::where('report_id', $report->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id'          => $message->id,
                    'sender_role' => $message->sender_role,
                    'body'        => $message->body,
                    'read_at'     => $message->read_at,
                    'created_at'  => $message->created_at,
                ];
            });

        $this->markMessagesAsRead($report->id, 'admin');

        return response()->json([
            'messages' => $messages,
        ], 200);
    }

    public function store(Request $request, string $referenceNumber)
    {
        $validated = $request->validate([
            'body' => 'required|string|min:2',
        ]);

        $report = Report::where('reference_number', $referenceNumber)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        if ($report->status === 'closed') {
            return response()->json([
                'message' => 'Cannot send messages on a closed report',
            ], 422);
        }

        $message = Message::create([
            'report_id'   => $report->id,
            'sender_id'   => $request->user()->id,
            'sender_role' => 'whistleblower',
            'body'        => $validated['body'],
        ]);

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $request->user()->id,
            action: 'message_sent',
            newValue: ['sender_role' => 'whistleblower'],
            ip: $request->ip()
        );

        return response()->json([
            'message'    => 'Message sent successfully',
            'id'         => $message->id,
            'created_at' => $message->created_at,
        ], 201);
    }

    public function adminIndex(Request $request, string $referenceNumber)
    {
        $report = Report::where('reference_number', $referenceNumber)->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        $messages = Message::where('report_id', $report->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id'          => $message->id,
                    'sender_role' => $message->sender_role,
                    'body'        => $message->body,
                    'read_at'     => $message->read_at,
                    'created_at'  => $message->created_at,
                ];
            });

        $this->markMessagesAsRead($report->id, 'whistleblower');

        return response()->json([
            'messages' => $messages,
        ], 200);
    }

    public function adminStore(Request $request, string $referenceNumber)
    {
        $validated = $request->validate([
            'body' => 'required|string|min:2',
        ]);

        $report = Report::where('reference_number', $referenceNumber)->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        if ($report->status === 'closed') {
            return response()->json([
                'message' => 'Cannot send messages on a closed report',
            ], 422);
        }

        $message = Message::create([
            'report_id'   => $report->id,
            'sender_id'   => $request->user()->id,
            'sender_role' => 'admin',
            'body'        => $validated['body'],
        ]);

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $request->user()->id,
            action: 'message_sent',
            newValue: ['sender_role' => 'admin'],
            ip: $request->ip()
        );

        return response()->json([
            'message'    => 'Message sent successfully',
            'id'         => $message->id,
            'created_at' => $message->created_at,
        ], 201);
    }

    private function markMessagesAsRead(string $reportId, string $senderRole): void
    {
        Message::where('report_id', $reportId)
            ->where('sender_role', $senderRole)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
