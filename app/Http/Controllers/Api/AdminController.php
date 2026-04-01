<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use App\Notifications\StatusChangedNotification;

class AdminController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request)
    {
        $query = Report::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'reference_number'  => $report->reference_number,
                    'category'          => $report->category,
                    'status'            => $report->status,
                    'subject'           => $report->subject,
                    'is_anonymous'      => $report->is_anonymous,
                    'created_at'        => $report->created_at,
                ];
            });

        return response()->json([
            'reports' => $reports,
        ], 200);
    }

    public function show(Request $request, string $referenceNumber)
    {
        $report = Report::where('reference_number', $referenceNumber)->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $request->user()->id,
            action: 'report_viewed',
            newValue: ['reference_number' => $report->reference_number],
            ip: $request->ip()
        );

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

    public function updateStatus(Request $request, string $referenceNumber)
    {
        $validated = $request->validate([
            'status' => 'required|in:received,reviewing,clarification,closed',
        ]);

        $report = Report::where('reference_number', $referenceNumber)->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        $oldStatus = $report->status;
        $report->update([
            'status'    => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : $report->closed_at,
        ]);

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $request->user()->id,
            action: 'status_changed',
            oldValue: ['status' => $oldStatus],
            newValue: ['status' => $validated['status']],
            ip: $request->ip()
        );

        try {
            $whistleblower = $report->user;
            if ($whistleblower && !$whistleblower->is_anonymous && $whistleblower->email) {
                $whistleblower->notify(new StatusChangedNotification($report, $oldStatus, $validated['status']));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send status notification: ' . $e->getMessage());
        }

        return response()->json([
            'message'          => 'Status updated successfully',
            'reference_number' => $report->reference_number,
            'status'           => $report->status,
        ], 200);
    }
}
