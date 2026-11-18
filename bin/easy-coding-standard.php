<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Basic\BracesFixer;
use PhpCsFixer\Fixer\Basic\BracesPositionFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoAliasTagFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

$setDir = __DIR__ . '/../vendor/symplify/easy-coding-standard/config/set/';

return ECSConfig::configure()
    ->withSets([
        $setDir . 'common/array.php',
        $setDir . 'common/control-structures.php',
        $setDir . 'common/docblock.php',
        $setDir . 'common/namespaces.php',
        $setDir . 'clean-code.php',
        $setDir . 'psr12.php',
    ])
    ->withRules([
        DeclareStrictTypesFixer::class,
        StrictComparisonFixer::class,
        StrictParamFixer::class,
    ])
    ->withSpacing(Option::INDENTATION_SPACES)
    ->withSkip([
        BlankLineAfterOpeningTagFixer::class,
        BracesFixer::class,
        BinaryOperatorSpacesFixer::class,
    ])
    ->withConfiguredRule(PhpdocNoAliasTagFixer::class, [
        'replacements' => [
            'link' => 'see',
            'type' => 'var',
        ],
    ])
    ->withConfiguredRule(ReturnTypeDeclarationFixer::class, [
        'space_before' => 'one',
    ])
    ->withConfiguredRule(ControlStructureContinuationPositionFixer::class, [
        'position' => ControlStructureContinuationPositionFixer::NEXT_LINE,
    ])
    ->withConfiguredRule(BracesPositionFixer::class, [
        'functions_opening_brace' => BracesPositionFixer::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END,
    ]);
