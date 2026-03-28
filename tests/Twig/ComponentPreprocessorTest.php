<?php

declare(strict_types=1);

namespace Tests\Twig;

use App\Twig\ComponentPreprocessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComponentPreprocessorTest extends TestCase
{
    #[Test]
    public function it_transforms_self_closing_tag_without_attributes(): void
    {
        $result = ComponentPreprocessor::process('<twig:Divider />');

        $this->assertSame("{% include 'components/Divider.html.twig' only %}", $result);
    }

    #[Test]
    public function it_transforms_self_closing_tag_with_static_attributes(): void
    {
        $result = ComponentPreprocessor::process('<twig:Button label="Click me" variant="primary" />');

        $this->assertSame(
            "{% include 'components/Button.html.twig' with { label: 'Click me', variant: 'primary' } only %}",
            $result,
        );
    }

    #[Test]
    public function it_transforms_self_closing_tag_with_dynamic_attribute(): void
    {
        $result = ComponentPreprocessor::process('<twig:Button label="Submit" :disabled="form.loading" />');

        $this->assertSame(
            "{% include 'components/Button.html.twig' with { label: 'Submit', disabled: form.loading } only %}",
            $result,
        );
    }

    #[Test]
    public function it_transforms_block_component_with_default_slot(): void
    {
        $source = '<twig:Alert type="warning">Watch out!</twig:Alert>';

        $result = ComponentPreprocessor::process($source);

        $this->assertSame(
            "{% embed 'components/Alert.html.twig' with { type: 'warning' } %}{% block content %}Watch out!{% endblock %}{% endembed %}",
            $result,
        );
    }

    #[Test]
    public function it_transforms_block_component_with_named_slot(): void
    {
        $source = '<twig:Card><twig:block name="header">Title</twig:block><p>Body</p></twig:Card>';

        $result = ComponentPreprocessor::process($source);

        $this->assertSame(
            "{% embed 'components/Card.html.twig' %}{% block header %}Title{% endblock %}{% block content %}<p>Body</p>{% endblock %}{% endembed %}",
            $result,
        );
    }

    #[Test]
    public function it_handles_nested_components(): void
    {
        $source = '<twig:Card><twig:Alert type="info">Nested</twig:Alert></twig:Card>';

        $result = ComponentPreprocessor::process($source);

        $inner = "{% embed 'components/Alert.html.twig' with { type: 'info' } %}{% block content %}Nested{% endblock %}{% endembed %}";
        $expected = "{% embed 'components/Card.html.twig' %}{% block content %}{$inner}{% endblock %}{% endembed %}";

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_ignores_lowercase_twig_block_tag_in_nesting_detection(): void
    {
        $source = '<twig:Card><twig:block name="header">H</twig:block>Body</twig:Card>';

        $result = ComponentPreprocessor::process($source);

        $this->assertStringContainsString("{% block header %}H{% endblock %}", $result);
        $this->assertStringContainsString("{% block content %}Body{% endblock %}", $result);
    }

    #[Test]
    public function it_leaves_non_component_content_untouched(): void
    {
        $source = '<div class="foo">{{ variable }}</div>';

        $this->assertSame($source, ComponentPreprocessor::process($source));
    }
}
