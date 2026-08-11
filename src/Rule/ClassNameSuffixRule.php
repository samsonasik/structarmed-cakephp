<?php
declare(strict_types=1);

namespace Crustum\StructArmed\Cake\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use function sprintf;

/**
 * Class-name suffix rule that skips traits, interfaces and enums.
 *
 * Cake naming conventions (`...Controller`, `...Table`, `...Command`, ...) apply
 * to concrete classes only — a trait like `UsesCodexConnectionTrait` lives in a
 * Table namespace but must not be forced to end with `Table`. Interfaces and
 * enums follow their own naming schemes, so they are skipped too.
 */
final readonly class ClassNameSuffixRule implements RuleInterface
{
    /**
     * Constructor.
     *
     * @param string $layer Layer name the rule applies to
     * @param string $suffix Required class-name suffix
     */
    public function __construct(
        private string $layer,
        private string $suffix,
    ) {
    }

    /**
     * Whether the rule applies to the given class node.
     *
     * @param \Boundwize\StructArmed\Analyser\ClassNode $classNode Class node
     * @return bool
     */
    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer);
    }

    /**
     * Evaluate the rule against a class node.
     *
     * @param \Boundwize\StructArmed\Analyser\ClassNode $classNode Class node
     * @return \Boundwize\StructArmed\Rule\RuleViolation|null Violation or null when the class passes
     */
    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        if ($classNode->isTrait || $classNode->isInterface || $classNode->isEnum) {
            return null;
        }

        if ($classNode->nameEndsWith($this->suffix)) {
            return null;
        }

        return new RuleViolation(
            message: sprintf(
                'Class [%s] must have suffix [%s]',
                $classNode->className,
                $this->suffix,
            ),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
