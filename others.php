<?php

namespace NW\WebService\References\Operations\Notification;

use Exception;
use Throwable;

class ContractorLoader
{
    const TYPE_MAP = [
        Customer::TYPE => Customer::class,
        Seller::TYPE   => Seller::class,
        Employee::TYPE => Employee::class,
    ];

    public function getById(int $type, int $id): Contractor
    {
        $class = self::TYPE_MAP[$type];
        $object = new $class();
        $object->getById($id);

        return $object;
    }
}

/**
 * @property Seller $Seller
 */
abstract class Contractor
{
    public $id;
    public $name;
    public $email;
    public $mobile;

    public function loadById(int $id): self
    {
        try {
            // @TODO
            // throw new NotFoundEntityException(sprintf(
            //     'Contractor entity with type "%s" not found', 
            //     $this->getType()
            // ));
        } catch (Exception $e) {
            throw new Exception(sprintf(
                'Error of the load Contractor entity', 
                $this->getType()
            ));
        }

        return $this;
    }

    abstract public function getType(): string;

    public function getFullName(): string
    {
        if (empty($this->name)) {
            throw new Exception('Name is empty!');
        }

        return !empty($this->id) ? $this->name . ' ' . $this->id : $this->name;
    }
}

class Customer extends Contractor
{
    const TYPE = 'customer';

    public function getType(): string
    {
        return static::TYPE;
    }
}

class Employee extends Contractor
{
    const TYPE = 'employee';

    public function getType(): string
    {
        return static::TYPE;
    }
}

class Seller extends Contractor
{
    const TYPE = 'seller';

    public function getType(): string
    {
        return static::TYPE;
    }
}

class Status
{
    const STATUS_MAP = [
        0 => 'Completed',
        1 => 'Pending',
        2 => 'Rejected',
    ];

    public $id;
    public $name;

    public function getName(int $id): string
    {
        return self::STATUS_MAP[$id];
    }
}

abstract class ReferencesOperation
{
    /**
     * @var ContractorLoader $contractorLoader
     */
    protected ContractorLoader $contractorLoader;

    /**
     * @var Helper $helper
     */
    protected Helper $helper;

    /**
     * @var MessagesClient $messagesClient
     */
    protected MessagesClient $messagesClient;

    /**
     * @var NotificationManager $notificationManager
     */
    protected NotificationManager $notificationManager;

    /**
     * @param ContractorLoader $contractorLoader
     * @param Helper $helper
     * @param MessagesClient $messagesClient
     * @param NotificationManager $notificationManager
     */
    public function __construct(
        ContractorLoader $contractorLoader,
        Helper $helper,
        MessagesClient $messagesClient,
        NotificationManager $notificationManager
    ) {
        $this->contractorLoader = $contractorLoader;
        $this->helper = $helper;
        $this->messagesClient = $messagesClient;
        $this->notificationManager = $notificationManager;
    }

    abstract public function doOperation(): OperationResultInterface;

    /**
     * Get request
     * 
     * @param string|null $param
     */
    public function getRequest(?string $param = null)
    {
        return !empty($param) ? $_REQUEST[$param] : $_REQUEST;
    }
}

class Helper
{
    public function getResellerEmailFrom()
    {
        return 'contractor@example.com';
    }

    public function getEmailsByPermit($resellerId, $event)
    {
        // fakes the method
        return ['someemeil@example.com', 'someemeil2@example.com'];
    }
}

class MessagesClient
{
    public function sendMessage(array $emailConfig, array ...$args): bool 
    {
        // @TODO

        return true;
    }
}

class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}

class NotificationManager
{
    public function send(int $resellerId, int $clientId, string $notificationEventStatus, int $differenceTo, array $templateData): array
    {
        // @TODO

        return [];
    }
}

class NotFoundEntityException extends Exception
{
    public function __construct(string $message = 'Not Found Entity', int $code = 0, ?Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}

class DataObject
{
    protected array $data = [];

    public function toArray()
    {
        return get_object_vars($this);
    }
}

interface OperationResultInterface
{
    public function getNotificationEmployeeByEmail(): bool;

    public function setNotificationEmployeeByEmail(bool $value): self;

    public function getNotificationClientByEmail(): bool;

    public function setNotificationClientByEmail(bool $value): self;

    public function getNotificationClientBySms(): NotificationClientBySmsInterface;

    public function setNotificationClientBySms(NotificationClientBySmsInterface $value): self;
}

class OperationResult implements OperationResultInterface
{
    protected bool $notificationEmployeeByEmail = false;
    protected bool $notificationClientByEmail = false;
    protected NotificationClientBySmsInterface $notificationClientBySms;

    public function getNotificationEmployeeByEmail(): bool
    {
        return $this->notificationEmployeeByEmail;
    }

    public function setNotificationEmployeeByEmail(bool $value): self
    {
        $this->notificationEmployeeByEmail = $value;

        return $this;
    }

    public function getNotificationClientByEmail(): bool
    {
        return $this->notificationClientByEmail;
    }

    public function setNotificationClientByEmail(bool $value): self
    {
        $this->notificationClientByEmail = $value;

        return $this;
    }

    public function getNotificationClientBySms(): NotificationClientBySmsInterface
    {
        return $this->notificationClientBySms;
    }

    public function setNotificationClientBySms(NotificationClientBySmsInterface $value): self
    {
        $this->notificationClientBySms = $value;

        return $this;
    }
}

interface NotificationClientBySmsInterface
{
    public function getIsSent(): bool;

    public function setIsSent(bool $value): self;

    public function getMessage(): ?string;

    public function setMessage(?string $value = null): self;
}

class NotificationClientBySms implements NotificationClientBySmsInterface
{
    protected bool $isSent = false;
    protected ?string $message = null;

    public function getIsSent(): bool
    {
        return $this->isSent;
    }

    public function setIsSent(bool $value): self
    {
        $this->isSent = $value;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $value = null): self
    {
        $this->message = $value;

        return $this;
    }
}
