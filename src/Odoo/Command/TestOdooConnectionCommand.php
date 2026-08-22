<?php

declare(strict_types=1);

namespace App\Odoo\Command;

use App\Odoo\Client\OdooClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:odoo:test-connection',
    description: 'Tests the configured Odoo connection without reading or modifying business data.',
)]
final class TestOdooConnectionCommand extends Command
{
    public function __construct(private readonly OdooClientInterface $odooClient)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->odooClient->authenticate();

        (new SymfonyStyle($input, $output))->success('Connection to Odoo succeeded.');

        return Command::SUCCESS;
    }
}
