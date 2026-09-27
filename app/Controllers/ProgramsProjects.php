<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;

class ProgramsProjects extends BaseController
{
    public function index(): string
    {
        helper(['programs_projects', 'asset', 'landing']);

        // Determine which tab to show based on URI segment or default to 'programs'
        $uri = service('uri');
        $tab = $uri->getSegment(1) ?? 'programs';
        
        // Validate tab
        $validTabs = programs_projects_tabs();
        if (! in_array($tab, $validTabs, true)) {
            $tab = 'programs';
        }

        $hero     = programs_projects_hero_get($tab);
        $heroUrl  = programs_projects_media_url($hero['image']);
        $sections = programs_projects_sections_get($tab);

        // Prepare sections with rendered media URLs (matching ChildProGad approach)
        $sectionData = [];
        foreach ($sections as $section) {
            $mediaUrl = $section['media_url'];
            $displayUrl = $mediaUrl;

            // Only convert local upload paths to full URLs if not already an external URL
            if ($mediaUrl !== '' && ! preg_match('#^https?://#', $mediaUrl)) {
                $displayUrl = programs_projects_media_url($mediaUrl);
            }

            $sectionData[] = [
                'title'       => $section['title'],
                'description' => $section['description'],
                'media_type'  => $section['media_type'],
                'media_url'   => $mediaUrl,
                'display_url' => $displayUrl,
                'is_youtube'  => ($section['media_type'] === 'video' && programs_projects_is_youtube_url($mediaUrl)),
                'embed_url'   => ($section['media_type'] === 'video' && programs_projects_is_youtube_url($mediaUrl))
                    ? programs_projects_youtube_embed_url($mediaUrl)
                    : '',
            ];
        }

        return view('programs_projects', [
            'title'     => programs_projects_tab_label($tab) . ' — CSCS Tap n Track',
            'tab'       => $tab,
            'hero'      => $hero,
            'sections'  => $sectionData,
            'heroUrl'   => $heroUrl,
            'tabs'      => $validTabs,
        ]);
    }
}