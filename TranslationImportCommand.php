<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Dumper\DumperInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ExceptionInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslationImportCommand extends Command
{
    protected static $defaultName = 'app:translation:import';

    private $translator;
    private $writer;
    private $reader;
    private $finder;
    private $filesystem;

    public function __construct(TranslationWriterInterface $writer, TranslationReaderInterface $reader)
    {
        parent::__construct();

        $this->writer = $writer;
        $this->reader = $reader;
        $this->translator = new Translator('en');
        $this->finder = new Finder();
        $this->filesystem = new Filesystem();
    }

    protected function configure()
    {
        $this
            ->setDescription('Translation convert command from an input format to another format')
            ->setHelp('You must specify a path using the --path option.')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Specify a path of files')
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Specify a input translation format')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Specify an output translation format (default: xliff)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $rootDir = $kernel->getContainer()->getParameter('kernel.root_dir');

        $this->translator->addLoader('xliff', new XliffFileLoader());

        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $path = $input->getOption('path');
        $inputFormat = $input->getOption('input');
        $outputFormat = $input->getOption('output') ?: 'xlf';

        if (!$inputFormat) {
            throw new InvalidArgumentException('You must specify a --input format option.');
        }

        if (!$path || !$this->filesystem->exists($path)) {
            throw new InvalidArgumentException('You must specify a valid --path option.');
        }

        // check format
        $supportedFormats = $this->writer->getFormats();
        if (!\in_array($outputFormat, $supportedFormats, true)) {
            $errorIo->error(['Wrong output format', 'Supported formats are: '.implode(', ', $supportedFormats).'.']);

            return 1;
        }

        $files = $this->finder->name('/[a-z]+\\.[a-z]{2}\\.[a-z]+/')->in($path);

        foreach ($files as $file) {
            list($domain, $language) = explode('.', $file->getFilename());

            $catalogue = new MessageCatalogue($language);
            $output->writeln(sprintf('Starts importing file %s', $file->getRealPath()));

            $lines = file($file->getRealPath());
            foreach ($lines as $line) {
                $line = str_replace(array("\n", "\""), array("", ""), $line); //remove double quotes and eol
                $linecontent = explode(',', $line); //make array by the comma
                $catalogue->add([
                    $linecontent[0] => $linecontent[1]
                ]);
            }

            /*
            $catalogue->setMetadata('original-content', ['notes' => [
                ['category' => 'state', 'content' => 'new'],
                ['category' => 'approved', 'content' => 'true'],
                ['category' => 'section', 'content' => 'user login', 'priority' => '1'],
            ]]);
            */

            $dumper = new XliffFileDumper();
            $dumper->formatCatalogue($catalogue, 'messages', [
                'default_locale'    => $language,
                'xliff_version'     => '2.0'
            ]);

            $this->writer->write($catalogue, $outputFormat, ['path' => $rootDir . "/../translations", 'default_locale' => $language, 'xliff_version' => '2.0']);
            $output->writeln('Importing finished');
        }
    }

}
