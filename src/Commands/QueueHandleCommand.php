<?php
/**
 * Created for plugin-component-request-dispatcher
 * Date: 16.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Components\Queue\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

abstract class QueueHandleCommand extends Command
{

    public function __construct(string $name)
    {
        parent::__construct("{$name}:handle");
    }

    protected function configure()
    {
        $this
            ->setDescription('Get operation by model id & run it in background')
            ->addArgument('id', InputArgument::REQUIRED);
    }

}