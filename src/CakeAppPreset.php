<?php
declare(strict_types=1);

namespace Crustum\StructArmed\Cake;

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\PresetInterface;
use Boundwize\StructArmed\Rule\Rules\Class_\ClassNameMustHaveSuffixRule;
use Boundwize\StructArmed\Rule\Rules\Class_\MaxDependencyCountRule;
use Boundwize\StructArmed\Rule\Rules\Layer\MayNotDependOnRule;
use Boundwize\StructArmed\Rule\Rules\Method\MaxCyclomaticComplexityRule;
use Boundwize\StructArmed\Rule\Rules\Method\MaxMethodLengthRule;
use Boundwize\StructArmed\Rule\Rules\Method\MustHaveReturnTypeRule;
use Boundwize\StructArmed\Rule\Rules\Usage\MayNotCallFunctionRule;
use Boundwize\StructArmed\Rule\Rules\Usage\MayNotUseLanguageConstructRule;
use Boundwize\StructArmed\Rule\Rules\Usage\MayNotUseSuperglobalsRule;
use function sprintf;
use function str_replace;
use function strtolower;

/**
 * StructArmed preset for CakePHP 5 application conventions.
 *
 * Defines namespace-pattern layers for the standard Cake layout and applies
 * layer-isolation + quality rules on top. Templates (`templates/`) are not
 * classes, so they are excluded from ruleset evaluation.
 *
 * Layers:
 *   Controller, Component, Table, Entity, Behavior, View, Cell, Helper, Command
 */
