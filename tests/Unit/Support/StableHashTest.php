<?php

namespace Lyre\Filament\Admin\Tests\Unit\Support;

use Lyre\Filament\Admin\Support\StableHash;
use PHPUnit\Framework\TestCase;

class StableHashTest extends TestCase
{
    public function test_hash_is_twelve_chars_and_deterministic(): void
    {
        $a = StableHash::ofModel('App\\Models\\Foo');
        $b = StableHash::ofModel('App\\Models\\Foo');

        $this->assertSame(12, strlen($a));
        $this->assertSame($a, $b);
    }

    public function test_different_models_produce_different_hashes(): void
    {
        $this->assertNotSame(
            StableHash::ofModel('App\\Models\\Foo'),
            StableHash::ofModel('App\\Models\\Bar')
        );
    }

    public function test_generated_class_name_uses_prefix_and_suffix(): void
    {
        $name = StableHash::generatedClassName('App\\Models\\Foo', 'Resource');

        $this->assertStringStartsWith('Mdl', $name);
        $this->assertStringEndsWith('Resource', $name);
        $this->assertSame('Mdl'.StableHash::ofModel('App\\Models\\Foo').'Resource', $name);
    }
}
