<?php
namespace Netliva\SymfonyFastSearchBundle\Events;


use Symfony\Component\EventDispatcher\Event;

class BeforeViewEvent extends Event
{
    private $records;
    private $entityInfos;
    private $entityKey;

	/**
	 * PrepareRecordEvent constructor.
	 */
	public function __construct (?array $records, string $entityKey, array $entityInfos) {
        $this->records      = $records;
        $this->entityKey   = $entityKey;
        $this->entityInfos = $entityInfos;
	}


    /**
     * @return array|null
     */
    public function getRecords ()
    {
        return $this->records;
    }

    /**
     * @param array $records
     */
    public function setRecords (array $records): void
    {
        $this->records = $records;
    }

    /**
     * @param array $record
     */
    public function updateRecord (int $key, array $record): void
    {
        $this->records[$key] = $record;
    }


    /**
     * @return array
     */
    public function getEntityInfos (): array
    {
        return $this->entityInfos;
    }

    /**
     * @return string
     */
    public function getEntityKey (): string
    {
        return $this->entityKey;
    }



}
