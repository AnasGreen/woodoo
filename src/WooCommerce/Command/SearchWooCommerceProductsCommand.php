<?php

declare(strict_types=1);

namespace App\WooCommerce\Command;

use App\WooCommerce\Client\WooCommerceClientInterface;
use App\WooCommerce\Exception\WooCommerceInvalidResponseException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:woocommerce:search-products',
    description: 'Searches WooCommerce products using a non-destructive GET request.',
)]
final class SearchWooCommerceProductsCommand extends Command
{
    private const RESULT_LIMIT = 20;

    private const FIELDS = 'id,name,sku,price,stock_quantity,status,categories';

    public function __construct(private readonly WooCommerceClientInterface $wooCommerceClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('term', InputArgument::REQUIRED, 'Product name or SKU to search for.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $term = trim((string) $input->getArgument('term'));

        if ('' === $term) {
            $io->error('The WooCommerce product search term must not be empty.');

            return Command::INVALID;
        }

        $products = $this->wooCommerceClient->get('products', [
            'search' => $term,
            'per_page' => self::RESULT_LIMIT,
            'page' => 1,
            'orderby' => 'title',
            'order' => 'asc',
            '_fields' => self::FIELDS,
        ]);
        $this->assertProductList($products);

        if ([] === $products) {
            $io->note('No matching WooCommerce products were found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Name', 'SKU', 'Price', 'Stock quantity', 'Status', 'Categories'],
            array_map($this->formatProduct(...), $products),
        );

        return Command::SUCCESS;
    }

    /**
     * @phpstan-assert list<array<string, mixed>> $products
     *
     * @param array<array-key, mixed> $products
     */
    private function assertProductList(array $products): void
    {
        if (!array_is_list($products)) {
            throw new WooCommerceInvalidResponseException('WooCommerce returned an invalid product list.');
        }

        foreach ($products as $product) {
            if (!is_array($product)) {
                throw new WooCommerceInvalidResponseException('WooCommerce returned an invalid product record.');
            }
        }
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return list<string>
     */
    private function formatProduct(array $product): array
    {
        return [
            $this->formatValue($product['id'] ?? null),
            $this->formatValue($product['name'] ?? null),
            $this->formatValue($product['sku'] ?? null),
            $this->formatValue($product['price'] ?? null),
            $this->formatValue($product['stock_quantity'] ?? null),
            $this->formatValue($product['status'] ?? null),
            $this->formatCategories($product['categories'] ?? null),
        ];
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : '';
        }

        return null === $value ? '' : (string) $value;
    }

    private function formatCategories(mixed $categories): string
    {
        if (!is_array($categories)) {
            return '';
        }

        $names = [];
        foreach ($categories as $category) {
            if (is_array($category) && is_string($category['name'] ?? null)) {
                $names[] = $category['name'];
            }
        }

        return implode(', ', $names);
    }
}
