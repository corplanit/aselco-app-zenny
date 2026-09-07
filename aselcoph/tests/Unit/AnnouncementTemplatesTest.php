<?php

namespace Tests\Unit;

use App\Support\AnnouncementTemplates;
use PHPUnit\Framework\TestCase;

class AnnouncementTemplatesTest extends TestCase
{
    public function test_templates_are_ready_to_select(): void
    {
        $templates = AnnouncementTemplates::all();

        $this->assertNotEmpty($templates);

        $ids = [];
        foreach ($templates as $template) {
            $this->assertNotEmpty($template['id']);
            $this->assertNotEmpty($template['label']);
            $this->assertNotEmpty($template['title']);
            $this->assertNotEmpty($template['body']);
            $this->assertContains($template['category'], ['alert', 'service', 'billing']);
            $this->assertLessThanOrEqual(180, mb_strlen($template['title']));
            $this->assertLessThanOrEqual(5000, mb_strlen($template['body']));
            $ids[] = $template['id'];
        }

        $this->assertCount(count($templates), array_unique($ids));
    }
}
