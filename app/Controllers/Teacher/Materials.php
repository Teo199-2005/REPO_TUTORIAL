<?php
namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\MaterialModel;

class Materials extends BaseController
{
    protected $auth;
    protected $materialModel;

    public function __construct()
    {
        $this->auth = auth();
        $this->materialModel = new MaterialModel();
    }

    public function index()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        // Teachers have read-only access: school materials shared by the
        // administration plus anything the teacher uploaded themselves.
        $adminMaterials = $this->materialModel->getAdminMaterials();

        $ownMaterials = $this->materialModel->where('uploaded_by', $this->auth->user()->id)
                                            ->where('uploaded_by_type', 'teacher')
                                            ->orderBy('created_at', 'DESC')
                                            ->findAll();

        $materials = array_merge($adminMaterials, $ownMaterials);
        usort($materials, function ($a, $b) {
            return strtotime($b->created_at ?? 'now') <=> strtotime($a->created_at ?? 'now');
        });

        return view('teacher/materials', [
            'title' => 'Learning Materials - CSCS Tap n Track',
            'materials' => $materials
        ]);
    }

    public function upload()
    {
        // Teachers have read-only access to materials.
        return redirect()->back()->with('error', 'Teachers can only view and download materials. Uploading is managed by the school administration.');
    }

    public function delete($id)
    {
        // Teachers have read-only access to materials.
        return redirect()->back()->with('error', 'Teachers can only view and download materials. Deleting is managed by the school administration.');
    }

    public function download($id)
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $material = $this->materialModel->find($id);
        if (!$material) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Material not found');
        }

        $filePath = WRITEPATH . 'uploads/' . $material->file_path;
        if (!file_exists($filePath)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('File not found');
        }

        return $this->response->download($filePath, null);
    }
}
