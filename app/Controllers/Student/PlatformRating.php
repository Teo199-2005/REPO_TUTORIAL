<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\PlatformRatingModel;

class PlatformRating extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (! $this->auth->loggedIn() || ! $this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('login'));
        }

        helper(['platform_rating', 'school_year']);

        $model = new PlatformRatingModel();
        $row   = $model->findByUserId((int) $this->auth->id());

        return view('student/platform_rating', [
            'title'               => 'Rate platform - CSCS Tap n Track',
            'rating'              => $row,
            'school_year'         => get_current_school_year(),
            'term_label'          => platform_rating_term_label(get_current_term()),
            'ratingThankYou'      => (bool) session()->getFlashdata('rating_thank_you'),
            'ratingThankYouStars' => (int) session()->getFlashdata('rating_thank_you_stars'),
        ]);
    }

    public function save()
    {
        if (! $this->auth->loggedIn() || ! $this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('login'));
        }

        $rules = [
            'rating'  => 'required|integer|greater_than[0]|less_than[6]',
            'comment' => 'permit_empty|max_length[500]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $rating  = (int) $this->request->getPost('rating');
        $comment = $this->request->getPost('comment');

        $model = new PlatformRatingModel();
        $ok    = $model->saveOrUpdateForUser((int) $this->auth->id(), 'student', $rating, is_string($comment) ? $comment : null);

        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'Could not save your rating. Please try again.');
        }

        return redirect()->to(base_url('student/platform-rating'))
            ->with('rating_thank_you', true)
            ->with('rating_thank_you_stars', $rating);
    }
}

