<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileUploadRequest;
use App\Models\VaultFile;
use App\Support\FileCrypto;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

class FileController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Files/Index', [
            'files' => $request->user()->files()->latest()->get()->map(fn (VaultFile $f) => [
                'id' => $f->id, 'name' => $f->original_name, 'size' => $f->size, 'createdAt' => $f->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function store(FileUploadRequest $request)
    {
        $upload = $request->file('file');
        $plaintext = $upload->get();
        $storedName = (string) Str::uuid();

        // Encrypted before it touches the disk; the name on disk is random, never the client's name.
        Storage::disk('local')->put(self::path($storedName), FileCrypto::fromConfig()->encrypt($plaintext, $storedName));

        $file = new VaultFile;
        $file->user_id = $request->user()->id;
        $file->original_name = $upload->getClientOriginalName();
        $file->stored_name = $storedName;
        $file->mime = $upload->getMimeType();
        $file->size = strlen($plaintext);
        $file->sha256 = hash('sha256', $plaintext);
        $file->save();

        SecurityLog::event('data.file.uploaded', SecurityLog::DATA, ['file_id' => $file->id, 'size' => $file->size, 'mime' => $file->mime]);

        return redirect()->route('files.index')->with('status', 'File stored encrypted.');
    }

    public function download(VaultFile $file)
    {
        Gate::authorize('access', $file);

        $blob = Storage::disk('local')->get(self::path($file->stored_name));
        try {
            $plaintext = $blob === null ? null : FileCrypto::fromConfig()->decrypt($blob, $file->stored_name);
        } catch (RuntimeException) {
            // Tampered, truncated or wrong key: fail closed, never return partial or unverified data.
            $plaintext = null;
        }
        if ($plaintext === null || ! hash_equals($file->sha256, hash('sha256', $plaintext))) {
            SecurityLog::event('crypto.file.integrity_failed', SecurityLog::CRYPTO, ['outcome' => 'failure', 'file_id' => $file->id], 'error');
            abort(500);
        }

        SecurityLog::event('data.file.downloaded', SecurityLog::DATA, ['file_id' => $file->id]);

        // Always a download (never rendered inline), so an uploaded file cannot run as HTML/JS in our origin.
        return response()->streamDownload(fn () => print ($plaintext), $file->original_name, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function destroy(VaultFile $file)
    {
        Gate::authorize('access', $file);
        Storage::disk('local')->delete(self::path($file->stored_name));
        $file->delete();
        SecurityLog::event('data.file.deleted', SecurityLog::DATA, ['file_id' => $file->id]);

        return redirect()->route('files.index')->with('status', 'File deleted.');
    }

    private static function path(string $storedName): string
    {
        return 'vault/'.$storedName.'.bin';
    }
}
