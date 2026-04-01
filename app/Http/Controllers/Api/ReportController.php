<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use App\Notifications\NewReportNotification;
use Illuminate\Support\Facades\Notification;

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

        try {
            $admins = \App\Models\User::whereIn('role', ['admin', 'superadmin'])
                ->whereNotNull('email')
                ->get();
            \Illuminate\Support\Facades\Notification::send($admins, new NewReportNotification($report));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send new report notification: ' . $e->getMessage());
        }

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

    public function index(Request $request)
    {
        $reports = Report::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'reference_number'  => $report->reference_number,
                    'category'          => $report->category,
                    'status'            => $report->status,
                    'subject'           => $report->subject,
                    'incident_date'     => $report->incident_date,
                    'incident_location' => $report->incident_location,
                    'created_at'        => $report->created_at,
                ];
            });

        return response()->json([
            'reports' => $reports,
        ], 200);
    }

    public function show(Request $request, string $referenceNumber)
    {
        $report = Report::where('reference_number', $referenceNumber)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'reference_number'  => $report->reference_number,
            'category'          => $report->category,
            'status'            => $report->status,
            'subject'           => $report->subject,
            'description'       => $report->description,
            'incident_date'     => $report->incident_date,
            'incident_location' => $report->incident_location,
            'involved_persons'  => $report->involved_persons,
            'is_anonymous'      => $report->is_anonymous,
            'created_at'        => $report->created_at,
        ], 200);
    }
}
