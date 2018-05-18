<?php

namespace Datahouse\Elements\Tests;

/**
 * Tests basic Twig capabilities as well as our custom extensions.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class TwigTemplateFieldsTest extends TwigRendererCommon
{
    /**
     * A trivial template test.
     *
     * @throws \Exception
     * @return void
     */
    public function testTrivialTwigTemplate()
    {
        $content = $this->triggerRenderer('trivial.html', []);
        $this->assertRegExp('/html/', $content);
    }

    /**
     * Test the 'ele-field' token.
     *
     * @return void
     */
    public function testFieldToken()
    {
        $content = $this->triggerRenderer('field.html', [
            'fields' => ['title' => 'Yay']
        ]);
        $exp = '<h1><div class="__ele_field-title">Yay</div></h1>';
        $this->assertContains($exp, $content);
    }

    /**
     * Test the same 'ele-field' token with a specific class and tag.
     *
     * @return void
     */
    public function testFieldTokenDetail()
    {
        $content = $this->triggerRenderer('field_detail.html', [
            'fields' => ['title' => 'Yay, Customization!']
        ]);
        $exp = '<p class="__ele_field-title alpha beta">Yay, Customization!</p>';
        $this->assertContains($exp, $content);
    }

    /**
     * Test a simple 'eleref' tag with a reference to an existing element
     * (usual case).
     *
     * @return void
     */
    public function testRefSimple()
    {
        $fakeId = $this->genFakeId();
        $content = $this->triggerRenderer('ref.html', [
            'refs' => [
                'myref' => [
                    'children' => ['element_id' => $fakeId],
                    'selected' => $fakeId,
                    'selectable' => true
                ]
            ]
        ]);
        $this->assertContains('<p>a reference</p>', $content);
    }

    /**
     * Test an 'eleref' tag with an else block, but that one should not be
     * emitted as we pass a referenced element. However, this also tests the
     * elefield reading from a referenced element.
     *
     * @return void
     */
    public function testRefWithElse()
    {
        $fakeId = $this->genFakeId();
        $content = $this->triggerRenderer('ref_with_else.html', [
            'refs' => [
                'myref' => [
                    'children' => [
                        [
                            'element_id' => $fakeId,
                            'fields' => ['hello' => 'Howdy!'],
                        ]
                    ],
                    'selected' => $fakeId,
                    'selectable' => true
                ]
            ]
        ]);
        $exp = 'referenced element says: '
            . '<div class="__ele_field-myref-hello">Howdy!</div>';
        $this->assertContains($exp, $content);
        $this->assertNotContains('no element here', $content);
    }

    /**
     * Test an 'eleref' tag on a missing reference, which should display the
     * else block.
     *
     * @return void
     */
    public function testRefMissing()
    {
        $content = $this->triggerRenderer('ref_with_else.html', [
            'refs' => [
                'myref' => [
                    'children' => [],
                    'selected' => null,
                    'selectable' => true
                ]
            ]
        ]);
        $this->assertNotContains('referenced element says', $content);
        $this->assertContains('<p>no element here</p>', $content);
    }

    /**
     * Use an 'eleref' tag and then test twig context. The fields of the
     * referenced element should be used.
     *
     * @return void
     */
    public function testRefWithTwigField()
    {
        $fakeId = $this->genFakeId();
        $content = $this->triggerRenderer('ref_with_twig_field.html', [
            'refs' => [
                'myref' => [
                    'children' => [
                        [
                            'element_id' => $fakeId,
                            'fields' => ['hello' => 'Howdy!']
                        ]
                    ],
                    'selected' => $fakeId,
                    'selectable' => true
                ]
            ],
            'fields' => [
                'hello' => 'Good bye!'
            ]
        ]);
        $this->assertContains(
            'referenced element says: Howdy!',
            $content
        );
        $this->assertContains(
            'parent element says: Good bye!',
            $content
        );
        $this->assertNotContains('no element here', $content);
    }

    /**
     * Use an 'eleref' tag and then test twig context. The fields of the
     * referenced element should be used.
     *
     * @return void
     */
    public function testRefWithPermissionCheck()
    {
        $testFn = function (
            bool $hasAdmin,
            bool $selectable,
            string $expOutput,
            bool $expElseBlock
        ) {
            $fakeId = $this->genFakeId();
            $content = $this->triggerRenderer('ref_with_permission_check.html', [
                'permissions' => ['admin' => $hasAdmin],
                'refs' => [
                    'myref' => [
                        'children' => [
                            ['element_id' => $fakeId],
                        ],
                        'selected' => $fakeId,
                        'selectable' => $selectable
                    ],
                ],
                'element_id' => '2222222222222222222222222222222222222222'
            ]);
            $this->assertContains("parent-pre-ref: $expOutput", $content);
            $this->assertContains("within-reference: $expOutput", $content);
            $this->assertContains("parent-post-ref: $expOutput", $content);
            if ($expElseBlock) {
                $this->assertContains('no element here', $content);
            } else {
                $this->assertNotContains('no element here', $content);
            }
        };

        $testFn(false, true, 'no', false);
        $testFn(true, true, 'yes', true);

        $testFn(false, false, 'no', false);
        $testFn(true, false, 'yes', false);
    }

    /**
     * Same as above, but with the parent element lacking fields.
     *
     * @return void
     */
    public function testRefWithTwigParentFieldsMissing()
    {
        $fakeId = $this->genFakeId();
        $content = $this->triggerRenderer('ref_with_twig_field.html', [
            'refs' => [
                'myref' => [
                    'children' => [
                        [
                            'element_id' => $fakeId,
                            'fields' => ['hello' => 'Howdy!']
                        ]
                    ],
                    'selected' => $fakeId,
                    'selectable' => true
                ]
            ]
        ]);
        $this->assertContains(
            'referenced element says: Howdy!',
            $content
        );
        $this->assertContains(
            'parent element says:',
            $content
        );
        $this->assertNotContains('no element here', $content);
    }

    /**
     * Same as above, but with the child element lacking fields.
     *
     * @return void
     */
    public function testRefWithTwigChildFieldsMissing()
    {
        $fakeId = $this->genFakeId();
        $content = $this->triggerRenderer('ref_with_twig_field.html', [
            'refs' => [
                'myref' => [
                    'children' => [
                        [
                            'element_id' => $fakeId,
                            'fields' => ['hello' => 'Howdy!']
                        ]
                    ],
                    'selected' => $fakeId,
                    'selectable' => true
                ]
            ],
            'fields' => [
                'hello' => 'Good bye!'
            ]
        ]);
        $this->assertContains(
            'referenced element says:',
            $content
        );
        $this->assertContains(
            'parent element says: Good bye!',
            $content
        );
        $this->assertNotContains('no element here', $content);
    }
}
