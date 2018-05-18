<?php

namespace Datahouse\Elements\Tests;

/**
 * Tests basic Twig capabilities as well as our custom extensions.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class TwigTemplateRefLoopTest extends TwigRendererCommon
{
    /**
     * Test a simple 'eleloop' tag.
     *
     * @return void
     */
    public function testLoopTwoChildren()
    {
        $content = $this->triggerRenderer('ref_loop_two_children.html', [
            'refs' => ['myref' => [
                'element_id' => 'e2415cb7f63df0c9de23362326ad3c37a9adfc96',
                'children' => [
                    [
                        'element_id' => str_repeat('1', 40),
                        'fields' => ['preview' => 'just the trailer']
                    ],
                    [
                        'element_id' => str_repeat('2', 40),
                        'fields' => ['preview' => 'another twist']
                    ]
                ]
            ]]
        ]);

        $this->assertContains(
            '<li><div class="__ele_field-myref-0-preview">just the trailer</div></li>',
            $content
        );
        $this->assertContains(
            '<li><div class="__ele_field-myref-1-preview">another twist</div></li>',
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
        $content = $this->triggerRenderer('ref_loop_with_grouping.html', [
            'refs' => ['myref' => [
                'element_id' => 'e2415cb7f63df0c9de23362326ad3c37a9adfc96',
                'children' => [
                    [
                        'element_id' => str_repeat('1', 40),
                        'fields' => ['preview' => 'a']
                    ],
                    [
                        'element_id' => str_repeat('2', 40),
                        'fields' => ['preview' => 'b']
                    ],
                    [
                        'element_id' => str_repeat('3', 40),
                        'fields' => ['preview' => 'c']
                    ],
                    [
                        'element_id' => str_repeat('4', 40),
                        'fields' => ['preview' => 'd']
                    ],
                    [
                        'element_id' => str_repeat('5', 40),
                        'fields' => ['preview' => 'e']
                    ],
                    [
                        'element_id' => str_repeat('6', 40),
                        'fields' => ['preview' => 'f']
                    ],
                    [
                        'element_id' => str_repeat('7', 40),
                        'fields' => ['preview' => 'g']
                    ],
                    [
                        'element_id' => str_repeat('8', 40),
                        'fields' => ['preview' => 'h']
                    ]
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
