<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\File;
use App\Models\FileAccess;
use App\Models\User;

class FileController extends Controller
{
    public function uploadFiles(Request $request)
    {
        $allowedExtensions = ['doc', 'pdf', 'docx', 'zip', 'jpeg', 'jpg', 'png'];

        $validator = Validator::make($request->all(), [
            'files' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = $request->file('files');
        $extension = $file->getClientOriginalExtension();
        $fileID = uniqid();
        $fileName = $fileID. '.' .$file->getClientOriginalExtension();

        if (!in_array(strtolower($extension), $allowedExtensions)) {
            return response()->json([
                'success' => false,
                'message' => "File not loaded",
                'name' => $file->getClientOriginalName()
            ]);
        };

        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'File size exceeds 2 MB',
                'name' => $fileName,
            ]);
        }
    
        Storage::disk('local')->put($fileName, file_get_contents($file));

        File::create([
            'name' => $file->getClientOriginalName(),
            'file_path' => url('files/' . $fileName),
            'file_id' => $fileID,
            'user_id' => auth()->id(),
        ]);

        FileAccess::create([
            'file_id' => $fileID,
            'user_id' => auth()->id(),
            'type' => 'author'
        ]);

        return [
            'success' => true,
            'message' => 'Success',
            'name' => $file->getClientOriginalName(),
            'url' => url('files/' . $fileName),
            'file_id' => $fileID,
        ];
    }

    public function rename(Request $request, $file_id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = File::where('file_id', $file_id)->first();

        if (!$file) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($file->user_id !== auth()->id()) { 
            return response()->json(['error' => 'Forbidden for you'], 403);
        }

        $file->name = $request->input('name');
        $file->save();

        return response()->json([
            'success' => true, 
            'message' => 'Renamed'
        ]);
    }

    public function delete(Request $request, $file_id)
    {
        $file = File::where('file_id', $file_id)->first();
        $fileAccess = FileAccess::where('file_id', $file_id);

        if (!$file) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($file->user_id !== auth()->id()) { 
            return response()->json(['error' => 'Forbidden for you'], 403);
        }

        Storage::disk('local')->delete(
            $this->expolodeURL($file->file_path)
        );

        $fileAccess->delete();

        $file->delete();

        return response()->json([
            'success' => true, 
            'message' => 'File already deleted'
        ]);
    }

    public function download(Request $request, $file_id)
    {
        $file = File::where('file_id', $file_id)->first();

        if (!$file) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $filePath = $this->expolodeURL($file->file_path);

        return response()->download(storage_path("app/{$filePath}"));
    }

    public function addAccess(Request $request, $file_id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = File::where('file_id', $file_id)->first();

        if (!$file) {
            return response()->json(['error' => 'Not found'], 404);
        }
    
        if ($file->user_id !== auth()->id()) { 
            return response()->json(['error' => 'Forbidden for you'], 403);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $access = FileAccess::where('file_id', $file->file_id)->where('user_id', $user->id)->first();

        if (!$access) {
            $access = new FileAccess;
            $access->file_id = $file->file_id;
            $access->user_id = $user->id;
            $access->save();
        }

        return response()->json(FileAccess::where('file_id', $file->file_id)->get());
    }


    public function deleteAccess(Request $request, $file_id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = File::where('file_id', $file_id)->first();

        if ($file->user_id !== auth()->id()) { 
            return response()->json(['error' => 'Forbidden for you'], 403);
        }

        $authUser = User::where('id', auth()->id())->first();

        // Попытка удаления самого себя
        if ($request->input('email') === $authUser->email) {
            return response()->json(['error' => 'Запрещено удаление самого себя'], 403);
        }

        $userAcc = User::where('email', $request->input('email'))->first();


        $access = FileAccess::where('file_id', $file->file_id)->where('user_id', $userAcc->id )->first();

        if (!$access) {
            return response()->json(['error' => 'Пользователь не найден в списке соавторов'], 404);
        }

        $access->delete();

        $usersWithAccess = FileAccess::where('file_id', $file->file_id)->get();

        return response()->json($usersWithAccess);
    }

    private function expolodeURL($url)
    { 
        $parts = explode('/', $url);
        return end($parts);
    }
}
