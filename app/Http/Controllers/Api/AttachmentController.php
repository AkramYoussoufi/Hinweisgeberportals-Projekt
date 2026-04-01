<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    protected ReportService $reportService;

    protected array $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'video/mp4',
        'video/mpeg',
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
    ];

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function store(Request $request, string $referenceNumber)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    if (!in_array($value->getMimeType(), $this->allowedMimeTypes)) {
                        $fail('File type not allowed.');
                    }
                },
            ],
        ]);

        $report = Report::where('reference_number', $referenceNumber)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        $file             = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $storedFilename   = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $mimeType         = $file->getMimeType();
        $size             = $file->getSize();

        Storage::disk('local')->putFileAs(
            'attachments/' . $report->id,
            $file,
            $storedFilename
        );

        $attachment = Attachment::create([
            'report_id'         => $report->id,
            'original_filename' => $originalFilename,
            'stored_filename'   => $storedFilename,
            'mime_type'         => $mimeType,
            'size'              => $size,
        ]);

        $this->reportService->logAction(
            reportId: $report->id,
            actorId: $request->user()->id,
            action: 'attachment_uploaded',
            newValue: ['filename' => $originalFilename, 'size' => $size],
            ip: $request->ip()
        );

        return response()->json([
            'message'           => 'File uploaded successfully',
            'id'                => $attachment->id,
            'original_filename' => $attachment->original_filename,
            'mime_type'         => $attachment->mime_type,
            'size'              => $attachment->size,
        ], 201);
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

        $attachments = $report->attachments->map(function ($attachment) {
            return [
                'id'                => $attachment->id,
                'original_filename' => $attachment->original_filename,
                'mime_type'         => $attachment->mime_type,
                'size'              => $attachment->size,
                'created_at'        => $attachment->created_at,
            ];
        });

        return response()->json([
            'attachments' => $attachments,
        ], 200);
    }

    public function download(Request $request, string $attachmentId)
    {
        $attachment = Attachment::find($attachmentId);

        if (!$attachment) {
            return response()->json([
                'message' => 'Attachment not found',
            ], 404);
        }

        $report = $attachment->report;

        $isAdmin         = in_array($request->user()->role, ['admin', 'superadmin']);
        $isOwner         = $report->user_id === $request->user()->id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $path = 'attachments/' . $report->id . '/' . $attachment->stored_filename;

        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'message' => 'File not found',
            ], 404);
        }

        $isImage = str_starts_with($attachment->mime_type, 'image/');


        $fileContents = Storage::disk('local')->get($path);

        return response($fileContents, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => $isImage
                ? 'inline; filename="' . $attachment->original_filename . '"'
                : 'attachment; filename="' . $attachment->original_filename . '"',
        ]);
    }

    public function adminIndex(Request $request, string $referenceNumber)
    {
        $report = Report::where('reference_number', $referenceNumber)->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        $attachments = $report->attachments->map(function ($attachment) {
            return [
                'id'                => $attachment->id,
                'original_filename' => $attachment->original_filename,
                'mime_type'         => $attachment->mime_type,
                'size'              => $attachment->size,
                'created_at'        => $attachment->created_at,
            ];
        });

        return response()->json([
            'attachments' => $attachments,
        ], 200);
    }
}
