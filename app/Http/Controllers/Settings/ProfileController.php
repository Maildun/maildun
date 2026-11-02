<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Teams\ReleaseUserTeams;
use App\Concerns\InteractsWithPasswordConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use InteractsWithPasswordConfirmation;

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'hasPassword' => $request->user()->hasPassword(),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * A new email address needs proof of identity: password accounts type
     * their current password (ProfileUpdateRequest), and an account that
     * signed up with Google must have confirmed recently, which it does
     * through the connected Google account.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Decided before fill(): the request compares against the same User
        // instance, which holds the new address once filled.
        $emailChanged = $request->changesEmail();

        if ($emailChanged && ! $user->hasPassword() && ! $this->passwordRecentlyConfirmed($request)) {
            return $this->redirectToPasswordConfirmation(route('profile.edit'));
        }

        $validated = $request->validated();
        $oldAvatarPath = null;

        unset($validated['avatar'], $validated['current_password']);

        if (! $emailChanged && $request->normalizedEmailBelongsToAnotherAccount()) {
            // A legacy case-variant duplicate: lowercasing this row would
            // collide with the other account, so the stored address is kept.
            unset($validated['email']);
        }

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $oldAvatarPath = $user->getRawOriginal('avatar_path');
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        if ($oldAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     *
     * An account that signed up with Google has no password to re-enter, so
     * deleting it rests on a recent confirmation instead, made through the
     * connected Google account.
     */
    public function destroy(ProfileDeleteRequest $request, ReleaseUserTeams $releaseTeams): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasPassword() && ! $this->passwordRecentlyConfirmed($request)) {
            return $this->redirectToPasswordConfirmation(route('profile.edit'));
        }

        Auth::logout();

        DB::transaction(function () use ($user, $releaseTeams): void {
            $releaseTeams->handle($user);

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
