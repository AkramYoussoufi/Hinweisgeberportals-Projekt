<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'          => 'required|in:fraud,harassment,safety,discrimination,other',
            'subject'           => 'required|string|max:255',
            'description'       => 'required|string|min:20',
            'incident_date'     => 'nullable|date|before_or_equal:today',
            'incident_location' => 'nullable|string|max:255',
            'involved_persons'  => 'nullable|string',
        ]);

        $user = auth('sanctum')->user();
        $isAnonymous = !$user;
        $anonymousData = null;

        if ($isAnonymous) {
            $anonymousData = $this->reportService->createAnonymousUser();
            $user          = $anonymousData['user'];
        }

        $report = Report::create([
            'user_id'           => $user->id,
            'reference_number'  => $this->reportService->generateReferenceNumber(),
            'category'          => $validated['category'],
            'status'            => 'received',
            'subject'           => $validated['subject'],
            'description'       => $validated['description'],
            'incident_date'     => $validated['incident_date'] ?? null,
            'incident_location' => $validated['incident_location'] ?? null,
            'involved_persons'  => $validated['involved_persons'] ?? null,
            'is_anonymous'      => $isAnonymous,
        ]);

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $user->id,
            action: 'report_submitted',
            newValue: ['reference_number' => $report->reference_number, 'category' => $report->category],
            ip: $request->ip()
        );

        if ($isAnonymous) {
            return response()->json([
                'message'          => 'Report submitted successfully',
                'reference_number' => $report->reference_number,
                'anonymous_access' => [
                    'token' => $anonymousData['token'],
                    'pin'   => $anonymousData['pin'],
                    'warning' => 'Save these credentials. They will never be shown again.',
                ],
            ], 201);
        }

        return response()->json([
            'message'          => 'Report submitted successfully',
            'reference_number' => $report->reference_number,
        ], 201);
    }
}
