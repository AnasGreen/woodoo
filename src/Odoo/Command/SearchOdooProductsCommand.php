<?php

declare(strict_types=1);

namespace App\Odoo\Command;

use App\Odoo\Client\OdooClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:odoo:search-products',
    description: 'Searches Odoo product templates using read-only JSON-RPC operations.',
)]
final class SearchOdooProductsCommand extends Command
{
    private const RESULT_LIMIT = 20;

    /** @var list<string> */
    private const FIELDS = [
        'id',
        'name',
        'default_code',
        'list_price',
        'public_categ_ids',
    ];

    public function __construct(private readonly OdooClientInterface $odooClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('term', InputArgument::REQUIRED, 'Product name or internal reference to search for.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $term = trim((string) $input->getArgument('term'));

        if ('' === $term) {
            $io->error('The product search term must not be empty.');

            return Command::INVALID;
        }

        $products = $this->odooClient->searchRead(
            'product.template',
            [
                '|',
                ['name', 'ilike', $term],
                ['default_code', 'ilike', $term],
            ],
            self::FIELDS,
            limit: self::RESULT_LIMIT,
            order: 'name asc',
        );

        if ([] === $products) {
            $io->note('No matching Odoo products were found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Name', 'Internal reference', 'List price', 'Public categories'],
            array_map($this->formatProduct(...), $products),
        );

        return Command::SUCCESS;
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
            $this->formatValue($product['default_code'] ?? null),
            $this->formatValue($product['list_price'] ?? null),
            $this->formatValue($product['public_categ_ids'] ?? null),
        ];
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        if (is_bool($value)) {
            return $value ? 'yes' : '';
        }

        return null === $value ? '' : (string) $value;
    }
}
