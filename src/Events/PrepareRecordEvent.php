<?php
namespace Netliva\SymfonyFastSearchBundle\Events;


use Symfony\Contracts\EventDispatcher\Event;

class PrepareRecordEvent extends Event
{
    private $entity;
    private $fKey;
    private $fields;
    private $entityKey;
    private $value;

	/**
	 * PrepareRecordEvent constructor.
	 */
	public function __construct ($entity, string $fKey, array $fields, string $entityKey, $value) {
        $this->entity    = $entity;
        $this->fKey      = $fKey;
        $this->fields    = $fields;
        $this->entityKey = $entityKey;
        $this->value     = $value;
	}


    /**
     * @return mixed
     */
    public function getEntity ()
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getFKey (): string
    {
        return $this->fKey;
    }

    /**
     * @return array
     */
    public function getFields (): array
    {
        return $this->fields;
    }

    /**
     * @return string
     */
    public function getEntityKey (): string
    {
        return $this->entityKey;
    }


    /**
     * @return mixed
     */
    public function getValue ()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue ($value): void
    {
        $this->value = $value;
    }



}
