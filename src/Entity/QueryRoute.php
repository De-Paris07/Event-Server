<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="query_route", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="name", columns={"name", "client_id"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\QueryRouteRepository")
 */
class QueryRoute
{
    /**
     * @var string
     *
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, options={"comment"="Наименование"})
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", options={"comment"="Наименование"})
     */
    private $description;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", length=6, options={"comment"="Дата создания"})
     */
    private $createdAt;

    /**
     * Клиент, который может обработать данный запрос
     *
     * @var Client
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Client", inversedBy="routes")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="client_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     * })
     */
    private $client;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @param mixed $client
     */
    public function setClient(?Client $client): void
    {
        $this->client = $client;
    }
}
