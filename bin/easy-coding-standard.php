<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Basic\BracesPositionFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

$rootDir = __DIR__ . '/../';

return static function (ECSConfig $ecsConfig) use ($rootDir) : void {
    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/common/array.php');

    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/common/control-structures.php');

    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/common/docblock.php');

    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/common/namespaces.php');

    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/common/strict.php');

    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/clean-code.php');

    $ecsConfig->import($rootDir . 'vendor/symplify/easy-coding-standard/config/set/psr12.php');

    $ecsConfig->indentation(Option::INDENTATION_SPACES);
    $ecsConfig->skip([
        BlankLineAfterOpeningTagFixer::class => null,
        PhpCsFixer\Fixer\Basic\BracesFixer::class => null,
        BinaryOperatorSpacesFixer::class => null,
    ]);

    $ecsConfig->ruleWithConfiguration(ReturnTypeDeclarationFixer::class, [
        'space_before' => 'one',
    ]);
    $ecsConfig->ruleWithConfiguration(ControlStructureContinuationPositionFixer::class, [
        'position' => ControlStructureContinuationPositionFixer::NEXT_LINE,
    ]);
    $ecsConfig->ruleWithConfiguration(BracesPositionFixer::class, [
        'functions_opening_brace' => BracesPositionFixer::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END,
    ]);
};
