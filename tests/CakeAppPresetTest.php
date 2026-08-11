<?php
declare(strict_types=1);

namespace Crustum\StructArmed\Cake\Tests;

use Boundwize\StructArmed\Architecture;
use Crustum\StructArmed\Cake\CakeAppPreset;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CakeAppPresetTest extends TestCase
{
    private function architecture(): Architecture
    {
        $architecture = Architecture::define();
        (new CakeAppPreset())->apply($architecture);

        return $architecture;
    }

    #[Test]
    public function defines_all_cake_layers(): void
    {
        $patterns = $this->architecture()->getLayerPatterns();

        foreach (['Controller', 'Component', 'Table', 'Entity', 'Behavior', 'View', 'Cell', 'Helper', 'Command'] as $layer) {
            $this->assertArrayHasKey($layer, $patterns, "layer [$layer] is missing");
        }
    }

    #[Test]
    public function layer_patterns_match_cake_namespaces(): void
    {
        $patterns = $this->architecture()->getLayerPatterns();

        $cases = [
            'Controller' => 'App\Controller\UsersController',
            'Component' => 'App\Controller\Component\FlashComponent',
            'Table' => 'App\Model\Table\UsersTable',
            'Entity' => 'App\Model\Entity\User',
            'Behavior' => 'App\Model\Behavior\TimestampBehavior',
            'View' => 'App\View\AppView',
            'Cell' => 'App\View\Cell\StatsCell',
            'Helper' => 'App\View\Helper\HtmlHelper',
            'Command' => 'App\Command\SyncCommand',
        ];

        foreach ($cases as $layer => $fqcn) {
            $this->assertMatchesLayer($patterns[$layer]['pattern'], $fqcn, $layer);
        }
    }

    #[Test]
    public function component_does_not_match_controller_layer(): void
    {
        $patterns = $this->architecture()->getLayerPatterns();

        // Component lives under Controller\Component\* — it must resolve to
        // Component, not Controller, even though Controller matches first.
        $this->assertNotMatchesLayer($patterns['Controller']['pattern'], 'App\Controller\Component\FlashComponent', 'Controller');
    }

    #[Test]
    public function applies_layer_isolation_rules(): void
    {
        $rules = $this->architecture()->getRules();

        foreach (
            [
            CakeAppPreset::TABLE_NOT_DEPEND_CONTROLLER,
            CakeAppPreset::TABLE_NOT_DEPEND_VIEW,
            CakeAppPreset::ENTITY_NOT_DEPEND_CONTROLLER,
            CakeAppPreset::VIEW_NOT_DEPEND_MODEL,
            CakeAppPreset::HELPER_NOT_DEPEND_MODEL,
            CakeAppPreset::CELL_NOT_DEPEND_CONTROLLER,
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $rules, "rule [$key] is missing");
        }
    }

    #[Test]
    public function applies_naming_and_quality_rules(): void
    {
        $rules = $this->architecture()->getRules();

        foreach (
            [
            CakeAppPreset::CONTROLLER_NAME_MUST_END_WITH_CONTROLLER,
            CakeAppPreset::TABLE_NAME_MUST_END_WITH_TABLE,
            CakeAppPreset::BEHAVIOR_NAME_MUST_END_WITH_BEHAVIOR,
            CakeAppPreset::COMMAND_NAME_MUST_END_WITH_COMMAND,
            CakeAppPreset::CONTROLLER_MAX_COMPLEXITY,
            CakeAppPreset::TABLE_MUST_HAVE_RETURN_TYPES,
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $rules, "rule [$key] is missing");
        }
    }

    #[Test]
    public function excludes_templates_from_ruleset(): void
    {
        $this->assertContains(
            'templates/',
            $this->architecture()->getRulesetSkipPaths(),
        );
    }

    #[Test]
    public function accepts_custom_namespace(): void
    {
        $architecture = Architecture::define();
        (new CakeAppPreset(namespace: 'Plugin\Foo'))->apply($architecture);

        $patterns = $architecture->getLayerPatterns();

        $this->assertMatchesLayer($patterns['Table']['pattern'], 'Plugin\Foo\Model\Table\UsersTable', 'Table');
    }

    #[Test]
    public function does_not_register_mapped_layers(): void
    {
        $architecture = Architecture::define();
        (new CakeAppPreset(layerMap: ['Controller' => 'Delivery', 'Table' => 'Model']))->apply($architecture);

        $patterns = $architecture->getLayerPatterns();

        $this->assertArrayNotHasKey('Controller', $patterns, 'mapped Controller layer should be left to the host');
        $this->assertArrayNotHasKey('Table', $patterns, 'mapped Table layer should be left to the host');
        $this->assertArrayHasKey('Command', $patterns, 'unmapped Command layer should still be registered');
    }

    #[Test]
    public function mapped_layers_apply_rules_to_host_layer(): void
    {
        $architecture = Architecture::define();
        (new CakeAppPreset(layerMap: ['Controller' => 'Delivery', 'Table' => 'Model']))->apply($architecture);

        // Rules must target the host layer name, not the canonical one.
        $rules = $architecture->getRules();

        $this->assertArrayHasKey(CakeAppPreset::CONTROLLER_MAX_COMPLEXITY, $rules);
        $this->assertArrayHasKey(CakeAppPreset::TABLE_MUST_HAVE_RETURN_TYPES, $rules);

        $this->assertSame('Delivery', $this->ruleLayer($rules[CakeAppPreset::CONTROLLER_MAX_COMPLEXITY]));
        $this->assertSame('Model', $this->ruleLayer($rules[CakeAppPreset::TABLE_MUST_HAVE_RETURN_TYPES]));
    }

    #[Test]
    public function naming_rules_skipped_for_mapped_layers(): void
    {
        $architecture = Architecture::define();
        (new CakeAppPreset(layerMap: ['Controller' => 'Delivery']))->apply($architecture);

        $rules = $architecture->getRules();

        $this->assertArrayNotHasKey(CakeAppPreset::CONTROLLER_NAME_MUST_END_WITH_CONTROLLER, $rules);
        $this->assertArrayHasKey(CakeAppPreset::COMMAND_NAME_MUST_END_WITH_COMMAND, $rules);
    }

    private function assertMatchesLayer(string|array $pattern, string $fqcn, string $layer): void
    {
        foreach ((array)$pattern as $single) {
            if (preg_match($single, $fqcn)) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("[$fqcn] does not match layer [$layer]");
    }

    private function assertNotMatchesLayer(string|array $pattern, string $fqcn, string $layer): void
    {
        foreach ((array)$pattern as $single) {
            if (preg_match($single, $fqcn)) {
                $this->fail("[$fqcn] unexpectedly matches layer [$layer]");
            }
        }

        $this->addToAssertionCount(1);
    }

    private function ruleLayer(object $rule): string
    {
        $property = new ReflectionProperty($rule, 'layer');

        return $property->getValue($rule);
    }
}
