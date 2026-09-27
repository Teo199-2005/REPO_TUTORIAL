<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\NotificationModel;

class Notifications extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    /**
     * The student portal nav links here, so this must render an actual page.
     * Mirrors Teacher\Notifications: newest first, scoped to the signed-in user.
     * (Read-only: unlike the teacher portal there is no mark-as-read route for
     * students, so no action column is offered here.)
     */
    public function index()
    {
        if (! $this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'));
        }

        $notificationModel = new NotificationModel();

        $notifications = $notificationModel
            ->where('user_id', $this->auth->user()->id)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return view('student/notifications', [
            'title'         => 'Notifications - CSCS Tap n Track',
            'notifications' => $notifications,
        ]);
    }
}





