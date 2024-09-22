<?php

namespace App\Http\Controllers\Api\File;

use App\Http\Controllers\Api\Dashboard\TicketController;
use App\Http\Controllers\Controller;
use App\Http\Requests\File\StoreFileRequest;
use App\Http\Requests\File\StoreImageRequest;
use App\Http\Resources\File\FileResource;
use App\Models\File;
use App\Models\User;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Storage;
use Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['download']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreImageRequest  $request
     * @return JsonResponse
     */


    // public function store(StoreImageRequest $request): JsonResponse
    // {
    //     $request->validated();
    //     if ($request->file('file')) {
    //         $image = $request->file('file');
    //         $file = new File();
    //         $file->uuid = Str::uuid();
    //         $file->name = $image->getClientOriginalName();
    //         $file->size = $image->getSize();
    //         $file->mime = $image->getMimeType();
    //         $file->extension = Str::lower($image->getClientOriginalExtension());
    //         $file->disk = 'public';
    //         $file->path = date('Y').'/'.date('m');
    //         $file->server_name = md5($image->getRealPath()).'.'.$file->extension;
    //         $file->user_id = Auth::id();
    //         if ($image->storeAs($file->path, $file->server_name, $file->disk) && $file->save()) {
    //             return response()->json(new FileResource($file));
    //         }
    //     }
    //     return response()->json(['message' => __('An error occurred while saving data')], 500);
    // }
    
    public function store(StoreImageRequest $request): JsonResponse
    {
        // Validate the uploaded file
        $request->validated();

        if ($request->file('file')) {
            // Store the file in the public disk, under a dynamic date-based directory
            $filePath = $request->file('file')->store('public/' . date('Y') . '/' . date('m'));

            // Create a new File instance to store metadata in the database
            $file = new File();
            $file->uuid = Str::uuid();
            $file->name = $request->file('file')->getClientOriginalName(); // Get the original file name
            $file->size = $request->file('file')->getSize(); // Get file size
            $file->mime = $request->file('file')->getMimeType(); // Get MIME type
            $file->extension = Str::lower($request->file('file')->getClientOriginalExtension()); // Get file extension
            $file->disk = 'public'; // Specify the disk
            $file->path = 'public/' . date('Y') . '/' . date('m'); // Store file path
            $file->server_name = md5($request->file('file')->getRealPath()) . '.' . $file->extension; // Generate a unique server name
            $file->user_id = Auth::id(); // Store the user who uploaded the file

            // Move the file to the correct location and save its metadata in the database
            if (Storage::disk($file->disk)->put($file->path . '/' . $file->server_name, file_get_contents($request->file('file')->getRealPath())) && $file->save()) {
                return response()->json(new FileResource($file));
            }
        }

        // Return error if the file was not saved
        return response()->json(['message' => __('An error occurred while saving data')], 500);
    }


    public function uploadAttachment(StoreFileRequest $request): JsonResponse
    {
        $request->validated();
        $attachment = $request->file('file');
        $file = new File();
        $file->uuid = Str::uuid();
        $file->name = $attachment->getClientOriginalName();
        $file->size = $attachment->getSize();
        $file->mime = $attachment->getMimeType();
        $file->extension = Str::lower($attachment->getClientOriginalExtension());
        $file->disk = 'private';
        $file->path = 'tickets/'.date('Y').'/'.date('m');
        $file->server_name = md5($attachment->getRealPath());
        $file->user_id = Auth::id();
        if ($attachment->storeAs($file->path, $file->server_name, $file->disk) && $file->save()) {
            return response()->json(new FileResource($file));
        }
        return response()->json(['message' => __('An error occurred while saving data')], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param  File  $file
     * @return JsonResponse
     */
    public function show(File $file): JsonResponse
    {
        return response()->json(new FileResource($file));
    }

    /**
     * @param  string  $uuid
     * @param  Request  $request
     * @return StreamedResponse
     * @throws Exception
     */
    public function download(string $uuid, Request $request): StreamedResponse
    {
        $file = File::where('uuid', $uuid)->firstOrFail();
        if ($file->path !== 'public') {
            /** @var PersonalAccessToken $model */
            $model = Sanctum::$personalAccessTokenModel;
            $accessToken = $model::findToken($request->get('token'));
            if (!$accessToken) {
                abort(404);
            }
            $user = User::findOrFail($accessToken->tokenable_id);
            $ticketReply = $file->ticketReply();
            if ($ticketReply) {
                $ticket = $ticketReply->ticket;
                if (!(
                    $ticket->user_id === $file->user_id ||
                    $ticket->agent_id === $file->user_id ||
                    $ticket->closed_by === $file->user_id ||
                    $ticket->user_id === $user->id ||
                    $ticket->agent_id === $user->id ||
                    $ticket->closed_by === $user->id ||
                    $user->userRole->checkPermission(str_replace('\\', '.', TicketController::class))
                )) {
                    abort(404);
                }
            }
        }
        if (!Storage::disk($file->disk)->exists($file->path.DIRECTORY_SEPARATOR.$file->server_name)) {
            abort(404);
        }
        return Storage::disk($file->disk)->download($file->path.DIRECTORY_SEPARATOR.$file->server_name, $file->name);
    }
}
