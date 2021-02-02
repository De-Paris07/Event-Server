<?php

namespace App\Command;

use App\Helper\StringHelper;
use App\Service\ElasticsearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class ReindexDatabaseCommand
 *
 * @package App\Command
 */
class ReindexDatabaseCommand extends Command
{
    /** @var InputInterface $input */
    private $input;

    /** @var OutputInterface $output */
    private $output;

    /** @var array $indexes */
    private $indexes;

    /** @var ElasticsearchService $elasticsearchService */
    private $elasticsearchService;

    protected static $defaultName = 'event:elastic:reindex';

    /**
     * ReindexDatabaseCommand constructor.
     *
     * @param ElasticsearchService $elasticsearchService
     * @param string | null $name
     */
    public function __construct(ElasticsearchService $elasticsearchService, string $name = null)
    {
        parent::__construct($name);

        $this->elasticsearchService = $elasticsearchService;
    }

    protected function configure()
    {
        $this->setDescription('Переиндексация документов');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->indexes = $this->elasticsearchService->getIndexes();
        $this->reindexMonths();
//        $this->reindexQuarters();
        $this->reindexYears();
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function reindexMonths()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m') - 1;
        $currentYear = $date->format('Y');
        $handler = function ($currentYear, $currentMonth) {
            for ($month = 1; $month <= $currentMonth; $month++) {
                $month = $month < 10 ? "0$month" : $month;
                $mergeIndexes = array_filter(array_keys($this->indexes), function ($name) use ($currentYear, $month) {
                    preg_match("/event-($currentYear).($month).(\d+)/", $name, $matches);
                    return !empty($matches);
                });

                if (empty($mergeIndexes) || count($mergeIndexes) === 1) {
                    continue;
                }

                $this->reindex($mergeIndexes, "event-$currentYear.$month", "event-$currentYear.$month.35");
            }
        };

        if ($currentMonth === 0 || $currentMonth == 1) {
            $handler((int) $currentYear - 1, 12);
        }

        $handler($currentYear, $currentMonth);
    }

    private function reindexQuarters()
    {

    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function reindexYears()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y') - 1;
        $handler = function ($currentYear) {
            $mergeIndexes = array_filter(array_keys($this->indexes), function ($name) use ($currentYear) {
                preg_match("/event-($currentYear).(\d+)/", $name, $matches);
                return !empty($matches);
            });

            if (empty($mergeIndexes) || count($mergeIndexes) === 1) {
                return;
            }

            $this->reindex($mergeIndexes, "event-$currentYear.1-12", "event-$currentYear.15");
        };

        if ($currentMonth === 0 || $currentMonth == 1) {
            return;
        }

        $handler($currentYear);
    }

    /**
     * @param array $mergeIndexes
     * @param string $newName
     * @param string $timeIndex
     * @param bool $createIndex
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function reindex(array $mergeIndexes, string $newName, string $timeIndex, $createIndex = true)
    {
        try {
            $this->output->writeln('Создание индекса ' . $newName);
            $mergeResponse = $this->mergeIndexes($mergeIndexes, $newName, $createIndex);
            $this->output->writeln("Индекс $newName успешно создан.");
            $this->output->writeln("Всего {$mergeResponse['total']}, создано - {$mergeResponse['created']}, обновлено - {$mergeResponse['updated']}");
            $this->elasticsearchService->removeIndexes($mergeIndexes);
        } catch (BadRequestHttpException $exception) {
            $errors = json_decode($exception->getMessage(), true);
            $this->output->writeln("Всего {$errors['total']}, создано - {$errors['created']}, обновлено - {$errors['updated']}");
            $errors = isset($errors['failures']) ? $errors['failures'] : [];
            $this->output->writeln('Ошибка слияния индексов.');
            $this->output->writeln('Автоматическое исправление конфликтов. Количество конфликтов - ' . count($errors));
            $this->elasticsearchService->removeIndex($newName);

            if ($createIndex) {
                try {
                    $this->createIndex($timeIndex);
                } catch (\Exception $exception) {

                }

                $mergeIndexes[] = $timeIndex;
            }

            $this->updateDocuments($errors, $timeIndex);
            $this->reindex($mergeIndexes, $newName, $timeIndex,false);
        }
    }

    /**
     * @param array $errorsDocuments
     * @param string $timeIndex
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    private function updateDocuments(array $errorsDocuments, string $timeIndex)
    {
        foreach ($errorsDocuments as $document) {
            $errorMessage = $document['cause']['reason'] ?? null;

            if (is_null($errorMessage)) {
                continue;
            }

            $this->output->writeln('Исправление конфликта документа ' . $document['id'] . ' в индексе ' . $document['index']);
            $this->output->writeln('Ошибка - ' . $errorMessage);
            $result = $this->parseErrorMessage($errorMessage);
            $documents = $this->elasticsearchService->getDocument($document['id']);

            if (!isset($documents['hits']['hits']) || count($documents = $documents['hits']['hits']) === 0) {
                continue;
            }

            foreach ($documents as $doc) {
                $data = $this->getUpdateData($result, $doc);

                if (empty($data)) {
                    continue;
                }

                if ('date' === $this->getErrorCode($result)) {
                    $this->elasticsearchService->updateDocument($doc['_index'], $doc['_id'], $data);
                } else {
                    $this->addDocument($timeIndex, $doc['_id'], $data);

                    if ($timeIndex !== $doc['_index']) {
                        $this->elasticsearchService->deleteDocument($doc['_index'], $doc['_id']);
                    }
                }
            }
        }
    }

    /**
     * @param string $errorMessage
     *
     * @return array | null
     */
    private function parseErrorMessage(string $errorMessage)
    {
        $result = null;
        preg_match_all('/([^ ,]*?) \[(.*?)\]/', $errorMessage, $matches);

        if (empty($matches)) {
            return null;
        }

        if (isset($matches[1][0]) && isset($matches[2][0])) {
            $result[$matches[1][0]] = $matches[2][0];
        }

        if (isset($matches[1][1]) && isset($matches[2][1])) {
            $result[$matches[1][1]] = $matches[2][1];
        }

        if (isset($matches[1][2]) && isset($matches[2][2])) {
            $result[$matches[1][2]] = $matches[2][2];
        }

        return $result;
    }

