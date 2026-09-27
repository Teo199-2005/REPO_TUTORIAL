<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;

/**
 * Stores whether the signed-in person wants Tappy on screen.
 *
 * The dock calls this whenever the hide/restore toggle is used. The reason this
 * is server side rather than a localStorage key is the whole point of the
 * feature: a browser-scoped flag is not a user preference. It would follow
 * whoever signs in next on a shared computer, and it would be gone on a second
 * device - so "I hid Tappy, then I logged out and back in" would not reliably
 * give you a Tappy-free page, which is exactly what someone pressing that button
 * is asking for.
 *
 * Deliberately unauthenticated requests get 401 rather than a silent success, and
 * an unknown 'hidden' value is rejected rather than coerced, so a stale client
 * cannot quietly write the wrong thing to somebody's account.
 */
class MascotVisibility extends BaseController
{
    public function update()
    {
        $auth = auth();

        if (! $auth->loggedIn()) {
            return $this->response
                ->setJSON(['success' => false, 'error' => 'Unauthorized'])
                ->setStatusCode(401);
        }

        if ($this->request->getMethod() !== 'POST') {
            return $this->response
                ->setJSON(['success' => false, 'error' => 'Invalid method'])
                ->setStatusCode(405);
        }

        $raw = $this->request->getPost('hidden');

        // Only the two real values are accepted. Treating anything unexpected as
        // "false" would mean a malformed request quietly put Tappy back on screen
        // for someone who had already dismissed him.
        if (! in_array((string) $raw, ['0', '1'], true)) {
            return $this->response
                ->setJSON(['success' => false, 'error' => 'hidden must be 0 or 1'])
                ->setStatusCode(422);
        }

        $hidden = (string) $raw === '1';
        $userId = (int) $auth->user()->id;

        // Written with the query builder rather than through the model's save(),
        // because UserModel declares validation rules for a full user record
        // (required email, unique check, password length) that a one-column
        // update would fail on. Only the id is used to build the WHERE clause.
        $updated = db_connect()
            ->table('users')
            ->where('id', $userId)
            ->update(['mascot_hidden' => $hidden ? 1 : 0]);

        if ($updated === false) {
            return $this->response
                ->setJSON(['success' => false, 'error' => 'Could not save preference'])
                ->setStatusCode(500);
        }

        // Keep the cached user in step with what was just written. Shield's
        // Session authenticator holds the User entity in a property and returns
        // that same object, so assigning to it updates the instance every later
        // auth()->user() call in this request will hand back. Without this,
        // mascot_user_hidden() would still report the old value for the rest of
        // the request and the next page would come back with Tappy on screen.
        $user = $auth->user();
        if ($user !== null) {
            $user->mascot_hidden = $hidden ? 1 : 0;
        }

        return $this->response->setJSON([
            'success' => true,
            'hidden'  => $hidden,
        ]);
    }
}