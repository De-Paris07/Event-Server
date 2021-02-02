<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\Event;
use App\Entity\UnhandledEventsClient;
use App\Service\ElasticsearchService;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CheckElasticDataCommand
 *
 * @package App\Command
 */
class CheckElasticDataCommand extends Command
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var ElasticsearchService $elasticsearchService */
    private $elasticsearchService;

    /**
     * CheckElasticDataCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param ElasticsearchService $elasticsearchService
     * @param string|null $name
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ElasticsearchService $elasticsearchService,
        string $name = null
    ) {
        parent::__construct($name);

        $this->entityManager = $entityManager;
        $this->elasticsearchService = $elasticsearchService;
    }

    protected function configure()
    {
        $this->setName('event:check:elastic:data')
            ->setDescription('Чистит события в базе, если они есть в эластике')
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'output',
                true
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $count = 0;
        $limit = 500;
        $time = Carbon::now()->subWeeks()->startOfDay();
        $notEvents = [];

        $countEvents = $this->entityManager
            ->getRepository(Event::class)
            ->createQueryBuilder('event')
            ->select('COUNT(DISTINCT event.id)')
            ->andWhere('event.created_at < :created')
            ->setParameter('created', $time)
            ->getQuery()->getSingleScalarResult();

        $output->writeln("$countEvents строк для обработки");

        while (true) {
            $builder = $this->entityManager
                ->getRepository(Event::class)
                ->createQueryBuilder('event')
                ->andWhere('event.created_at < :created')
                ->setParameter('created', $time)
                ->setMaxResults($limit);

            if (!empty($notEvents)) {
                $builder->andWhere('event.id NOT IN (:notEvents)')
                    ->setParameter('notEvents', $notEvents);
            }

            /** @var Event[] $events */
            $events = $builder->getQuery()->getResult();

            if (empty($events)) {
                $output->writeln('Нет событий для удаления');
                break;
            }

            foreach ($events as $event) {
                $useClient = $this->entityManager->getRepository(Client::class)
                    ->findOneBy(['lastEvent' => $event]);

                $useUnhandled = $this->entityManager->getRepository(UnhandledEventsClient::class)
                    ->findOneBy(['event' => $event]);

                if (!is_null($useClient) || !is_null($useUnhandled)) {
                    array_push($notEvents, $event->getId());
                    continue;
                }

                try {
                    $documents = $this->elasticsearchService->getDocument($event->getId());
                } catch (\Exception $exception) {
                    $output->writeln("Ошибка при извлечении документа {$event->getId()} из elasticSearch");
                    continue;
                }

                if (empty($documents)) {
                    array_push($notEvents, $event->getId());
                    continue;
                }

                foreach ($documents as $document) {
                    if (!empty($document['_source'])
                        && !empty($document['_source']['serviceName'])
                        && isset($document['_source']['history'])
                        && !empty($document['_source']['eventName'])
                        && !empty($document['_source']['eventData'])
                    ) {
                        $this->entityManager->remove($event);
                        break;
                    }

                    array_push($notEvents, $event->getId());
                    continue;
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            gc_collect_cycles();

            $count = $count + count($events);
            $output->writeln("Обработали $count событий");
            $this->entityManager->clear();
        }
    }
}
