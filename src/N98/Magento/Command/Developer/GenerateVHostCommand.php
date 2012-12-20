<?php

namespace N98\Magento\Command\Developer;

use N98\Magento\Command\AbstractMagentoCommand;
use N98\View\PhpView;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * GenerateVHostCommand
 * 
 * Automatically generates a vhost file for Apache, or optionally nginx.
 *
 * @author Luke Mills <luke@aligent.com.au>
 */
class GenerateVHostCommand extends AbstractMagentoCommand {

    /**
     * @var PhpView
     */
    protected $_view = null;

    protected function configure() {
        $this
                ->setName('dev:vhost:generate')
                ->setDescription('Generate a vhost file for your webserver')
                ->setHelp(<<<HELP
Automatically generates a vhost file for Apache, or optionally nginx.               
HELP
                )
                ->addOption('nginx', null, InputOption::VALUE_NONE, 'Generate nginx VHost')
                ->addOption('dev-mode', null, InputOption::VALUE_NONE, 'Set MAGE_IS_DEVELOPER_MODE to true')
                ->addOption('server-admin', null, InputOption::VALUE_OPTIONAL, "The webmaster's email address", 'webmaster@localhost')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            throw new \RuntimeException('Cannot initialize Magento.');
        }

        $this->_addOptionsToView($input->getOptions());
        $this->_addMagentoSettingsToView($output);

        if ($input->getOption('nginx')) {
            $this->_generateNginxVhost();
        } else {
            $this->_generateApacheVhost();
        }

        $output->writeln($this->_getView()->render());
    }

    protected function _addMagentoSettingsToView(OutputInterface $output) {
        $view = $this->_getView();
        $view->assign('documentRoot', $this->_magentoRootFolder);

        $configPath = 'web/unsecure/base_url';

        $websites = \Mage::app()->getWebsites(true, false);
        
        
        foreach ($websites as $website) {
            $website->hostName = parse_url($website->getConfig($configPath), PHP_URL_HOST);
        }
        
        if (count($websites) > 1 && $websites[0]->code == 'admin') {
            unset($websites[0]);
        }
        
        $view->assign('websites', $websites);
    }

    protected function _addOptionsToView(array $options) {
        $view = $this->_getView();
        foreach ($options as $key => $value) {

            // convert hypenated options to camelCase
            $keyParts = explode('-', $key);
            for ($i = 1; $i < count($keyParts); $i++) {
                $keyParts[$i] = ucfirst($keyParts[$i]);
            }
            $key = implode('', $keyParts);

            $view->assign($key, $value);
        }
    }

    protected function _generateApacheVhost() {
        $view = $this->_getView();
        $view->setTemplate($this->_getBaseFolder() . '/apache/apacheVhost.phtml');
    }

    protected function _generateNginxVhost() {
        
    }

    protected function _getBaseFolder() {
        return __DIR__ . '/../../../../../res/vhost';
    }

    /**
     * @return \Mage_Core_Model_Abstract
     */
    protected function _getConfigDataModel() {
        return $this->_getModel('core/config_data', 'Mage_Core_Model_Config_Data');
    }

    /**
     * 
     * @return PhpView
     */
    protected function _getView() {
        if (is_null($this->_view)) {
            $this->_view = new PhpView();
        }
        return $this->_view;
    }

}
