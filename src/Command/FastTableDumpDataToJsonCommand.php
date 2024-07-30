<?php

namespace Netliva\SymfonyFastSearchBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Netliva\SymfonyFastSearchBundle\Services\FastSearchServices;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FastTableDumpDataToJsonCommand extends Command
{
    public function __construct (
        private readonly FastSearchServices $fss,
        private readonly EntityManagerInterface $em,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }


    protected function configure()
    {
        // Her saba saat 5'te cron çalışıyor
        $this
            ->setName('netliva:fast_table:dump_to_json')
            ->addArgument('entity-name', InputArgument::OPTIONAL, 'Oluşturulacak mülke ait tanım bilgisi')
            ->addOption('limit', 'l', InputArgument::OPTIONAL, 'Her bir sorgunun getireceği kayıt sayısı')
            ->setDescription('Hızlı tablo için verileri json olarak kaydeder');
    }


    protected function interact (InputInterface $input, OutputInterface $output)
    {
        if (null === $input->getArgument('entity-name'))
        {
            $entityNames = array_keys($this->container->getParameter('netliva_fast_search.entities'));
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
        $limit  = $input->getOption('limit');
        $output->writeln($entKey. ' için veriler json´a yazılıyor!');

        $entityInfos = $this->container->getParameter('netliva_fast_search.entities');
        $cachePath   = $this->container->getParameter('netliva_fast_search.cache_path');

        if(!is_dir($cachePath))
            mkdir($cachePath, 0777, true);

        $tempPath = $cachePath.'/'.$entKey.'-temp.json';
        $filePath = $cachePath.'/'.$entKey.'.json';
        if(file_exists($tempPath))
            unlink($tempPath);

        $main_alias = $entityInfos[$entKey]['alias']?:'ent';
        $qb = $this->em->createQueryBuilder();
        $qb->select('count('.$main_alias.'.id)');
        $qb->from($entityInfos[$entKey]['class'],$main_alias);
        $this->fss->addWhereToQuery($qb, $entityInfos[$entKey]);

        $count = $qb->getQuery()->getSingleScalarResult();

        $io = new SymfonyStyle($input, $output);
        $io->createProgressBar();
        $io->progressStart($count);


        $say = 0;
        if (!$limit) $limit = 500;
        $dataFile = fopen($tempPath, 'w');
        fwrite($dataFile, "[".PHP_EOL);
        // $data = [];
        for ($i = 0; $i<ceil($count/$limit); $i++)
        {
            $this->em->clear();
            $qb = $this->em->getRepository($entityInfos[$entKey]['class'])->createQueryBuilder($main_alias);
            $qb->setMaxResults($limit);
            $qb->setFirstResult($i*$limit);
            $this->fss->addWhereToQuery($qb, $entityInfos[$entKey]);
            $query = $qb->getQuery();
            // $query->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);
            foreach ($query->getResult() as $entity)
            {
                $say++;
                // $data[] = $this->fss->getEntObj($entity, $entityInfos[$entKey]['fields'], $entKey);
                $data = $this->fss->getEntObj($entity, $entityInfos[$entKey]['fields'], $entKey);
                unset($entity);
                fwrite($dataFile, json_encode($data).($say==$count?'':',').PHP_EOL);
                unset($data);
                $io->progressAdvance();
            }

        }
        fwrite($dataFile, "]");
        fclose($dataFile);


        if(file_exists($tempPath))
        {
            if(file_exists($filePath))
                unlink($filePath);
            rename($tempPath, $filePath);
        }

        // file_put_contents($filePath, json_encode($data));


        $io->newLine();
        $output->writeln('Başarıyla Kaydedildi');

    }

}
