<?php

namespace App\DataFixtures;

use Carbon\Carbon;
use App\Helper\ArrayHelper;
use Doctrine\DBAL\DBALException;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseFixture extends Fixture
{
    public $entityClass;
    public $uniqueField = 'id';
    public $tableName;
    public $data;
    public $created = false;
    public $modified = false;
    public $applied = false;
    public $existing = null;
    public $container;
    
    /**
     * @var
     */
    public $faker;
    
    /**
     * BaseFixture constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    /**
     * @param $array
     * @param $manager
     * @return mixed
     */
    public function getArray($array, $manager)
    {
        return $array;
    }

    public function checkRowExists(ObjectManager $manager, $value)
    {
        return !@$this->existing[$value[$this->uniqueField]];
    }
    
    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        if (null == $this->entityClass) {
            return;
        }
        /*
         * @var $manager ObjectManager|\Doctrine\ORM\EntityManager
         */
        if (!$this->existing) {
            $this->existing = $manager->getRepository($this->entityClass)
            ->findAll();
        }
        if ($this->uniqueField) {
            $this->existing = ArrayHelper::index($this->existing, $this->uniqueField);
        }
        $values = $this->data;
        if ($values) {
            foreach ($values as $value) {
                if ($this->checkRowExists($manager, $value)) {
                    $this->createValue($value, $manager);
                }
            }
        }
    }
    
    /**
     * @param $value
     * @param ObjectManager $manager
     */
    public function createValue($value, ObjectManager $manager)
    {
        $conn = $manager->getConnection();
        $array = $this->getArray($value, $manager);
        if ($this->created) {
            $array['created_at'] = Carbon::now()->toDateTimeString();
        }
        if ($this->modified) {
            $array['modified'] = Carbon::now()->toDateTimeString();
        }
        if ($this->applied) {
            $array['applied'] = Carbon::now()->toDateTimeString();
        }
        try {
            $conn->insert($this->tableName, $array);
        } catch (DBALException $e) {
            echo 'Warning! Fixture Error!'.PHP_EOL."{$e->getMessage()}".PHP_EOL;
        }
    }
    
    /**
     * @param $filePath
     * @param ObjectManager $manager
     */
    public function loadFromSql($filePath, ObjectManager $manager)
    {
        $sqlFile = __DIR__.$filePath;
        $content = file_get_contents($sqlFile);
        $stmt = $manager->getConnection()->prepare($content);
        $stmt->execute();
    }
    
    /**
     * @param $commandClass
     * @param ObjectManager $manager
     */
    public function loadFromCommand($commandClass, ObjectManager $manager)
    {
        $command = (new $commandClass(null, $manager));
        $command->setContainer($this->container);
        $command->run(new StringInput(''), new ConsoleOutput());
    }
}
