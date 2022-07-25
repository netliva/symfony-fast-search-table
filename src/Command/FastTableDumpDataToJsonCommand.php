<?php

namespace Netliva\SymfonyFastSearchBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class FastTableDumpDataToJsonCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
    	// Her saba saat 5'te cron çalışıyor
        $this
            ->setName('netliva:fast_table:dump_to_json')
            ->addArgument('entity-name', InputArgument::OPTIONAL, 'Oluşturulacak mülke ait tanım bilgisi')
            ->setDescription('Hızlı tablo için verileri json olarak kaydeder');
    }


    protected function interact (InputInterface $input, OutputInterface $output)
    {
        if (null === $input->getArgument('entity-name'))
        {
            $entityNames = array_keys($this->getContainer()->getParameter('netliva_fast_search.entities'));
            $argument    = $this->getDefinition()->getArgument('entity-name');
            $question    = new Question($argument->getDescription());
            $question->setAutocompleterValues($entityNames);

            $question->setValidator(function ($value) use ($entityNames) {
                if ('' === trim($value)) {
                    throw new \Exception('Değer Boş Olamaz');
                }

                if (!in_array(trim($value), $entityNames)) {
                    throw new \Exception('Değer geçerli değil. Olası değerler; '. implode(', ', $entityNames));
                }

                return $value;
            })->setMaxAttempts(20);

            $io = new SymfonyStyle($input, $output);
            $value = $io->askQuestion($question);
            $input->setArgument('entity-name', $value);
        }


    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entKey = $input->getArgument('entity-name');
        $output->writeln($entKey. ' için veriler json´a yazılıyor!');

        $fss         = $this->getContainer()->get('netliva_fastSearchServices');
        $entityInfos = $this->getContainer()->getParameter('netliva_fast_search.entities');
        $cachePath   = $this->getContainer()->getParameter('netliva_fast_search.cache_path');

        if(!is_dir($cachePath))
            mkdir($cachePath, 0777, true);

        $filePath = $cachePath.'/'.$entKey.'.json';
        if(file_exists($filePath))
            unlink($filePath);

        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get('doctrine')->getManager();
        $entities = $em->getRepository($entityInfos[$entKey]['class'])->findAll();

        $data = [];

        $io = new SymfonyStyle($input, $output);
        $io->createProgressBar();
        $io->progressStart(count($entities));
        foreach ($entities as $entity)
        {
            $data[] = $fss->getEntObj($entity, $entityInfos[$entKey]['fields']);
            $io->progressAdvance();
        }

        file_put_contents($filePath, json_encode($data));


        $io->newLine();
        $output->writeln('Başarıyla Kaydedildi');

    }

}
