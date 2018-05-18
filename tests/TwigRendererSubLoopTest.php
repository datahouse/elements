<?php

namespace Datahouse\Elements\Tests;

/**
 * Tests basic Twig capabilities as well as our custom extensions.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class TwigTemplateSubLoopTest extends TwigRendererCommon
{
    /**
     * Test a simple 'eleloop' tag.
     *
     * @return void
     */
    public function testLoop()
    {
        $content = $this->triggerRenderer('sub_loop_two_children.html', [
            'subs' => ['sub' => [
                [
                    'fields' => ['preview' => 'just the trailer'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'another twist'],
                    'element_id' => $this->genFakeId()
                ]
            ]]
        ]);

        $this->assertContains(
            '<li><div class="__ele_field-sub-0-preview">just the trailer</div></li>',
            $content
        );
        $this->assertContains(
            '<li><div class="__ele_field-sub-1-preview">another twist</div></li>',
            $content
        );
        $this->assertNotContains('<p>no element here</p>', $content);
    }

    /**
     * Test a nested eleloop tag with grouping.
     *
     * @return void
     */
    public function testLoopWithGrouping()
    {
        $content = $this->triggerRenderer('sub_loop_with_grouping.html', [
            'subs' => ['sub' => [
                [
                    'fields' => ['preview' => 'a'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'b'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'c'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'd'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'e'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'f'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'g'],
                    'element_id' => $this->genFakeId()
                ],
                [
                    'fields' => ['preview' => 'h'],
                    'element_id' => $this->genFakeId()
                ]
            ]]
        ]);
        $this->assertContains(
            '<ul><li>a</li><li>b</li><li>c</li></ul>',
            $content
        );
        $this->assertContains(
            '<ul><li>d</li><li>e</li><li>f</li></ul>',
            $content
        );
        $this->assertContains(
            '<ul><li>g</li><li>h</li></ul>',
            $content
        );
        $this->assertNotContains('<p>no element here</p>', $content);
    }
}
