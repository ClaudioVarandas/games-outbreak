<?php

declare(strict_types=1);

namespace App\Enums;

enum PcPortTechniqueEnum: string
{
    case StaticRecompilation = 'static-recompilation';
    case DecompilationNativePort = 'decompilation-native-port';
    case ReverseEngineeredReimplementation = 'reverse-engineered-reimplementation';
    case Decompilation = 'decompilation';
    case ReverseEngineering = 'reverse-engineering';
    case SourcePort = 'source-port';
    case LauncherTool = 'launcher-tool';
    case TexturePack = 'texture-pack';

    public function label(): string
    {
        return match ($this) {
            self::StaticRecompilation => 'Static recompilation',
            self::DecompilationNativePort => 'Decompilation + native port',
            self::ReverseEngineeredReimplementation => 'Reimplementation (reverse engineering)',
            self::Decompilation => 'Decompilation',
            self::ReverseEngineering => 'Reverse engineering',
            self::SourcePort => 'Source port',
            self::LauncherTool => 'Tool / launcher',
            self::TexturePack => 'Texture pack',
        };
    }

    /**
     * The tracker CSV mixes snake_case and kebab-case spellings of the same technique.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return str_replace('_', '-', strtolower(trim($value)));
    }
}
