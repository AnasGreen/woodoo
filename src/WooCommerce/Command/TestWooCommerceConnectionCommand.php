<?php

declare(strict_types=1);

namespace App\WooCommerce\Command;

use App\WooCommerce\Client\WooCommerceClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:woocommerce:test-connection',
    description: 'Tests WooCommerce REST API access using a non-destructive GET request.',
)]
final class TestWooCommerceConnectionCommand extends Command
{
    public function __construct(private readonly WooCommerceClientInterface $wooCommerceClient)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->wooCommerceClient->get('products', [
            'per_page' => 1,
            'page' => 1,
            '_fields' => 'id',
        ]);

        (new SymfonyStyle($input, $output))->success('Connection to WooCommerce succeeded.');

        return Command::SUCCESS;
    }
}
