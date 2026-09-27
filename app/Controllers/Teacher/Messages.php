<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;

class Messages extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (! $this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        return view('teacher/messages', [
            'title' => 'Messages - CSCS Tap n Track',
        ]);
    }
}