    /**
     * @param array $matches
     * @param array $document
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getUpdateData(array $matches, array $document)
    {
        $data = null;

        switch ($this->getErrorCode($matches)) {
            case 'date':
                $data = $this->getUpdateDataByDateToText($matches, $document);
                break;
            case 'changeType':
                $data = $this->getUpdateDataByFailedParseText($matches, $document);
                break;
            default:
                throw new \RuntimeException('Данный тип ошибки пока невозможно решить.');
        }

        return $data;
    }

    /**
     * @param array $matches
     *
     * @return string | null
     */
    private function getErrorCode(array $matches)
    {
        if (isset($matches['mapper']) && isset($matches['current_type']) && isset($matches['merged_type']) &&
            ('date' === $matches['current_type'] && 'text' === $matches['merged_type'] ||
                'text' === $matches['current_type'] && 'date' === $matches['merged_type']
            )
        ) {
            return 'date';
        } elseif (isset($matches['field']) && isset($matches['type']) && 'text' === $matches['type']) {
            return 'changeType';
        }

        return null;
    }

    /**
     * @param array $matches
     * @param array $document
     *
     * @return array|null
     *
     * @throws \Exception
     */
    private function getUpdateDataByDateToText(array $matches, array $document)
    {
        $value = $this->getDataDocumentByPath($matches['mapper'], $document);
        $data = null;

        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as $key => $_val) {
                $value[$key] = date_create($_val) instanceof \DateTime ? (new \DateTime($_val))->format('d-m-Y H:i:s.u') : $_val;
            }
        } else {
            $value = (new \DateTime($value))->format('d-m-Y H:i:s.u');
        }

        return $this->getUpdateDataDocumentByPath($matches['mapper'], $value, $document);
    }

    /**
     * @param array $matches
     * @param array $document
     *
     * @return array | null
     */
    private function getUpdateDataByFailedParseText(array $matches, array $document)
    {
        $value = $this->getDataDocumentByPath($matches['field'], $document);

        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (StringHelper::isJson($item)) {
                    $value[$key] = json_decode($item);
                }
            }

            $value = json_encode($value, 128 | 256);
        } else {
            $value = (string) $value;
        }

        return $this->getUpdateDataDocumentByPath($matches['field'], $value, $document);
    }

    /**
     * @param string $path
     * @param array $document
     *
     * @return array | null
     */
    private function getDataDocumentByPath(string $path, array $document) {
        $input = explode('.', $path);

        if (current($input) === 'doc') {
            array_shift($input);
        }

        $value = null;

        foreach ($input as $key => $item) {
            if (0 === $key) {
                $value = $document['_source'][$item] ?? null;
                continue;
            }

            $value = $value[$item] ?? null;
        }

        return $value;
    }

    /**
     * @param string $path
     * @param $value
     * @param array $document
     *
     * @return array | null
     */
    private function getUpdateDataDocumentByPath(string $path, $value, array $document)
    {
        $input = explode('.', $path);

        if (current($input) === 'doc') {
            array_shift($input);
        }

        $data = null;

        foreach (array_reverse($input) as $key => $item) {
            if (0 === $key) {
                $data = [$item => $value];
                continue;
            }

            $data = [$item => [array_reverse($input)[$key - 1] => $data[array_reverse($input)[$key - 1]]]];
        }

        foreach ($input as $key => $item) {
            if (0 === $key) {
                $document['_source'][$item]  = $data[$item];
                break;
            }
        }

        return $document['_source'];
    }

    /**
     * @param string $index
     * @param string $id
     * @param array $data
     *
     * @return mixed
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    private function addDocument(string $index, string $id, array $data)
    {
        $options['body'] = json_encode($data);

        try {
            return $this->elasticsearchService->addDocument($index, $id, $data);
        } catch (BadRequestHttpException $exception) {
            $errors = json_decode($exception->getMessage(), true);
            $errorMessage = $errors['error']['reason'] ?? null;

            if (is_null($errorMessage)) {
                throw $exception;
            }

            $this->output->writeln('Ошибка создания документа - ' . $errorMessage);
            $this->output->writeln('Исправление ошибки создания.');
            $result = $this->parseErrorMessage($errorMessage);

            $data = $this->getUpdateData($result, ['_source' => $data]);
            $this->addDocument($index, $id, $data);
        }
    }

    /**
     * @param array $from
     * @param string $to
     * @param bool $createIndex
     *
     * @return mixed
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function mergeIndexes(array $from, string $to, bool $createIndex)
    {
        if ($createIndex) {
            $this->createIndex($to);
        }

        return $this->elasticsearchService->mergeIndexes($from, $to);
    }

    /**
     * @param string $name
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function createIndex(string $name)
    {
        $date = new \DateTime();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        $mapping = "event-$year.$month.$day";

        $this->elasticsearchService->createIndex($name, $mapping);
    }
}
