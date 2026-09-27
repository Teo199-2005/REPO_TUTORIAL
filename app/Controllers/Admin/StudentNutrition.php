<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\StudentNutritionClassifier;
use App\Models\SectionModel;
use App\Models\StudentModel;

class StudentNutrition extends BaseController
{
    public function index()
    {
        if (! auth()->user() || ! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        helper(['school_year', 'nutrition']);

        $sectionModel = new SectionModel();
        $sections = $sectionModel->orderBy('grade_level', 'ASC')->orderBy('section_name', 'ASC')->findAll();

        return view('admin/student_nutrition', [
            'title'          => 'Student nutrition / BMI - CSCS Tap n Track',
            'students'       => $this->getFilteredRows(),
            'sections'       => $sections,
            'filter_grade'   => $this->request->getGet('grade_level'),
            'filter_section' => $this->request->getGet('section_id'),
            'filter_status'  => $this->request->getGet('nutrition_status'),
            'filter_q'       => $this->request->getGet('q'),
            'schoolYear'     => get_current_school_year(),
            'filtersSummary' => $this->filtersSummary(),
        ]);
    }

    public function exportPdf()
    {
        if (! auth()->user() || ! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        helper(['school_year', 'nutrition']);

        $data = [
            'students'       => $this->getFilteredRows(),
            'reportDate'     => date('F j, Y'),
            'schoolYear'     => get_current_school_year(),
            'filtersSummary' => $this->filtersSummary(),
        ];

        $html = view('admin/student_nutrition_pdf', $data);

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $this->sendPdfInline($dompdf, 'CSCS_Student_Nutrition_' . date('Y-m-d') . '.pdf');
    }

    private function filtersSummary(): string
    {
        $parts = [];
        $g = $this->request->getGet('grade_level');
        if ($g !== null && $g !== '' && ctype_digit((string) $g)) {
            $parts[] = grade_level_label((int) $g);
        }
        $sid = $this->request->getGet('section_id');
        if ($sid !== null && $sid !== '') {
            $parts[] = 'Section #' . $sid;
        }
        $st = $this->request->getGet('nutrition_status');
        if ($st !== null && $st !== '') {
            $parts[] = $st === 'incomplete'
                ? 'Missing nutrition fields'
                : StudentNutritionClassifier::statusLabel((string) $st);
        }
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $parts[] = 'Search: ' . $q;
        }

        return $parts === [] ? 'All enrolled students' : implode(' | ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getFilteredRows(): array
    {
        $model = new StudentModel();
        $model->select('students.*, sections.section_name');
        $model->join('sections', 'sections.id = students.section_id', 'left');
        $model->where('students.enrollment_status', 'enrolled');

        $grade = $this->request->getGet('grade_level');
        if ($grade !== null && $grade !== '') {
            $model->where('students.grade_level', (int) $grade);
        }

        $sectionId = $this->request->getGet('section_id');
        if ($sectionId !== null && $sectionId !== '') {
            $model->where('students.section_id', (int) $sectionId);
        }

        $status = $this->request->getGet('nutrition_status');
        if ($status !== null && $status !== '') {
            if ($status === 'incomplete') {
                $model->groupStart()
                    ->where('students.height_cm IS NULL', null, false)
                    ->orWhere('students.weight_kg IS NULL', null, false)
                    ->orWhere('students.ethnicity IS NULL', null, false)
                    ->orWhere('students.ethnicity', '')
                    ->groupEnd();
            } else {
                $model->where('students.nutrition_status', $status);
            }
        }

        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $model->groupStart()
                ->like('students.first_name', $q)
                ->orLike('students.last_name', $q)
                ->orLike('students.lrn', $q)
                ->groupEnd();
        }

        $model->orderBy('students.grade_level', 'ASC')
            ->orderBy('sections.section_name', 'ASC')
            ->orderBy('students.last_name', 'ASC')
            ->orderBy('students.first_name', 'ASC');

        return $model->findAll();
    }
}
