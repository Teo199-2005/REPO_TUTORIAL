<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\MaterialModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class Materials extends BaseController
{
    protected MaterialModel $materialModel;

    public function __construct()
    {
        $this->materialModel = new MaterialModel();
    }

    public function index()
    {
        helper(['materials', 'admin_access']);

        return view('admin/materials', [
            'title'      => 'School Materials — CSCS Tap n Track',
            'materials'  => $this->materialModel->getAdminMaterials(),
            'categories' => material_category_options(),
        ]);
    }

    public function upload()
    {
        helper(['materials', 'admin_access']);

        $title = trim((string) $this->request->getPost('title'));
        if ($title === '') {
            return redirect()->back()->with('error', 'Title is required.');
        }

        $file = $this->request->getFile('material');
        if ($file === null || $file->getName() === '') {
            return redirect()->back()->with('error', 'Please choose a file to upload.');
        }

        $validation = $this->validateUpload($file);
        if ($validation !== true) {
            return redirect()->back()->with('error', $validation);
        }

        $uploadPath = $this->uploadDir();
        $storedName = $file->getRandomName();

        try {
            $file->move($uploadPath, $storedName);
        } catch (\Throwable $e) {
            log_message('error', 'Admin material upload failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not save the file. Check folder permissions on writable/uploads/materials/.');
        }

        $category = $this->resolveCategoryInput(
            (string) $this->request->getPost('category'),
            (string) $this->request->getPost('custom_category'),
            'learning_material'
        );

        $description = trim((string) $this->request->getPost('description'));

        $this->materialModel->insert([
            'title'             => $title,
            'description'       => $description !== '' ? $description : null,
            'file_name'         => $file->getClientName(),
            'file_path'         => 'materials/' . $storedName,
            'file_size'         => $file->getSize(),
            // Do not call getExtension() after move(): CI may re-check the tmp file and fail.
            'file_type'         => strtolower((string) (pathinfo($storedName, PATHINFO_EXTENSION) ?: $file->getClientExtension() ?: '')),
            'category'          => $category,
            'uploaded_by'       => (int) auth()->id(),
            'uploaded_by_type'  => 'admin',
            'student_id'        => null,
            'is_public'         => 1,
            'show_on_website'   => $this->request->getPost('show_on_website') ? 1 : 0,
            // Automatic ordering now uses created_at (newest first).
            'sort_order'        => 0,
        ]);

        $materialId = $this->materialModel->getInsertID();

        audit_event('material.uploaded', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'material',
            'resource_id'   => (string) $materialId,
            'description'   => 'School material uploaded',
            'after'         => [
                'title'    => $title,
                'category' => $category,
                'file'     => $file->getClientName(),
            ],
        ]);

        return redirect()->back()->with('success', 'Material uploaded successfully.');
    }

    public function update($id)
    {
        helper(['materials', 'admin_access']);

        $material = $this->findAdminMaterial((int) $id);
        if ($material === null) {
            return redirect()->back()->with('error', 'Material not found.');
        }

        $title = trim((string) $this->request->getPost('title'));
        if ($title === '') {
            return redirect()->back()->with('error', 'Title is required.');
        }

        $category = $this->resolveCategoryInput(
            (string) $this->request->getPost('category'),
            (string) $this->request->getPost('custom_category'),
            (string) ($material->category ?? 'learning_material')
        );

        $this->materialModel->update($material->id, [
            'title'           => $title,
            'description'     => trim((string) $this->request->getPost('description')) ?: null,
            'category'        => $category,
            'show_on_website' => $this->request->getPost('show_on_website') ? 1 : 0,
        ]);

        audit_event('material.updated', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'material',
            'resource_id'   => (string) $material->id,
            'description'   => 'School material updated',
        ] + audit_diff(
            [
                'title'           => (string) ($material->title ?? ''),
                'category'        => (string) ($material->category ?? ''),
                'show_on_website' => (string) ($material->show_on_website ?? ''),
            ],
            [
                'title'           => (string) $title,
                'category'        => (string) $category,
                'show_on_website' => $this->request->getPost('show_on_website') ? '1' : '0',
            ],
            ['title', 'category', 'show_on_website']
        ));

        return redirect()->back()->with('success', 'Material updated successfully.');
    }

    public function delete($id)
    {
        helper(['admin_access']);

        $material = $this->findAdminMaterial((int) $id);
        if ($material === null) {
            return redirect()->back()->with('error', 'Material not found.');
        }

        $disk = material_file_disk_path($material);
        if ($disk !== null && is_file($disk)) {
            @unlink($disk);
        }

        $this->materialModel->delete($material->id);

        audit_event('material.deleted', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'material',
            'resource_id'   => (string) $material->id,
            'description'   => 'School material deleted',
            'before'        => ['title' => (string) ($material->title ?? '')],
        ]);

        return redirect()->back()->with('success', 'Material deleted successfully.');
    }

    public function download($id)
    {
        helper(['materials', 'admin_access']);

        $material = $this->findAdminMaterial((int) $id);
        if ($material === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $disk = material_file_disk_path($material);
        if ($disk === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('File not found');
        }

        return $this->response->download($disk, null)->setFileName($material->file_name ?: basename($disk));
    }

    private function findAdminMaterial(int $id): ?object
    {
        $material = $this->materialModel->find($id);
        if ($material === null || ($material->uploaded_by_type ?? '') !== 'admin') {
            return null;
        }

        return $material;
    }

    private function uploadDir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Cannot create materials upload directory.');
        }

        return $dir;
    }

    /**
     * @return true|string
     */
    private function validateUpload(UploadedFile $file)
    {
        if (! $file->isValid()) {
            $error = $file->getErrorString();

            return $error !== '' ? $error : 'Upload failed.';
        }

        if ($file->getSize() > 15 * 1024 * 1024) {
            return 'File must be 15MB or less.';
        }

        $ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'zip'];
        if (! in_array($ext, $allowed, true)) {
            return 'Allowed types: PDF, Word, Excel, PowerPoint, images, or ZIP.';
        }

        return true;
    }

    private function resolveCategoryInput(string $postedCategory, string $customCategory, string $fallback): string
    {
        $postedCategory = trim($postedCategory);
        $customCategory = trim($customCategory);
        $fallback       = trim($fallback) !== '' ? trim($fallback) : 'learning_material';
        $options        = material_category_options();

        // If user typed a custom category, prioritize it even when select value
        // is lost/missing due browser autofill, JS state, or stale modal state.
        if ($customCategory !== '') {
            return mb_substr($customCategory, 0, 50);
        }

        if ($postedCategory === 'other') {
            return 'other';
        }

        if ($postedCategory !== '' && isset($options[$postedCategory])) {
            return $postedCategory;
        }

        return $fallback;
    }
}
