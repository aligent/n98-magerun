<?php

namespace N98\Magento\Command\Developer\Widget;

use InvalidArgumentException;
use N98\Magento\Command\AbstractMagentoCommand;
use N98\View\PhpView;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create a Magento Widget
 *
 * @author Luke Mills <luke@aligent.com.au>
 */
class CreateCommand extends AbstractMagentoCommand {

    /**
     * @var PhpView
     */
    protected $view;

    /**
     * @var string
     */
    protected $baseFolder;

    /**
     * @var string
     */
    protected $moduleDirectory;

    /**
     * @var string
     */
    protected $vendorNamespace;

    /**
     * @var string
     */
    protected $moduleName;

    /**
     * @var string
     */
    protected $codePool;

    /**
     * @var string
     */
    protected $moduleId;

    /**
     * @var string
     */
    protected $widgetId;

    /**
     * @var string
     */
    protected $widgetName;

    /**
     * @var string
     */
    protected $designPackage;

    /**
     * @var string
     */
    protected $designTheme;

    /**
     * 
     * @var array
     */
    protected $widgetParameters;

    protected function configure() {
        $this
                ->setName('dev:widget:create')
                ->addArgument('vendorNamespace', InputArgument::REQUIRED, 'Namespace (your company prefix, Case sensitive, eg. Foo)')
                ->addArgument('moduleName', InputArgument::REQUIRED, 'Name of your module. (Case sensitive, eg. Bar)')
                ->addArgument('codePool', InputArgument::REQUIRED, 'Codepool (local,community)')
                ->addArgument('moduleId', InputArgument::REQUIRED, 'The identifier for the module (Case sensitive, eg. foo_bar)')
                ->addArgument('widgetId', InputArgument::REQUIRED, 'The identifier for the widget (Case sensitive, eg. my_widget)')
                ->addArgument('widgetName', InputArgument::REQUIRED, 'The name of the widget (Human readable name, eg "My Widget Name" - use quotes if there are spaces)')
                ->addArgument('designPackage', InputArgument::REQUIRED, 'The design package (Case sensitive, eg. base)')
                ->addArgument('designTheme', InputArgument::REQUIRED, 'The design theme (Case sensitive, eg. default)')
                ->addArgument('widgetParameter', InputArgument::IS_ARRAY, 'A list of widget parameters, eg: id=title,label=Title,required=1,visible=1,type=text,sort_order=10 (Quote values if they contain spaces)')
                ->addOption('author-name', null, InputOption::VALUE_OPTIONAL, 'Author for docblock comments')
                ->addOption('author-email', null, InputOption::VALUE_OPTIONAL, 'Author email for docblock comments')
                ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Description docblock comments')
                ->setDescription('Creates an registers new magento widget.' 
                        . ' Creates all required files including widget.xml, block file and template.'
                        . ' If widget.xml exists, it will add to the xml.'
                        . ' Currently the widtget.xml file will require manual formatting after widget creation.'
                        . ' Not widget parameters are not validated.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->detectMagento($output);
        $this->initMagento();
        $this->baseFolder = __DIR__ . '/../../../../../../res/widget/create';
        $this->vendorNamespace = ucfirst($input->getArgument('vendorNamespace'));
        $this->moduleName = ucfirst($input->getArgument('moduleName'));
        $this->codePool = $input->getArgument('codePool');
        $this->moduleId = $input->getArgument('moduleId');
        $this->widgetId = $input->getArgument('widgetId');
        $this->widgetName = $input->getArgument('widgetName');
        $this->designPackage = $input->getArgument('designPackage');
        $this->designTheme = $input->getArgument('designTheme');
        $this->widgetParameters = $this->normaliseParameters($input->getArgument('widgetParameter'));

        if (!in_array($this->codePool, array('local', 'community'))) {
            throw new InvalidArgumentException('Code pool must "community" or "local"');
        }

        $this->initView($input);
        $this->createDirectories($output);

        if (!file_exists($this->getWidgetXmlFilename())) {
            $this->writeWidgetXml($input, $output);
        }
        $this->updateWidgetXml($input, $output);
        $this->writeWidgetBlock($input, $output);
        $this->writeWidgetTemplate($input, $output);
    }

    protected function normaliseParameters($parameters) {
        $normalisedParameters = array();
        foreach ($parameters as $paramString) {
            $tmpParams = explode(',', $paramString);
            $params = array();
            foreach ($tmpParams as $tmp) {
                list($tmpKey, $tmpVal) = explode('=', $tmp);
                $params[$tmpKey] = $tmpVal;
            }
            if (isset($params['id'])) {
                $normalisedParameters[$params['id']] = $params;
            } else {
                $normalisedParameters[] = $params;
            }
            unset($params);
            unset($tmpParams);
        }
        return $normalisedParameters;
    }

    protected function createDirectories(OutputInterface $output) {
        $dirs = array($this->getWidgetBlockDir(), $this->getWidgetTemplateDir());
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
                $output->writeln(sprintf('<info>Created directory: <comment>%s</comment></info>', $dir));
            }
        }
    }

    protected function getModuleDir() {
        $moduleDir = $this->_magentoRootFolder
                . '/app/code/'
                . $this->codePool
                . '/' . $this->vendorNamespace
                . '/' . $this->moduleName;
        if (!file_exists($moduleDir)) {
            throw new RuntimeException(sprintf('Invalid module - could not find module dir %s. Stop.', $moduleDir));
        }
        return $moduleDir;
    }

    protected function getDesignDir() {
        $designDir = $this->_magentoRootFolder
                . '/app/design/frontend'
                . '/' . $this->designPackage
                . '/' . $this->designTheme;
        if (!file_exists($designDir)) {
            throw new RuntimeException(sprintf('Invalid theme - could not find theme dir %s. Stop.', $designDir));
        }
        return $designDir;
    }

    protected function getWidgetBlockDir() {
        return $this->getModuleDir() . '/Block/Widget';
    }

    protected function getWidgetBlockFilename() {
        return $this->getWidgetBlockDir() . '/' . self::uscore2CamelCase($this->widgetId) . '.php';
    }

    protected function getWidgetTemplateDir() {
        return $this->getDesignDir() . '/template/' . $this->moduleId . '/widget';
    }

    protected function getWidgetTemplateFilename() {
        return $this->getWidgetTemplateDir() . '/' . $this->widgetId . '.phtml';
    }

    protected function getWidgetXmlFilename() {
        return $this->getModuleDir() . '/etc/widget.xml';
    }

    protected function initView(InputInterface $input) {
        $view = new PhpView();
        $view->assign('vendorNamespace', $this->vendorNamespace);
        $view->assign('moduleName', $this->moduleName);
        $view->assign('codePool', $this->codePool);
        $view->assign('moduleId', $this->moduleId);
        $view->assign('widgetId', $this->widgetId);
        $view->assign('widgetName', $this->widgetName);
        $view->assign('widgetParameters', $this->widgetParameters);
        $view->assign('widgetTemplateFilename', $this->getWidgetTemplateFilename());
        $view->assign('authorName', $input->getOption('author-name'));
        $view->assign('authorEmail', $input->getOption('author-email'));
        $view->assign('description', $input->getOption('description'));
        $view->assign('blockClass', sprintf('%s_%s_Block_Widget_%s', $this->vendorNamespace, $this->moduleName, self::uscore2UpperUscore($this->widgetId)));
        $this->view = $view;
    }

    protected function updateWidgetXml(InputInterface $input, OutputInterface $output) {
        $this->view->setTemplate($this->baseFolder . '/widget.part.xml.phtml');
        $newWidgetXmlString = $this->view->render();
        $newWidgetXml = new \Varien_Simplexml_Element($newWidgetXmlString);
        $outfile = $this->getWidgetXmlFilename();
        $widgetXml = new \Varien_Simplexml_Element(file_get_contents($outfile));
        $widgetXml->extendChild($newWidgetXml, true);
        $widgetXml->asXML($outfile);
        $output->writeln(sprintf('<info>Updated file: <comment>%s</comment></info>', $outfile));
    }

    protected function writeWidgetXml(InputInterface $input, OutputInterface $output) {
        $this->view->setTemplate($this->baseFolder . '/widget.xml.phtml');
        $outfile = $this->getWidgetXmlFilename();
        file_put_contents($outfile, $this->view->render());
        $output->writeln(sprintf('<info>Created file: <comment>%s</comment></info>', $outfile));
    }

    protected function writeWidgetBlock(InputInterface $input, OutputInterface $output) {
        $templateFilename = $this->baseFolder . '/widget.block.phtml';
        $outfile = $this->getWidgetBlockFilename();
        $this->writeTemplate($input, $output, $templateFilename, $outfile);
    }

    protected function writeWidgetTemplate(InputInterface $input, OutputInterface $output) {
        $templateFilename = $this->baseFolder . '/widget.template.phtml';
        $outfile = $this->getWidgetTemplateFilename();
        $this->writeTemplate($input, $output, $templateFilename, $outfile);
    }

    protected function writeTemplate(InputInterface $input, OutputInterface $output, $templateFilename, $outfile) {
        $this->view->setTemplate($templateFilename);
        file_put_contents($outfile, $this->view->render());
        $output->writeln(sprintf('<info>Created file: <comment>%s</comment></info>', $outfile));
    }

    public static function uscore2CamelCase($value) {
        $parts = explode('_', $value);
        array_walk($parts, function(&$value, $key) { $value = ucfirst($value); });
        $result = implode('', $parts);
        return $result;
    }
    
    public static function uscore2UpperUscore($value) {
        $parts = explode('_', $value);
        array_walk($parts, function (&$value, $key) {$value = ucfirst($value); });
        $result = implode('_', $parts);
        return $result;
    }
    
}