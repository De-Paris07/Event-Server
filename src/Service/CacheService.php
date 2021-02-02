<?php

namespace App\Service;

use Predis\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CacheService
 *
 * @package App\Services
 */
class CacheService
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /** @var RedisAdapter $cache */
    private $cache;

    /**
     * CacheService constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = new RedisAdapter(
            new Client(['scheme' => 'tcp',
                'host' => getenv('CURRENT_HOST'),
                'port' => 6379,
                'read_write_timeout' => 0
            ]),
            'eventServer'
        );
    }

    /**
     * @param string $requestId
     *
     * @return bool | mixed
     */
    public function getQueryResponse(string $requestId)
    {
        if (!$this->keyExist("query.$requestId")) {
            return false;
        }
        
        return $this->getKey("query.$requestId");
    }

    /**
     * @param string $requestId
     * @param array $data
     * @param null $expiresAfter
     *
     * @return bool
     */
    public function setQueryResponse(string $requestId, array $data, $expiresAfter = null)
    {
        return $this->setKey("query.$requestId", $data, $expiresAfter);
    }
    
    public function checkRoute(string $route)
    {
        return $this->keyExist("query.$route");
    }

    /**
     * @param string $route
     *
     * @return mixed
     */
    public function getRouteValidateSchema(string $route)
    {
        $data = $this->getKey("query.$route");
        
        return $data['validateSchema'];
    }

    /**
     * @param string $route
     * @param array $data
     *
     * @return bool
     */
    public function setRoute(string $route, array $data)
    {
        return $this->setKey("query.$route", $data);
    }

    /**
     * @param string $route
     *
     * @return mixed
     */
    public function removeRoute(string $route)
    {
        return $this->deleteKey("query.$route");
    }

    /**
     * @param $key
     *
     * @return mixed|null
     */
    public function getKey($key)
    {
        $cached = $this->cache->getItem($key);

        return $cached->get();
    }

    /**
     * @param $key
     * @param $value
     * @param null $expiresAfter
     *
     * @return bool
     */
    public function setKey($key, $value, $expiresAfter = null)
    {
        $cached = $this->cache->getItem($key);

        if (!is_null($expiresAfter)) {
            $cached->expiresAfter($expiresAfter);
        }

        $cached->set($value);

        return $this->cache->save($cached);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function keyExist($key)
    {
        $cached = $this->cache->getItem($key);

        if (!$cached->isHit()) {
            return false;
        }

        return true;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public function deleteKey($key)
    {
        return $this->cache->deleteItem($key);
    }
}
