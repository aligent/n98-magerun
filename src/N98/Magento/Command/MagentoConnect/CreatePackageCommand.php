<?php
/**
 * CreatePackageCommand.php
 *
 * @author    Luke Mills <luke@aligent.com.au>
 * @link      http://www.aligent.com.au/
 */

namespace N98\Magento\Command\MagentoConnect;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

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
             ->setDescription('Creates a Magento Connect package')
             ->addOption('package-yaml-file', 'f', InputOption::VALUE_OPTIONAL, 'path/to/package.yaml');

        $help = <<<'HELP'
Creates a Magento Connect package
HELP;
        $this->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        $this->initMagento();

        $packageYamlPath = $this->getPackageYamlPath($input);
        $packageYaml     = file_get_contents($packageYamlPath);

        $data = Yaml::parse($packageYaml);

        $this->checkYamlVersion($data);
        $this->cleanData($data);

        $this->savePackage($data, $output);
        $this->createPackage($data, $output);

//        $output->write(var_export($data, true), true);
    }

    /**
     * @return string
     */
    protected function getPackageYamlPath(InputInterface $input)
    {
        $packageYamlPath = $input->getOption('package-yaml-file');
        if (!$packageYamlPath) {
            $packageYamlFilename = 'package.yaml';
            $magentoRootFolder   = $this->getApplication()->getMagentoRootFolder();
            $packageYamlPath     = $magentoRootFolder . DIRECTORY_SEPARATOR . $packageYamlFilename;
        }
        if (!file_exists($packageYamlPath)) {
            throw new \Exception(sprintf("File '%s' not found.", $packageYamlPath));
        }

        return $packageYamlPath;
    }

    protected function checkYamlVersion($data)
    {
        if (!isset($data['package_yaml_version']) || $data['package_yaml_version'] != '0.0') {
            throw new \Exception('Invlaid package_yaml_version, only 0.0 supported');
        }
    }

    protected function cleanData(&$data)
    {
        $removeKeys = array(
            'package_yaml_version',
            '_create',
        );
        foreach ($removeKeys as $key) {
            if (isset($data[$key])) {
                unset($data[$key]);
            }
        }
        if (!isset($data['version_ids'])) {
            $data['version_ids'] = array();
        }
        if (isset($data['contents'])) {
            // Reshuffle contents sub arrays, adding a new empty item to each, as Magento strangely drops the first item of each one.
            // @see \Mage_Connect_Model_Extension::_setContents
            foreach($data['contents'] as &$content) {
                $tmpContent = array('');
                foreach ($content as $item)
                {
                    $tmpContent[] = $item;
                }
                $content = $tmpContent;
            }
            unset($target);
        }
        return $data;
    }

    protected function savePackage($data, OutputInterface $output) {
        $ext = $this->_getModel('connect/extension', null);
        $ext->setData($data);
        if ($ext->savePackage()) {
            $output->writeln('The package data has been saved.');
        } else {
            throw new \Exception('There was a problem saving the package data. Please check var/connect exists and is writable');
        }
    }

    protected function createPackage($data, OutputInterface $output) {
        $ext = $this->_getModel('connect/extension', null);
        $ext->setData($data);
        $packageVersion = $data['version_ids'];
        if (is_array($packageVersion)) {
            if (in_array(\Mage_Connect_Package::PACKAGE_VERSION_2X, $packageVersion)) {
                $ext->createPackage();
            }
            if (in_array(\Mage_Connect_Package::PACKAGE_VERSION_1X, $packageVersion)) {
                $ext->createPackageV1x();
            }
        }
        $output->writeln('The package was created successfully');
    }

}
