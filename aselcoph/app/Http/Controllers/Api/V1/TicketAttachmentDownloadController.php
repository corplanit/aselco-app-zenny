<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TicketService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentDownloadController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
    }

    public function __invoke(Request $request, int $attachment): StreamedResponse
    {
        $download = $this->tickets->downloadAttachment($request->user(), $attachment);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($download['disk']);

        return $disk->download($download['path'], $download['name'], [
            'Content-Type' => $download['mime'],
        ]);
    }
}
