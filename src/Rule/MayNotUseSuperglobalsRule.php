<?php
declare(strict_types=1);

namespace Crustum\StructArmed\Cake\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use function implode;
use function in_array;
use function sprintf;

/**
 * Superglobal rule with an allowlist.
 *
 * Most classes must not touch `$_ENV`/`$_SERVER`, but dev tooling legitimately
 * seeds process environment (e.g. an MCP inspector that sets ALLOWED_ORIGINS for
 * a child process). Allow those classes explicitly instead of disabling the rule
 * for the whole layer.
 */
final readonly class MayNotUseSuperglobalsRule implements RuleInterface
{
    /**
     * @param list<string> $allowlist Fully-qualified class names exempt from the rule
     */
    public function __construct(
        private string $layer,
        private array $allowlist = [],
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
        if (in_array($classNode->className, $this->allowlist, true)) {
            return null;
        }

        if (! $classNode->accessesSuperglobals()) {
            return null;
        }

        return new RuleViolation(
            message: sprintf(
                'Class [%s] must not access superglobals directly (%s)',
                $classNode->className,
                implode(', ', $classNode->superglobals),
            ),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
