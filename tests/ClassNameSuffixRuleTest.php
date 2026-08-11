<?php
declare(strict_types=1);

namespace Crustum\StructArmed\Cake\Tests;

use Boundwize\StructArmed\Analyser\ClassNode;
use Crustum\StructArmed\Cake\Rule\ClassNameSuffixRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassNameSuffixRuleTest extends TestCase
{
    private function node(string $short, bool $isTrait = false, bool $isInterface = false, bool $isEnum = false): ClassNode
    {
        return new ClassNode(
            className: 'App\Model\Table\\' . $short,
            file: __FILE__,
            line: 1,
            layer: 'Table',
            extends: null,
            isAbstract: false,
            isFinal: false,
            isInterface: $isInterface,
            isReadonly: false,
            isTrait: $isTrait,
            isEnum: $isEnum,
        );
    }

    #[Test]
    public function passes_concrete_class_with_suffix(): void
    {
        $rule = new ClassNameSuffixRule(layer: 'Table', suffix: 'Table');

        $this->assertNull($rule->evaluate($this->node('UsersTable')));
    }

    #[Test]
    public function fails_concrete_class_without_suffix(): void
    {
        $rule = new ClassNameSuffixRule(layer: 'Table', suffix: 'Table');

        $this->assertNotNull($rule->evaluate($this->node('Users')));
    }

    #[Test]
    public function skips_trait(): void
    {
        $rule = new ClassNameSuffixRule(layer: 'Table', suffix: 'Table');

        $this->assertNull($rule->evaluate($this->node('UsesCodexConnectionTrait', isTrait: true)));
    }

    #[Test]
    public function skips_interface(): void
    {
        $rule = new ClassNameSuffixRule(layer: 'Table', suffix: 'Table');

        $this->assertNull($rule->evaluate($this->node('UsersInterface', isInterface: true)));
    }

    #[Test]
    public function skips_enum(): void
    {
        $rule = new ClassNameSuffixRule(layer: 'Table', suffix: 'Table');

        $this->assertNull($rule->evaluate($this->node('UserStatus', isEnum: true)));
    }

    #[Test]
    public function does_not_apply_outside_layer(): void
    {
        $rule = new ClassNameSuffixRule(layer: 'Command', suffix: 'Command');

        $this->assertFalse($rule->appliesTo($this->node('UsersTable')));
    }
}
