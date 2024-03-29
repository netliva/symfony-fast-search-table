<?php
namespace Netliva\SymfonyFastSearchBundle\Events;


use Symfony\Contracts\EventDispatcher\Event;

class BeforeViewEvent extends Event
{
    private $records;
    private $entityInfos;
    private $entityKey;
    private $requests;

	/**
	 * PrepareRecordEvent constructor.
	 */
	public function __construct (?array $records, string $entityKey, array $entityInfos, $requests) {
        $this->records     = $records;
        $this->entityKey   = $entityKey;
        $this->entityInfos = $entityInfos;
        $this->requests    = $requests;
	}


    /**
     * @return array|null
     */
    public function getRequests ()
    {
        return $this->requests;
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
