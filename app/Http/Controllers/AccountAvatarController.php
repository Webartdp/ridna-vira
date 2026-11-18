<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountAvatarController extends Controller
{
    public function show(Request $request)
    {
        $path = $request->user()->avatar_path;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, headers: ['X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb'), 'dimensions:max_width=3000,max_height=3000'],
        ]);

        $path = $data['avatar']->store('avatars', 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['avatar' => 'Не вдалося зберегти фото. Спробуйте ще раз.']);
        }

        try {
            $this->replaceAvatar($request->user(), $path);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);

            throw $error;
        }

        return redirect()->route('account.profile')->with('status', 'Аватарку збережено.');
    }

    public function destroy(Request $request)
    {
        $this->replaceAvatar($request->user(), null);

        return redirect()->route('account.profile')->with('status', 'Аватарку видалено.');
    }

    private function replaceAvatar(User $user, ?string $path): void
    {
        DB::transaction(function () use ($user, $path) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            $previous = $locked->avatar_path;
            $locked->forceFill(['avatar_path' => $path])->save();
            if ($previous) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($previous));
            }
        }, 3);
    }
}
