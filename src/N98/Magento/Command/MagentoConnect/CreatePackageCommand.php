<?php
/**
 * CreatePackageCommand.php
 *
 * @author    Luke MillsName <luke@aligent.com.au>
 * @link      http://www.aligent.com.au/
 */

namespace N98\Magento\Command\MagentoConnect;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreatePackageCommand
 *
 * @package N98\Magento\Command\MagentoConnect
 */
class CreatePackageCommand extends AbstractMagentoCommand
{

    protected function configure()
    {
        $this->setName('extension:create')
            ->setDescription('Creates a Magento Connect package');

        $help = <<<'HELP'
Creates a Magento Connect package
HELP;
        $this->setHelp($help);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        $output->writeln('Hello World!');



    }
}
