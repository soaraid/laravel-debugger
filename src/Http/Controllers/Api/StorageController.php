<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Storage\StorageTester;
use Soara\Debugger\Support\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StorageController extends Controller
{
    public function __construct(protected StorageTester $storage) {}

    public function index(): JsonResponse
    {
        $files = [];
        $error = null;

        try {
            $files = array_map(fn (array $f): array => [
                'name' => $f['name'],
                'size' => Format::bytes($f['size']),
                'uploaded' => Format::time($f['modified']),
                'url' => route('debugger.storage.show', ['name' => $f['name']]),
            ], $this->storage->files());
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $free = $this->storage->freeSpace();

        return response()->json([
            'files' => $files,
            'error' => $error,
            'disk' => $this->storage->diskName(),
            'folder' => $this->storage->folder(),
            'max_mb' => round($this->storage->maxKilobytes() / 1024),
            'free' => $free !== null ? Format::bytes($free) : null,
        ]);
    }

    /**
     * Write, read and delete a probe file on each disk. Its own endpoint
     * (POST) because it writes to the disks; opening the page never does.
     */
    public function check(): JsonResponse
    {
        return response()->json([
            'probes' => array_map(fn (array $p): array => [
                'disk' => $p['disk'],
                'ok' => $p['ok'],
                'time' => Format::milliseconds($p['ms']),
                'error' => $p['error'],
            ], $this->storage->probe()),
        ]);
    }

    public function upload(Request $request, AuditLog $audit): JsonResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'max:'.$this->storage->maxKilobytes()]],
            ['file.max' => 'Choose a file under '.round($this->storage->maxKilobytes() / 1024).' MB.', 'file.required' => 'Choose a file to upload.'],
        );

        try {
            $name = $this->storage->upload($request->file('file'));
            $audit->record('storage.upload', $name);

            return $this->notice("Uploaded {$name} to the {$this->storage->diskName()} disk.");
        } catch (Throwable $e) {
            return $this->notice("Couldn't upload the file: {$e->getMessage()}", 'danger');
        }
    }

    public function show(Request $request): StreamedResponse
    {
        try {
            return $this->storage->response((string) $request->query('name'));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    public function destroy(Request $request, AuditLog $audit): JsonResponse
    {
        $name = (string) $request->input('name');

        try {
            $this->storage->delete($name);
            $audit->record('storage.delete', $name);

            return $this->notice("Deleted {$name}.");
        } catch (Throwable $e) {
            return $this->notice("Couldn't delete {$name}: {$e->getMessage()}", 'danger');
        }
    }
}
