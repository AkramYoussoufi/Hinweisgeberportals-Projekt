<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function listAdmins()
    {
        $admins = User::whereIn('role', ['admin', 'superadmin', 'deactivated_admin'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admin) {
                return [
                    'id'         => $admin->id,
                    'role'       => $admin->role,
                    'created_at' => $admin->created_at,
                ];
            });

        return response()->json(['admins' => $admins], 200);
    }

    public function createAdmin(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:8',
            'role'     => 'required|in:admin,superadmin',
        ]);

        $emailHash = hash('sha256', strtolower(trim($validated['email'])));

        $existing = User::where('email_hash', $emailHash)->first();
        if ($existing) {
            return response()->json([
                'message' => 'An account with this email already exists.',
            ], 422);
        }

        $admin = User::create([
            'email'        => $validated['email'],
            'email_hash'   => $emailHash,
            'password'     => $validated['password'],
            'role'         => $validated['role'],
            'is_anonymous' => false,
        ]);

        $this->reportService->logAction(
            reportId: null,
            actorId: $request->user()->id,
            action: 'admin_created',
            newValue: ['role' => $validated['role']],
            ip: $request->ip()
        );

        return response()->json([
            'message' => 'Admin account created successfully.',
            'id'      => $admin->id,
            'role'    => $admin->role,
        ], 201);
    }

    public function deactivateAdmin(Request $request, string $adminId)
    {
        $admin = User::find($adminId);

        if (!$admin || !in_array($admin->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Admin not found.'], 404);
        }

        if ($admin->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $admin->update(['role' => 'deactivated_admin']);

        $this->reportService->logAction(
            reportId: null,
            actorId: $request->user()->id,
            action: 'admin_deactivated',
            newValue: ['deactivated_id' => $adminId],
            ip: $request->ip()
        );

        return response()->json(['message' => 'Admin deactivated successfully.'], 200);
    }

    public function reactivateAdmin(Request $request, string $adminId)
    {
        $admin = User::find($adminId);

        if (!$admin || $admin->role !== 'deactivated_admin') {
            return response()->json(['message' => 'Admin not found or not deactivated.'], 404);
        }

        $admin->update(['role' => 'admin']);

        $this->reportService->logAction(
            reportId: null,
            actorId: $request->user()->id,
            action: 'admin_reactivated',
            newValue: ['reactivated_id' => $adminId],
            ip: $request->ip()
        );

        return response()->json(['message' => 'Admin reactivated successfully.'], 200);
    }

    public function deleteAdmin(Request $request, string $adminId)
    {
        $admin = User::find($adminId);

        if (!$admin || !in_array($admin->role, ['admin', 'superadmin', 'deactivated_admin'])) {
            return response()->json(['message' => 'Admin not found.'], 404);
        }

        if ($admin->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $this->reportService->logAction(
            reportId: null,
            actorId: $request->user()->id,
            action: 'admin_deleted',
            newValue: ['deleted_id' => $adminId, 'deleted_role' => $admin->role],
            ip: $request->ip()
        );

        $admin->delete();

        return response()->json(['message' => 'Admin deleted successfully.'], 200);
    }

    public function changeAdminPassword(Request $request, string $adminId)
    {
        $validated = $request->validate([
            'password' => 'required|min:8',
        ]);

        $admin = User::find($adminId);

        if (!$admin || !in_array($admin->role, ['admin', 'superadmin', 'deactivated_admin'])) {
            return response()->json(['message' => 'Admin not found.'], 404);
        }

        if ($admin->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own password this way.'], 422);
        }

        $admin->update(['password' => $validated['password']]);

        $this->reportService->logAction(
            reportId: null,
            actorId: $request->user()->id,
            action: 'admin_password_changed',
            newValue: ['target_id' => $adminId],
            ip: $request->ip()
        );

        return response()->json(['message' => 'Password changed successfully.'], 200);
    }

    public function unlockIdentity(Request $request, string $referenceNumber)
    {
        $report = Report::where('reference_number', $referenceNumber)->first();

        if (!$report) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $whistleblower = $report->user;

        if (!$whistleblower || $whistleblower->is_anonymous) {
            return response()->json([
                'message' => 'This report was submitted anonymously. No identity data exists.',
            ], 422);
        }

        if (!$whistleblower->email) {
            return response()->json([
                'message' => 'No identity data available for this user.',
            ], 422);
        }

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $request->user()->id,
            action: 'identity_unlocked',
            newValue: ['reference_number' => $referenceNumber, 'unlocked_by' => $request->user()->id],
            ip: $request->ip()
        );

        return response()->json([
            'message'          => 'Identity unlocked. This action has been logged.',
            'reference_number' => $referenceNumber,
            'email'            => $whistleblower->email,
        ], 200);
    }
}
