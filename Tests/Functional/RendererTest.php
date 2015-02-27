<?php

namespace Oro\Bundle\LayoutBundle\Tests\Functional;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

use Oro\Component\ConfigExpression\Condition;
use Oro\Component\Layout\ContextInterface;
use Oro\Component\Layout\Layout;
use Oro\Component\Layout\LayoutContext;
use Oro\Component\Layout\LayoutManager;

use Oro\Bundle\LayoutBundle\Layout\Form\FormAccessor;
use Oro\Bundle\LayoutBundle\Tests\Fixtures\UserNameType;

class RendererTest extends LayoutTestCase
{
    protected function setUp()
    {
        $this->initClient();
    }

    public function testHtmlRenderingForCoreBlocksByTwigRenderer()
    {
        if (!$this->getContainer()->hasParameter('oro_layout.twig.resources')) {
            $this->markTestSkipped('TWIG renderer is not enabled.');
        }

        $context = new LayoutContext();
        $context->getDataResolver()->setOptional(['form']);
        $form = $this->getTestForm();
        $context->set('form', new FormAccessor($form));

        // revert TWIG form renderer to Symfony's default theme
        $this->getContainer()->get('twig.form.renderer')->setTheme(
            $context->get('form')->getView(),
            'form_div_layout.html.twig'
        );

        $result   = $this->getCoreBlocksTestLayout($context)->setRenderer('twig')->render();
        $expected = $this->getCoreBlocksTestLayoutResult(
            $this->getTwigFormLayoutResult()
        );

        $this->assertHtmlEquals($expected, $result);
    }

    public function testHtmlRenderingForCoreBlocksByPhpRenderer()
    {
        if (!$this->getContainer()->hasParameter('oro_layout.php.resources')) {
            $this->markTestSkipped('PHP renderer is not enabled.');
        }

        $context = new LayoutContext();
        $context->getDataResolver()->setOptional(['form']);
        $form = $this->getTestForm();
        $context->set('form', new FormAccessor($form));

        $result   = $this->getCoreBlocksTestLayout($context)->setRenderer('php')->render();
        $expected = $this->getCoreBlocksTestLayoutResult(
            $this->getPhpFormLayoutResult()
        );

        $this->assertHtmlEquals($expected, $result);
    }

    /**
     * @param ContextInterface $context
     *
     * @return Layout
     */
    protected function getCoreBlocksTestLayout(ContextInterface $context)
    {
        /** @var LayoutManager $layoutManager */
        $layoutManager = $this->getContainer()->get('oro_layout.layout_manager');

        $layout = $layoutManager->getLayoutBuilder()
            ->add('root', null, 'root')
            ->add('head', 'root', 'head', ['title' => 'Test'])
            ->add('meta', 'head', 'meta', ['charset' => 'UTF-8'])
            ->add('style', 'head', 'style', ['content' => 'body { color: red; }'])
            ->add('script', 'head', 'script', ['content' => 'alert(\'test\');'])
            ->add('content', 'root', 'body')
            ->add('title', 'content', 'text', ['text' => 'Hi %val%!', 'text_parameters' => ['%val%' => 'World']])
            ->add(
                'form',
                'content',
                'form',
                [
                    'preferred_fields' => ['jobTitle', 'user.lastName'],
                    'groups'           => [
                        'general'    => [
                            'title'  => 'General Info',
                            'fields' => ['user.firstName', 'user.lastName']
                        ],
                        'additional' => [
                            'title'   => 'Additional Info',
                            'default' => true
                        ]
                    ]
                ]
            )
            // swap 'general' and 'additional' groups to check that a layout update
            // can be applied for items added by a block type
            ->move('form:group_general', null, 'form:group_additional')
            // test 'visible' option
            ->add('invisible_container', 'root', 'head', ['visible' => false])
            ->add('invisible_child', 'invisible_container', 'meta', ['charset' => 'invisible'])
            // test 'visible' option when its value is an expression
            ->add(
                'invisible_by_expr_raw_container',
                'root',
                'head',
                ['visible' => ['@false' => null]]
            )
            ->add(
                'invisible_by_expr_raw_child',
                'invisible_by_expr_raw_container',
                'meta',
                ['charset' => 'invisible_by_expr_raw']
            )
            // test 'visible' option when its value is already assembled expression
            ->add('invisible_by_expr_container', 'root', 'head', ['visible' => new Condition\False()])
            ->add('invisible_by_expr_child', 'invisible_by_expr_container', 'meta', ['charset' => 'invisible_by_expr'])
            ->getLayout($context);

        return $layout;
    }

    /**
     * @return FormInterface
     */
    protected function getTestForm()
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = $this->getContainer()->get('form.factory');

