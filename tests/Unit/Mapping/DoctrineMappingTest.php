<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mapping;

use App\Mapping\Entity\BrandMapping;
use App\Mapping\Entity\CategoryMapping;
use App\Mapping\Entity\OrderMapping;
use App\Mapping\Entity\ProductMapping;
use Doctrine\ORM\Mapping\UniqueConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctrineMappingTest extends TestCase
{
    /** @return iterable<string, array{class-string, list<string>}> */
    public static function uniqueConstraints(): iterable
    {
        yield 'product' => [ProductMapping::class, ['odoo_template_id', 'woo_product_id']];
        yield 'category' => [CategoryMapping::class, ['odoo_category_id', 'woo_category_id']];
        yield 'brand' => [BrandMapping::class, ['odoo_value_id', 'woo_brand_id']];
        yield 'order' => [OrderMapping::class, ['woo_order_id', 'idempotency_key']];
    }

    /**
     * @param class-string $entity
     * @param list<string> $expectedColumns
     */
    #[DataProvider('uniqueConstraints')]
    public function testRequiredFieldsHaveIndependentUniqueConstraints(string $entity, array $expectedColumns): void
    {
        $attributes = (new \ReflectionClass($entity))->getAttributes(UniqueConstraint::class);
        $columns = [];
        foreach ($attributes as $attribute) {
            $constraint = $attribute->newInstance();
            self::assertNotNull($constraint->columns);
            self::assertCount(1, $constraint->columns);
            $columns[] = $constraint->columns[0];
        }
        self::assertEqualsCanonicalizing($expectedColumns, $columns);
    }
}
