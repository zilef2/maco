<?php

namespace Tests\Unit;

use App\Models\materias_primas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillableWithTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_fillable_with_types_structure(): void
    {
        $result = materias_primas::getFillableWithTypes();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        foreach ($result as $item) {
            $this->assertArrayHasKey('order', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertNotEquals('id', $item['order']);
        }
    }
}