final readonly class CakeAppPreset implements PresetInterface
{
    // Layer isolation
    public const TABLE_NOT_DEPEND_CONTROLLER = 'cakephp.layer.table_not_depend_controller';

    public const TABLE_NOT_DEPEND_VIEW = 'cakephp.layer.table_not_depend_view';

    public const ENTITY_NOT_DEPEND_CONTROLLER = 'cakephp.layer.entity_not_depend_controller';

    public const ENTITY_NOT_DEPEND_VIEW = 'cakephp.layer.entity_not_depend_view';

    public const VIEW_NOT_DEPEND_MODEL = 'cakephp.layer.view_not_depend_model';

    public const VIEW_NOT_DEPEND_CONTROLLER = 'cakephp.layer.view_not_depend_controller';

    public const HELPER_NOT_DEPEND_MODEL = 'cakephp.layer.helper_not_depend_model';

    public const HELPER_NOT_DEPEND_CONTROLLER = 'cakephp.layer.helper_not_depend_controller';

    public const CELL_NOT_DEPEND_CONTROLLER = 'cakephp.layer.cell_not_depend_controller';

    public const COMMAND_NOT_DEPEND_VIEW = 'cakephp.layer.command_not_depend_view';

    // Naming
    public const CONTROLLER_NAME_MUST_END_WITH_CONTROLLER = 'cakephp.controller.name_must_end_with_controller';

    public const TABLE_NAME_MUST_END_WITH_TABLE = 'cakephp.table.name_must_end_with_table';

    public const BEHAVIOR_NAME_MUST_END_WITH_BEHAVIOR = 'cakephp.behavior.name_must_end_with_behavior';

    public const COMPONENT_NAME_MUST_END_WITH_COMPONENT = 'cakephp.component.name_must_end_with_component';

    public const CELL_NAME_MUST_END_WITH_CELL = 'cakephp.cell.name_must_end_with_cell';

    public const HELPER_NAME_MUST_END_WITH_HELPER = 'cakephp.helper.name_must_end_with_helper';

    public const COMMAND_NAME_MUST_END_WITH_COMMAND = 'cakephp.command.name_must_end_with_command';

    // Controller quality
    public const CONTROLLER_MAX_COMPLEXITY = 'cakephp.controller.max_complexity';

    public const CONTROLLER_MAX_METHOD_LENGTH = 'cakephp.controller.max_method_length';

    public const CONTROLLER_MAX_DEPENDENCIES = 'cakephp.controller.max_dependencies';

    public const CONTROLLER_NO_SUPERGLOBALS = 'cakephp.controller.no_superglobals';

    public const CONTROLLER_MUST_HAVE_RETURN_TYPES = 'cakephp.controller.must_have_return_types';

    // Model / ORM quality
    public const TABLE_NO_SUPERGLOBALS = 'cakephp.table.no_superglobals';

    public const TABLE_MUST_HAVE_RETURN_TYPES = 'cakephp.table.must_have_return_types';

    public const ENTITY_NO_SUPERGLOBALS = 'cakephp.entity.no_superglobals';

    public const ENTITY_MUST_HAVE_RETURN_TYPES = 'cakephp.entity.must_have_return_types';

    public const BEHAVIOR_MUST_HAVE_RETURN_TYPES = 'cakephp.behavior.must_have_return_types';

    // View / Cell / Helper quality
    public const VIEW_MAX_COMPLEXITY = 'cakephp.view.max_complexity';

    public const VIEW_NO_SUPERGLOBALS = 'cakephp.view.no_superglobals';

    public const CELL_MAX_COMPLEXITY = 'cakephp.cell.max_complexity';

    public const CELL_MUST_HAVE_RETURN_TYPES = 'cakephp.cell.must_have_return_types';

    public const HELPER_NO_SUPERGLOBALS = 'cakephp.helper.no_superglobals';

    public const HELPER_MUST_HAVE_RETURN_TYPES = 'cakephp.helper.must_have_return_types';

    // Command quality
    public const COMMAND_MAX_COMPLEXITY = 'cakephp.command.max_complexity';

    public const COMMAND_NO_SUPERGLOBALS = 'cakephp.command.no_superglobals';

    public const COMMAND_MUST_HAVE_RETURN_TYPES = 'cakephp.command.must_have_return_types';

    /**
     * Constructor.
     *
     * @param string $namespace Root namespace of the application (no trailing `\`),
     *   e.g. `'App'` or `'Crustum\Ai'`.
     * @param int $controllerMaxComplexity Default: 5
     * @param int $controllerMaxMethodLength Default: 20
     * @param int $controllerMaxDependencies Default: 5
     * @param int $viewMaxComplexity Default: 3 (View classes should just load helpers)
     * @param array<string, string> $layerMap Optional map of canonical Cake layer
     *   name => host layer name. When a canonical layer is present in the map, the
     *   preset does NOT register a layerPattern for it (the host already defines it)
     *   and applies its rules against the mapped host layer instead. Use this to
     *   combine the preset with an existing `ruleset()` config that owns the layers,
     *   e.g. `['Controller' => 'Delivery', 'Command' => 'Delivery', 'Table' => 'Model', 'Entity' => 'Model']`.
     */
    public function __construct(
        private string $namespace = 'App',
        private int $controllerMaxComplexity = 5,
        private int $controllerMaxMethodLength = 20,
        private int $controllerMaxDependencies = 5,
        private int $viewMaxComplexity = 3,
        private array $layerMap = [],
    ) {
    }

    /**
     * Apply the preset to the architecture config.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return void
     */
    public function apply(Architecture $architecture): void
    {
        $this
            ->applyLayerPatterns($architecture)
            ->applyLayerIsolation($architecture)
            ->applyNaming($architecture)
            ->applyControllerRules($architecture)
            ->applyModelRules($architecture)
            ->applyViewRules($architecture)
            ->applyCommandRules($architecture)
            ->applySafetyRules($architecture);

        $architecture->skipPathsForRuleset(['templates/']);
    }

    /**
     * Register the Cake namespace layer patterns (skipping mapped ones).
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyLayerPatterns(Architecture $architecture)
    {
        $ns = $this->namespace;

        // `Controller`/`View` match direct children only (`[^\\]+`), so nested
        // Component/Cell/Helper namespaces fall through to their own layers.
        $patterns = [
            'Component' => 'Controller\\\\Component.*',
            'Controller' => 'Controller\\\\[^\\\\]+',
            'Behavior' => 'Model\\\\Behavior.*',
            'Entity' => 'Model\\\\Entity.*',
            'Table' => 'Model\\\\Table.*',
            'Cell' => 'View\\\\Cell.*',
            'Helper' => 'View\\\\Helper.*',
            'View' => 'View\\\\[^\\\\]+',
            'Command' => 'Command.*',
        ];

        foreach ($patterns as $layer => $suffix) {
            if (isset($this->layerMap[$layer])) {
                continue; // host already defines this layer
            }

            $architecture->layerPattern($layer, $this->pattern($ns, $suffix));
        }

        return $this;
    }

    /**
     * Resolve the host layer name for a canonical Cake layer.
     *
     * @param string $layer Canonical layer name
     * @return string
     */
    private function resolved(string $layer): string
    {
        return $this->layerMap[$layer] ?? $layer;
    }

    /**
     * Apply layer-isolation rules (using resolved host layer names).
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyLayerIsolation(Architecture $architecture)
    {
        $c = $this->resolved('Controller');
        $t = $this->resolved('Table');
        $e = $this->resolved('Entity');
        $v = $this->resolved('View');
        $h = $this->resolved('Helper');
        $cell = $this->resolved('Cell');
        $cmd = $this->resolved('Command');

        $architecture
            ->rule(self::TABLE_NOT_DEPEND_CONTROLLER, new MayNotDependOnRule(from: $t, to: $c))
            ->rule(self::TABLE_NOT_DEPEND_VIEW, new MayNotDependOnRule(from: $t, to: $v))
            ->rule(self::ENTITY_NOT_DEPEND_CONTROLLER, new MayNotDependOnRule(from: $e, to: $c))
            ->rule(self::ENTITY_NOT_DEPEND_VIEW, new MayNotDependOnRule(from: $e, to: $v))
            ->rule(self::VIEW_NOT_DEPEND_MODEL, new MayNotDependOnRule(from: $v, to: $t))
            ->rule(self::VIEW_NOT_DEPEND_CONTROLLER, new MayNotDependOnRule(from: $v, to: $c))
            ->rule(self::HELPER_NOT_DEPEND_MODEL, new MayNotDependOnRule(from: $h, to: $t))
            ->rule(self::HELPER_NOT_DEPEND_CONTROLLER, new MayNotDependOnRule(from: $h, to: $c))
            ->rule(self::CELL_NOT_DEPEND_CONTROLLER, new MayNotDependOnRule(from: $cell, to: $c))
            ->rule(self::COMMAND_NOT_DEPEND_VIEW, new MayNotDependOnRule(from: $cmd, to: $v));

        return $this;
    }

    /**
     * Apply naming rules (skipped for mapped layers).
     *
     * Suffix rules are skipped for mapped layers: the host layer is broader
     * than the canonical Cake one (e.g. Delivery holds Controller + Command),
     * so a single suffix would wrongly flag sibling classes.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyNaming(Architecture $architecture)
    {
        if (!isset($this->layerMap['Controller'])) {
            $architecture->rule(self::CONTROLLER_NAME_MUST_END_WITH_CONTROLLER, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Controller'), suffix: 'Controller'));
        }

        if (!isset($this->layerMap['Table'])) {
            $architecture->rule(self::TABLE_NAME_MUST_END_WITH_TABLE, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Table'), suffix: 'Table'));
        }

        if (!isset($this->layerMap['Behavior'])) {
            $architecture->rule(self::BEHAVIOR_NAME_MUST_END_WITH_BEHAVIOR, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Behavior'), suffix: 'Behavior'));
        }

        if (!isset($this->layerMap['Component'])) {
            $architecture->rule(self::COMPONENT_NAME_MUST_END_WITH_COMPONENT, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Component'), suffix: 'Component'));
        }

        if (!isset($this->layerMap['Cell'])) {
            $architecture->rule(self::CELL_NAME_MUST_END_WITH_CELL, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Cell'), suffix: 'Cell'));
        }

        if (!isset($this->layerMap['Helper'])) {
            $architecture->rule(self::HELPER_NAME_MUST_END_WITH_HELPER, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Helper'), suffix: 'Helper'));
        }

        if (!isset($this->layerMap['Command'])) {
            $architecture->rule(self::COMMAND_NAME_MUST_END_WITH_COMMAND, new ClassNameMustHaveSuffixRule(layer: $this->resolved('Command'), suffix: 'Command'));
        }

        return $this;
    }

    /**
     * Apply controller quality rules.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyControllerRules(Architecture $architecture)
    {
        $layer = $this->resolved('Controller');

        $architecture
            ->rule(self::CONTROLLER_MAX_COMPLEXITY, new MaxCyclomaticComplexityRule(layer: $layer, maxComplexity: $this->controllerMaxComplexity))
            ->rule(self::CONTROLLER_MAX_METHOD_LENGTH, new MaxMethodLengthRule(layer: $layer, maxLines: $this->controllerMaxMethodLength))
            ->rule(self::CONTROLLER_MAX_DEPENDENCIES, new MaxDependencyCountRule(layer: $layer, maxCount: $this->controllerMaxDependencies))
            ->rule(self::CONTROLLER_NO_SUPERGLOBALS, new MayNotUseSuperglobalsRule(layer: $layer))
            ->rule(self::CONTROLLER_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $layer));

        return $this;
    }

    /**
     * Apply Model/ORM quality rules.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyModelRules(Architecture $architecture)
    {
        $table = $this->resolved('Table');
        $entity = $this->resolved('Entity');
        $behavior = $this->resolved('Behavior');

        $architecture
            ->rule(self::TABLE_NO_SUPERGLOBALS, new MayNotUseSuperglobalsRule(layer: $table))
            ->rule(self::TABLE_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $table))
            ->rule(self::ENTITY_NO_SUPERGLOBALS, new MayNotUseSuperglobalsRule(layer: $entity))
            ->rule(self::ENTITY_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $entity))
            ->rule(self::BEHAVIOR_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $behavior));

        return $this;
    }

    /**
     * Apply View/Cell/Helper quality rules.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyViewRules(Architecture $architecture)
    {
        $view = $this->resolved('View');
        $cell = $this->resolved('Cell');
        $helper = $this->resolved('Helper');

        $architecture
            ->rule(self::VIEW_MAX_COMPLEXITY, new MaxCyclomaticComplexityRule(layer: $view, maxComplexity: $this->viewMaxComplexity))
            ->rule(self::VIEW_NO_SUPERGLOBALS, new MayNotUseSuperglobalsRule(layer: $view))
            ->rule(self::CELL_MAX_COMPLEXITY, new MaxCyclomaticComplexityRule(layer: $cell, maxComplexity: $this->viewMaxComplexity))
            ->rule(self::CELL_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $cell))
            ->rule(self::HELPER_NO_SUPERGLOBALS, new MayNotUseSuperglobalsRule(layer: $helper))
            ->rule(self::HELPER_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $helper));

        return $this;
    }

    /**
     * Apply Command quality rules.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applyCommandRules(Architecture $architecture)
    {
        $layer = $this->resolved('Command');

        $architecture
            ->rule(self::COMMAND_MAX_COMPLEXITY, new MaxCyclomaticComplexityRule(layer: $layer, maxComplexity: $this->controllerMaxComplexity))
            ->rule(self::COMMAND_NO_SUPERGLOBALS, new MayNotUseSuperglobalsRule(layer: $layer))
            ->rule(self::COMMAND_MUST_HAVE_RETURN_TYPES, new MustHaveReturnTypeRule(layer: $layer));

        return $this;
    }

    /**
     * Apply safety rules — no debug/death constructs anywhere.
     *
     * @param \Boundwize\StructArmed\Architecture $architecture Architecture config
     * @return $this
     */
    private function applySafetyRules(Architecture $architecture)
    {
        foreach (['Controller', 'Table', 'Entity', 'View', 'Helper', 'Command'] as $layer) {
            $resolved = $this->resolved($layer);

            foreach (['dd', 'dump', 'var_dump', 'print_r', 'var_export'] as $fn) {
                $architecture->rule(
                    sprintf('cakephp.safety.%s_no_%s', strtolower($layer), $fn),
                    new MayNotCallFunctionRule(layer: $resolved, function: $fn),
                );
            }

            foreach (['die', 'exit'] as $construct) {
                $architecture->rule(
                    sprintf('cakephp.safety.%s_no_%s', strtolower($layer), $construct),
                    new MayNotUseLanguageConstructRule(layer: $resolved, construct: $construct),
                );
            }
        }

        return $this;
    }

    /**
     * Build a namespace regex pattern for a layer path under the root namespace.
     *
     * `$suffix` is the layer path in regex form and fully controls the match,
     * e.g. `Model\\\\Table.*` for tables or `Controller[^\\\\]+` for direct
     * controller children only.
     *
     * @param string $namespace Root namespace (no trailing backslash)
     * @param string $suffix Regex layer path
     * @return string
     */
    private function pattern(string $namespace, string $suffix): string
    {
        $escaped = str_replace('\\', '\\\\', $namespace);

        return sprintf('/^%s\\\\%s$/', $escaped, $suffix);
    }
}