        $form = $formFactory->createNamedBuilder('form_for_layout_renderer_test')
            ->add('user', new UserNameType())
            ->add('jobTitle', 'text', ['label' => 'Job Title', 'required' => false])
            ->add(
                'gender',
                'choice',
                [
                    'label'    => 'Gender',
                    'required' => false,
                    'choices'  => ['male' => 'Male', 'female' => 'Female'],
                    'expanded' => true
                ]
            )
            ->getForm();

        return $form;
    }

    /**
     * @param string $formLayout
     *
     * @return string
     */
    protected function getCoreBlocksTestLayoutResult($formLayout)
    {
        $expected = <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <title>Test</title>
        <meta charset="UTF-8"/>
        <style type="text/css">
            body { color: red; }
        </style>
        <script type="text/javascript">
            alert('test');
        </script>
    </head>
<body>
    {form_layout}
</body>
</html>
HTML;
        $expected = str_replace('{form_layout}', $formLayout, $expected);

        return $expected;
    }

    /**
     * @return string
     */
    protected function getTwigFormLayoutResult()
    {
        $expected = <<<HTML
Hi World!    <fieldset>
    <legend>Additional Info</legend>
    <div>
        <label for="form_for_layout_renderer_test_jobTitle">Job Title</label>
        <input type="text"
            id="form_for_layout_renderer_test_jobTitle"
            name="form_for_layout_renderer_test[jobTitle]"/>
    </div>
    <div>
        <label>Gender</label>
        <div id="form_for_layout_renderer_test_gender">
            <input type="radio"
                id="form_for_layout_renderer_test_gender_placeholder"
                name="form_for_layout_renderer_test[gender]"
                value="" checked="checked"/>
            <label for="form_for_layout_renderer_test_gender_placeholder">None</label>
            <input type="radio"
                id="form_for_layout_renderer_test_gender_0"
                name="form_for_layout_renderer_test[gender]"
                value="male"/>
            <label for="form_for_layout_renderer_test_gender_0">Male</label>
            <input type="radio"
                id="form_for_layout_renderer_test_gender_1"
                name="form_for_layout_renderer_test[gender]"
                value="female"/>
            <label for="form_for_layout_renderer_test_gender_1">Female</label>
        </div>
    </div>
</fieldset>
<fieldset>
    <legend>General Info</legend>
    <div>
        <label for="form_for_layout_renderer_test_user_lastName" class="required">Last Name</label>
        <input type="text"
            id="form_for_layout_renderer_test_user_lastName"
            name="form_for_layout_renderer_test[user][lastName]"
            required="required"/>
    </div>
    <div>
        <label for="form_for_layout_renderer_test_user_firstName" class="required">First Name</label>
        <input type="text"
            id="form_for_layout_renderer_test_user_firstName"
            name="form_for_layout_renderer_test[user][firstName]"
            required="required"/>
    </div>
</fieldset>
HTML;

        return $expected;
    }

    /**
     * @return string
     */
    protected function getPhpFormLayoutResult()
    {
        $expected = <<<HTML
Hi World!                <fieldset>
    <legend>Additional Info</legend>
    <div>
        <label for="form_for_layout_renderer_test_jobTitle">Job Title</label>
        <input type="text"
            id="form_for_layout_renderer_test_jobTitle"
            name="form_for_layout_renderer_test[jobTitle]"/>
    </div>
    <div>
        <label>Gender</label>
        <div id="form_for_layout_renderer_test_gender">
            <input type="radio"
                id="form_for_layout_renderer_test_gender_placeholder"
                name="form_for_layout_renderer_test[gender]"
                value="" checked="checked"/>
            <label for="form_for_layout_renderer_test_gender_placeholder">None</label>
            <input type="radio"
                id="form_for_layout_renderer_test_gender_0"
                name="form_for_layout_renderer_test[gender]"
                value="male"/>
            <label for="form_for_layout_renderer_test_gender_0">Male</label>
            <input type="radio"
                id="form_for_layout_renderer_test_gender_1"
                name="form_for_layout_renderer_test[gender]"
                value="female"/>
            <label for="form_for_layout_renderer_test_gender_1">Female</label>
        </div>
    </div>
</fieldset>
<fieldset>
    <legend>General Info</legend>
    <div>
        <label class="required" for="form_for_layout_renderer_test_user_lastName">Last Name</label>
        <input type="text"
            id="form_for_layout_renderer_test_user_lastName"
            name="form_for_layout_renderer_test[user][lastName]"
            required="required"/>
    </div>
    <div>
        <label class="required" for="form_for_layout_renderer_test_user_firstName">First Name</label>
        <input type="text"
            id="form_for_layout_renderer_test_user_firstName"
            name="form_for_layout_renderer_test[user][firstName]"
            required="required"/>
    </div>
</fieldset>
HTML;

        return $expected;
    }
}